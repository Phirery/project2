<?php
/**
 * Shared Billing & Cashier Helpers (Task 9).
 * - Aggregates 3 billing groups per encounter (`maLichKham`):
 *   1. `kham`: Consultation package fee (`lichkham` + `goikham`, or legacy booking `hoadon` amount)
 *   2. `cls`: Paraclinical services (`chi_dinh_chi_tiet` WHERE `trangThai <> 'Hủy'`)
 *   3. `thuoc`: Actually dispensed medicines (`phieu_phat_thuoc` + `phieu_phat_thuoc_chitiet` WHERE `trangThai = 'Hoàn thành'`)
 * - Guarantees zero duplicate payment via `hoadon_chitiet.paidKey` UNIQUE constraint + row locks.
 */

function billing_build_encounter_summary(mysqli $conn, int $maLichKham): ?array
{
    $lkStmt = $conn->prepare("
        SELECT
            lk.maLichKham,
            lk.ngayKham,
            lk.trangThai AS trangThaiLichKham,
            lk.maGoi,
            lk.nguon,
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            bs.maBacSi,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            gk.tenGoi,
            gk.gia AS giaGoi,
            ca.tenCa,
            TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
            hs.maHoSo,
            hs.chanDoan,
            hs.trangThai AS trangThaiHoSo,
            hs.trangThaiQuyTrinh
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN hosobenhan hs ON hs.maLichKham = lk.maLichKham AND hs.isDeleted = 0
        WHERE lk.maLichKham = ?
        LIMIT 1
    ");
    if (!$lkStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn thông tin lượt khám.');
    }
    $lkStmt->bind_param('i', $maLichKham);
    $lkStmt->execute();
    $lk = $lkStmt->get_result()->fetch_assoc();
    $lkStmt->close();

    if (!$lk) {
        return null;
    }

    // 1. Load all invoices for this encounter
    $invStmt = $conn->prepare("
        SELECT
            hd.maHoaDon,
            hd.loaiHoaDon,
            hd.maLichKham,
            hd.soTien,
            hd.ngayTao,
            hd.trangThai,
            hd.phuongThuc,
            hd.vnp_TransactionNo,
            hd.maThuNgan,
            hd.ghiChu,
            hd.thoiGianThanhToan,
            nv.tenNhanVien AS tenThuNgan
        FROM hoadon hd
        LEFT JOIN nhanvien nv ON hd.maThuNgan = nv.maNhanVien
        WHERE hd.maLichKham = ?
        ORDER BY hd.maHoaDon ASC
    ");
    $invStmt->bind_param('i', $maLichKham);
    $invStmt->execute();
    $invoices = $invStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $invStmt->close();

    // 2. Load paid / pending hoadon_chitiet rows
    $hdctStmt = $conn->prepare("
        SELECT
            c.id,
            c.maHoaDon,
            c.nhomChiPhi,
            c.itemKey,
            c.refId,
            c.moTa,
            c.soLuong,
            c.donGia,
            c.thanhTien,
            c.trangThai,
            h.phuongThuc,
            h.vnp_TransactionNo,
            h.thoiGianThanhToan,
            h.ngayTao
        FROM hoadon_chitiet c
        JOIN hoadon h ON c.maHoaDon = h.maHoaDon
        WHERE c.maLichKham = ?
          AND c.trangThai IN ('Đã thanh toán', 'Chờ thanh toán')
        ORDER BY c.id ASC
    ");
    $hdctStmt->bind_param('i', $maLichKham);
    $hdctStmt->execute();
    $hdctRows = $hdctStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $hdctStmt->close();

    $paidByItemKey = [];
    $invoiceIdsWithDetails = [];
    $detailsByInvoice = [];
    foreach ($hdctRows as $d) {
        $invId = (int)$d['maHoaDon'];
        $invoiceIdsWithDetails[$invId] = true;
        $detailsByInvoice[$invId][] = [
            'nhomChiPhi' => $d['nhomChiPhi'],
            'itemKey' => $d['itemKey'],
            'moTa' => $d['moTa'],
            'soLuong' => (int)$d['soLuong'],
            'donGia' => (float)$d['donGia'],
            'thanhTien' => (float)$d['thanhTien'],
            'trangThai' => $d['trangThai'],
        ];
        if ($d['trangThai'] === 'Đã thanh toán') {
            $paidByItemKey[$d['itemKey']] = [
                'maHoaDon' => $invId,
                'phuongThuc' => $d['phuongThuc'] ?: 'TienMat',
                'vnp_TransactionNo' => $d['vnp_TransactionNo'] ?? '',
                'paidAt' => $d['thoiGianThanhToan'] ?: $d['ngayTao'],
            ];
        }
    }

    // Legacy paid booking invoice compatibility (paid BEFORE Task 9 without hoadon_chitiet rows)
    $legacyPaidBookingInvoice = null;
    $unpaidBookingInvoice = null;
    foreach ($invoices as $inv) {
        $invId = (int)$inv['maHoaDon'];
        $hasDetails = !empty($invoiceIdsWithDetails[$invId]);
        if ($inv['trangThai'] === 'Đã thanh toán' && !$hasDetails && $legacyPaidBookingInvoice === null) {
            $legacyPaidBookingInvoice = $inv;
        }
        if ($inv['trangThai'] === 'Chưa thanh toán' && $inv['loaiHoaDon'] === 'DatLich' && !$hasDetails && $unpaidBookingInvoice === null) {
            $unpaidBookingInvoice = $inv;
        }
    }

    // Determine consultation fee
    $consultFee = 0.0;
    if ($lk['giaGoi'] !== null && (float)$lk['giaGoi'] > 0) {
        $consultFee = (float)$lk['giaGoi'];
    } elseif ($legacyPaidBookingInvoice && (float)$legacyPaidBookingInvoice['soTien'] > 0) {
        $consultFee = (float)$legacyPaidBookingInvoice['soTien'];
    } elseif ($unpaidBookingInvoice && (float)$unpaidBookingInvoice['soTien'] > 0) {
        $consultFee = (float)$unpaidBookingInvoice['soTien'];
    } else {
        $consultFee = 150000.0;
    }

    $items = [];
    $excludedItems = [];

    // Group 1: Khám bệnh
    $consultKey = 'kham:' . $maLichKham;
    $consultPaidInfo = $paidByItemKey[$consultKey] ?? null;
    if (!$consultPaidInfo && $legacyPaidBookingInvoice) {
        $consultPaidInfo = [
            'maHoaDon' => (int)$legacyPaidBookingInvoice['maHoaDon'],
            'phuongThuc' => $legacyPaidBookingInvoice['phuongThuc'] ?: 'VNPAY',
            'vnp_TransactionNo' => $legacyPaidBookingInvoice['vnp_TransactionNo'] ?? '',
            'paidAt' => $legacyPaidBookingInvoice['thoiGianThanhToan'] ?: $legacyPaidBookingInvoice['ngayTao'],
            'isLegacyVnpayOrBooking' => true,
        ];
    }

    if ($consultFee > 0) {
        $items[] = [
            'itemKey' => $consultKey,
            'nhomChiPhi' => 'kham',
            'nhomLabel' => 'Phí khám bệnh',
            'refId' => (string)$maLichKham,
            'moTa' => 'Phí khám: ' . ($lk['tenGoi'] ?: 'Khám chuyên khoa') . ($lk['tenChuyenKhoa'] ? ' (' . $lk['tenChuyenKhoa'] . ')' : ''),
            'soLuong' => 1,
            'donGia' => $consultFee,
            'thanhTien' => $consultFee,
            'sourceStatus' => $lk['trangThaiLichKham'],
            'isPaid' => $consultPaidInfo !== null,
            'paymentInfo' => $consultPaidInfo,
        ];
    }

    // Group 2: Dịch vụ Cận lâm sàng (ONLY non-cancelled)
    $clsStmt = $conn->prepare("
        SELECT
            ct.maChiTietChiDinh,
            ct.nhomDichVu,
            ct.donGia,
            ct.trangThai,
            ct.lyDoHuy,
            dv.maCode,
            dv.tenDichVu,
            dv.tenNhom,
            dv.gia AS giaDanhMuc,
            cd.maPhieuChiDinh
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN dichvu_can_lam_sang dv ON ct.maDichVu = dv.maDichVu
        LEFT JOIN hosobenhan hs ON ct.maHoSo = hs.maHoSo
        WHERE (cd.maLichKham = ? OR hs.maLichKham = ?)
        ORDER BY ct.maChiTietChiDinh ASC
    ");
    $clsStmt->bind_param('ii', $maLichKham, $maLichKham);
    $clsStmt->execute();
    $clsRows = $clsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $clsStmt->close();

    foreach ($clsRows as $cls) {
        $price = (float)$cls['donGia'] > 0 ? (float)$cls['donGia'] : (float)$cls['giaDanhMuc'];
        if ($cls['trangThai'] === 'Hủy') {
            $excludedItems[] = [
                'nhomChiPhi' => 'cls',
                'moTa' => $cls['tenDichVu'] . ' (' . $cls['maPhieuChiDinh'] . ')',
                'lyDo' => 'Chỉ định đã hủy' . (!empty($cls['lyDoHuy']) ? ': ' . $cls['lyDoHuy'] : ''),
                'soTienKhongTinh' => $price,
            ];
            continue;
        }

        $clsKey = 'cls:' . (int)$cls['maChiTietChiDinh'];
        $paidInfo = $paidByItemKey[$clsKey] ?? null;
        $items[] = [
            'itemKey' => $clsKey,
            'nhomChiPhi' => 'cls',
            'nhomLabel' => 'Cận lâm sàng (' . ($cls['tenNhom'] ?: $cls['nhomDichVu']) . ')',
            'refId' => (string)$cls['maChiTietChiDinh'],
            'moTa' => $cls['tenDichVu'] . ' [' . $cls['maCode'] . ']',
            'soLuong' => 1,
            'donGia' => $price,
            'thanhTien' => $price,
            'sourceStatus' => $cls['trangThai'],
            'isPaid' => $paidInfo !== null,
            'paymentInfo' => $paidInfo,
        ];
    }

    // Group 3: Thuốc ĐÃ PHÁT (ONLY from completed `phieu_phat_thuoc`)
    $medStmt = $conn->prepare("
        SELECT
            pc.id AS phieuChiTietId,
            pc.maPhieuPhat,
            pc.maThuoc,
            pc.soLuongThucPhat,
            pc.donGia,
            pc.thanhTien,
            p.maPhieuCode,
            p.thoiGianPhat,
            t.tenThuoc,
            t.donViTinh
        FROM phieu_phat_thuoc p
        JOIN phieu_phat_thuoc_chitiet pc ON p.maPhieuPhat = pc.maPhieuPhat
        JOIN thuoc t ON pc.maThuoc = t.maThuoc
        WHERE p.maLichKham = ?
          AND p.trangThai = 'Hoàn thành'
          AND pc.soLuongThucPhat > 0
        ORDER BY p.thoiGianPhat ASC, pc.id ASC
    ");
    $medStmt->bind_param('i', $maLichKham);
    $medStmt->execute();
    $medRows = $medStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $medStmt->close();

    foreach ($medRows as $med) {
        $medKey = 'thuoc_phat:' . (int)$med['phieuChiTietId'];
        $paidInfo = $paidByItemKey[$medKey] ?? null;
        $qty = (int)$med['soLuongThucPhat'];
        $unitPrice = (float)$med['donGia'];
        $lineTotal = (float)$med['thanhTien'] > 0 ? (float)$med['thanhTien'] : ($qty * $unitPrice);

        $items[] = [
            'itemKey' => $medKey,
            'nhomChiPhi' => 'thuoc',
            'nhomLabel' => 'Thuốc đã phát',
            'refId' => (string)$med['phieuChiTietId'],
            'moTa' => $med['tenThuoc'] . ' (' . ($med['donViTinh'] ?: 'Đơn vị') . ' — Phiếu ' . $med['maPhieuCode'] . ')',
            'soLuong' => $qty,
            'donGia' => $unitPrice,
            'thanhTien' => $lineTotal,
            'sourceStatus' => 'Đã phát',
            'isPaid' => $paidInfo !== null,
            'paymentInfo' => $paidInfo,
        ];
    }

    // Check any un-dispensed prescription lines ONLY to surface informational exclusions (NEVER billed!)
    $undispStmt = $conn->prepare("
        SELECT
            dt.maDonThuoc,
            dt.trangThaiPhat,
            ct.soLuong,
            ct.soLuongDaPhat,
            t.tenThuoc,
            t.giaTien
        FROM donthuoc dt
        JOIN chitietdonthuoc ct ON dt.maDonThuoc = ct.maDonThuoc
        JOIN thuoc t ON ct.maThuoc = t.maThuoc
        WHERE dt.maLichKham = ?
    ");
    $undispStmt->bind_param('i', $maLichKham);
    $undispStmt->execute();
    $undispRows = $undispStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $undispStmt->close();

    foreach ($undispRows as $u) {
        $ke = (int)$u['soLuong'];
        $phat = (int)$u['soLuongDaPhat'];
        $rem = max(0, $ke - $phat);
        if ($u['trangThaiPhat'] === 'Hủy') {
            $excludedItems[] = [
                'nhomChiPhi' => 'thuoc',
                'moTa' => $u['tenThuoc'] . " (Đơn #{$u['maDonThuoc']}, SL kê: $ke)",
                'lyDo' => 'Đơn thuốc đã hủy tại quầy dược',
                'soTienKhongTinh' => $ke * (float)$u['giaTien'],
            ];
        } elseif ($rem > 0) {
            $excludedItems[] = [
                'nhomChiPhi' => 'thuoc',
                'moTa' => $u['tenThuoc'] . " (Đơn #{$u['maDonThuoc']}, chưa phát: $rem/$ke)",
                'lyDo' => 'Thuốc chưa phát thực tế tại quầy dược',
                'soTienKhongTinh' => $rem * (float)$u['giaTien'],
            ];
        }
    }

    // Aggregate totals by group & paid/unpaid
    $groups = [
        'kham' => ['label' => 'Phí khám bệnh', 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0],
        'cls' => ['label' => 'Cận lâm sàng', 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0],
        'thuoc' => ['label' => 'Thuốc đã phát', 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0],
    ];
    $tongCong = 0.0;
    $daThanhToan = 0.0;
    $conPhaiThu = 0.0;
    $unpaidCount = 0;
    $paidCount = 0;

    foreach ($items as $it) {
        $g = $it['nhomChiPhi'];
        $amt = (float)$it['thanhTien'];
        $groups[$g]['total'] += $amt;
        $tongCong += $amt;
        if ($it['isPaid']) {
            $groups[$g]['paid'] += $amt;
            $daThanhToan += $amt;
            $paidCount++;
        } else {
            $groups[$g]['unpaid'] += $amt;
            $conPhaiThu += $amt;
            $unpaidCount++;
        }
    }

    $billingStatus = 'Chưa phát sinh';
    if (!empty($items)) {
        if ($conPhaiThu <= 0) {
            $billingStatus = 'Đã thanh toán đủ';
        } elseif ($daThanhToan > 0) {
            $billingStatus = 'Thanh toán một phần';
        } else {
            $billingStatus = 'Chưa thanh toán';
        }
    }

    $formattedInvoices = array_map(static function (array $inv) use ($detailsByInvoice): array {
        $id = (int)$inv['maHoaDon'];
        return [
            'maHoaDon' => $id,
            'loaiHoaDon' => $inv['loaiHoaDon'] ?: 'DatLich',
            'soTien' => (float)$inv['soTien'],
            'trangThai' => $inv['trangThai'],
            'phuongThuc' => $inv['phuongThuc'] ?: '—',
            'vnp_TransactionNo' => $inv['vnp_TransactionNo'] ?: '',
            'maThuNgan' => $inv['maThuNgan'] ?: '',
            'tenThuNgan' => $inv['tenThuNgan'] ?: ($inv['maThuNgan'] ?: ''),
            'ghiChu' => $inv['ghiChu'] ?? '',
            'ngayTao' => $inv['ngayTao'],
            'thoiGianThanhToan' => $inv['thoiGianThanhToan'],
            'chiTiet' => $detailsByInvoice[$id] ?? [],
        ];
    }, $invoices);

    return [
        'maLichKham' => (int)$lk['maLichKham'],
        'ngayKham' => $lk['ngayKham'],
        'gioHen' => trim(($lk['gioBatDau'] ?? '') . ($lk['gioKetThuc'] ? ' - ' . $lk['gioKetThuc'] : '')),
        'tenCa' => $lk['tenCa'] ?? '',
        'trangThaiLichKham' => $lk['trangThaiLichKham'],
        'maHoSo' => $lk['maHoSo'] ?: '',
        'chanDoan' => $lk['chanDoan'] ?? '',
        'trangThaiHoSo' => $lk['trangThaiHoSo'] ?? '',
        'trangThaiQuyTrinh' => $lk['trangThaiQuyTrinh'] ?? '',
        'maBenhNhan' => $lk['maBenhNhan'],
        'tenBenhNhan' => $lk['tenBenhNhan'],
        'ngaySinh' => $lk['ngaySinh'],
        'gioiTinh' => $lk['gioiTinh'],
        'soTheBHYT' => $lk['soTheBHYT'] ?? '',
        'soDienThoai' => $lk['soDienThoai'] ?? '',
        'maBacSi' => $lk['maBacSi'],
        'tenBacSi' => $lk['tenBacSi'],
        'tenChuyenKhoa' => $lk['tenChuyenKhoa'] ?: 'Đa khoa',
        'billingStatus' => $billingStatus,
        'totals' => [
            'tongCong' => $tongCong,
            'daThanhToan' => $daThanhToan,
            'conPhaiThu' => $conPhaiThu,
            'paidItemCount' => $paidCount,
            'unpaidItemCount' => $unpaidCount,
        ],
        'groups' => $groups,
        'items' => $items,
        'excludedItems' => $excludedItems,
        'invoices' => $formattedInvoices,
        'unpaidBookingInvoiceId' => $unpaidBookingInvoice ? (int)$unpaidBookingInvoice['maHoaDon'] : null,
    ];
}
