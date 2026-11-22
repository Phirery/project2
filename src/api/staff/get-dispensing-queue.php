<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('GET');
require_staff_permission('pharmacy.dispense');

$tab = trim((string)($_GET['tab'] ?? 'pending'));
if (!in_array($tab, ['pending', 'completed', 'cancelled'], true)) {
    $tab = 'pending';
}

$dateParam = trim((string)($_GET['date'] ?? ''));
if ($dateParam !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    api_error('Định dạng ngày không hợp lệ (YYYY-MM-DD).', 400);
}

$search = trim((string)($_GET['search'] ?? ''));

try {
    $statsSql = "
        SELECT
            SUM(CASE WHEN trangThaiPhat = 'Chờ phát' THEN 1 ELSE 0 END) AS choPhat,
            SUM(CASE WHEN trangThaiPhat = 'Đã phát một phần' THEN 1 ELSE 0 END) AS phatMotPhan,
            SUM(CASE WHEN trangThaiPhat = 'Đã phát đủ' AND DATE(updatedAt) = CURDATE() THEN 1 ELSE 0 END) AS daPhatDuHomNay,
            SUM(CASE WHEN trangThaiPhat = 'Hủy' AND DATE(COALESCE(cancelledAt, updatedAt)) = CURDATE() THEN 1 ELSE 0 END) AS daHuyHomNay
        FROM donthuoc
    ";
    $statsRes = $conn->query($statsSql);
    $statsRow = $statsRes ? ($statsRes->fetch_assoc() ?: []) : [];

    $lowStockRes = $conn->query("SELECT COUNT(*) AS lowCount FROM thuoc WHERE soLuongTon <= nguongCanhBao");
    $lowStockRow = $lowStockRes ? ($lowStockRes->fetch_assoc() ?: []) : [];

    $where = " WHERE 1=1 ";
    $types = '';
    $params = [];

    if ($tab === 'pending') {
        $where .= " AND dt.trangThaiPhat IN ('Chờ phát', 'Đã phát một phần') ";
    } elseif ($tab === 'completed') {
        $where .= " AND dt.trangThaiPhat = 'Đã phát đủ' ";
    } else {
        $where .= " AND dt.trangThaiPhat = 'Hủy' ";
    }

    if ($dateParam !== '') {
        if ($tab === 'cancelled') {
            $where .= " AND DATE(COALESCE(dt.cancelledAt, dt.ngayKeDon)) = ? ";
        } elseif ($tab === 'completed') {
            $where .= " AND DATE(COALESCE(dt.updatedAt, dt.ngayKeDon)) = ? ";
        } else {
            $where .= " AND DATE(dt.ngayKeDon) = ? ";
        }
        $types .= 's';
        $params[] = $dateParam;
    }

    if ($search !== '') {
        $where .= " AND (bn.tenBenhNhan LIKE ? OR bn.maBenhNhan LIKE ? OR nd.soDienThoai LIKE ? OR bs.tenBacSi LIKE ? OR CAST(dt.maDonThuoc AS CHAR) LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'sssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $orderBy = $tab === 'pending'
        ? " ORDER BY FIELD(dt.trangThaiPhat, 'Đã phát một phần', 'Chờ phát'), dt.ngayKeDon ASC, dt.maDonThuoc ASC "
        : " ORDER BY dt.updatedAt DESC, dt.maDonThuoc DESC ";

    $sql = "
        SELECT
            dt.maDonThuoc,
            dt.maLichKham,
            dt.chuanDoan,
            dt.loiDanBacSi,
            dt.ngayKeDon,
            dt.tongTienThuoc,
            dt.trangThaiPhat,
            dt.lyDoHuy,
            dt.cancelledAt,
            dt.updatedAt,
            lk.ngayKham,
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            bs.maBacSi,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            hs.maHoSo,
            nvCancel.maNhanVien AS maNhanVienHuy,
            nvCancel.tenNhanVien AS tenNhanVienHuy
        FROM donthuoc dt
        LEFT JOIN lichkham lk ON dt.maLichKham = lk.maLichKham
        LEFT JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        LEFT JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        LEFT JOIN hosobenhan hs ON dt.maLichKham = hs.maLichKham AND hs.isDeleted = 0
        LEFT JOIN nhanvien nvCancel ON dt.cancelledBy = nvCancel.nguoiDungId
        $where
        $orderBy
        LIMIT 150
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn danh sách đơn thuốc.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $rxIds = array_map(static fn(array $r): int => (int)$r['maDonThuoc'], $rows);
    $itemsByRx = [];
    $vouchersByRx = [];

    if (!empty($rxIds)) {
        $placeholders = implode(',', array_fill(0, count($rxIds), '?'));
        $idTypes = str_repeat('i', count($rxIds));

        $itemSql = "
            SELECT
                ct.id AS chiTietDonThuocId,
                ct.maDonThuoc,
                ct.maThuoc,
                ct.soLuong AS soLuongKe,
                ct.soLuongDaPhat,
                ct.lieuDung,
                t.tenThuoc,
                t.donViTinh,
                t.soLuongTon,
                t.nguongCanhBao,
                t.giaTien,
                t.cachDungMacDinh,
                t.hanSuDung
            FROM chitietdonthuoc ct
            JOIN thuoc t ON ct.maThuoc = t.maThuoc
            WHERE ct.maDonThuoc IN ($placeholders)
            ORDER BY ct.maDonThuoc ASC, ct.id ASC
        ";
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param($idTypes, ...$rxIds);
        $itemStmt->execute();
        $itemRows = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();

        foreach ($itemRows as $it) {
            $rxId = (int)$it['maDonThuoc'];
            $soLuongKe = (int)$it['soLuongKe'];
            $soLuongDaPhat = (int)$it['soLuongDaPhat'];
            $soLuongConLai = max(0, $soLuongKe - $soLuongDaPhat);
            $soLuongTon = (int)$it['soLuongTon'];
            $nguong = (int)$it['nguongCanhBao'];

            $itemsByRx[$rxId][] = [
                'chiTietDonThuocId' => (int)$it['chiTietDonThuocId'],
                'maThuoc' => (int)$it['maThuoc'],
                'tenThuoc' => $it['tenThuoc'],
                'donViTinh' => $it['donViTinh'] ?? '',
                'soLuongKe' => $soLuongKe,
                'soLuongDaPhat' => $soLuongDaPhat,
                'soLuongConLai' => $soLuongConLai,
                'soLuongTon' => $soLuongTon,
                'nguongCanhBao' => $nguong,
                'giaTien' => (float)$it['giaTien'],
                'lieuDung' => $it['lieuDung'] ?? '',
                'cachDungMacDinh' => $it['cachDungMacDinh'] ?? '',
                'hanSuDung' => $it['hanSuDung'],
                'isOutOfStock' => $soLuongConLai > 0 && $soLuongTon <= 0,
                'isLowStock' => $soLuongTon <= $nguong,
                'isShortageForRemaining' => $soLuongConLai > 0 && $soLuongTon < $soLuongConLai,
            ];
        }

        $voucherSql = "
            SELECT
                p.maPhieuPhat,
                p.maPhieuCode,
                p.maDonThuoc,
                p.maDuocSi,
                p.loaiPhat,
                p.trangThai,
                p.tongTienPhat,
                p.huongDanSuDung,
                p.ghiChu,
                p.thoiGianPhat,
                nv.tenNhanVien AS tenDuocSi
            FROM phieu_phat_thuoc p
            LEFT JOIN nhanvien nv ON p.maDuocSi = nv.maNhanVien
            WHERE p.maDonThuoc IN ($placeholders)
            ORDER BY p.thoiGianPhat DESC, p.maPhieuPhat DESC
        ";
        $vStmt = $conn->prepare($voucherSql);
        $vStmt->bind_param($idTypes, ...$rxIds);
        $vStmt->execute();
        $vRows = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $vStmt->close();

        $voucherIds = array_map(static fn(array $v): int => (int)$v['maPhieuPhat'], $vRows);
        $voucherDetails = [];
        if (!empty($voucherIds)) {
            $vPh = implode(',', array_fill(0, count($voucherIds), '?'));
            $vTypes = str_repeat('i', count($voucherIds));
            $vdStmt = $conn->prepare("
                SELECT
                    pc.maPhieuPhat,
                    pc.chiTietDonThuocId,
                    pc.maThuoc,
                    pc.soLuongKe,
                    pc.soLuongThucPhat,
                    pc.donGia,
                    pc.thanhTien,
                    pc.tonTruocPhat,
                    pc.tonSauPhat,
                    t.tenThuoc,
                    t.donViTinh
                FROM phieu_phat_thuoc_chitiet pc
                JOIN thuoc t ON pc.maThuoc = t.maThuoc
                WHERE pc.maPhieuPhat IN ($vPh)
                ORDER BY pc.id ASC
            ");
            $vdStmt->bind_param($vTypes, ...$voucherIds);
            $vdStmt->execute();
            $vdRows = $vdStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $vdStmt->close();

            foreach ($vdRows as $vd) {
                $vpId = (int)$vd['maPhieuPhat'];
                $voucherDetails[$vpId][] = [
                    'chiTietDonThuocId' => (int)$vd['chiTietDonThuocId'],
                    'maThuoc' => (int)$vd['maThuoc'],
                    'tenThuoc' => $vd['tenThuoc'],
                    'donViTinh' => $vd['donViTinh'] ?? '',
                    'soLuongKe' => (int)$vd['soLuongKe'],
                    'soLuongThucPhat' => (int)$vd['soLuongThucPhat'],
                    'donGia' => (float)$vd['donGia'],
                    'thanhTien' => (float)$vd['thanhTien'],
                    'tonTruocPhat' => (int)$vd['tonTruocPhat'],
                    'tonSauPhat' => (int)$vd['tonSauPhat'],
                ];
            }
        }

        foreach ($vRows as $v) {
            $rxId = (int)$v['maDonThuoc'];
            $vpId = (int)$v['maPhieuPhat'];
            $vouchersByRx[$rxId][] = [
                'maPhieuPhat' => $vpId,
                'maPhieuCode' => $v['maPhieuCode'],
                'maDuocSi' => $v['maDuocSi'],
                'tenDuocSi' => $v['tenDuocSi'] ?: $v['maDuocSi'],
                'loaiPhat' => $v['loaiPhat'],
                'trangThai' => $v['trangThai'],
                'tongTienPhat' => (float)$v['tongTienPhat'],
                'huongDanSuDung' => $v['huongDanSuDung'] ?? '',
                'ghiChu' => $v['ghiChu'] ?? '',
                'thoiGianPhat' => $v['thoiGianPhat'],
                'chiTiet' => $voucherDetails[$vpId] ?? [],
            ];
        }
    }

    $items = array_map(static function (array $r) use ($itemsByRx, $vouchersByRx): array {
        $rxId = (int)$r['maDonThuoc'];
        $rxItems = $itemsByRx[$rxId] ?? [];
        $tongKe = 0;
        $tongDaPhat = 0;
        $hasShortage = false;
        foreach ($rxItems as $it) {
            $tongKe += $it['soLuongKe'];
            $tongDaPhat += $it['soLuongDaPhat'];
            if ($it['isShortageForRemaining']) {
                $hasShortage = true;
            }
        }

        return [
            'maDonThuoc' => $rxId,
            'maLichKham' => $r['maLichKham'] !== null ? (int)$r['maLichKham'] : null,
            'maHoSo' => $r['maHoSo'] !== null ? (string)$r['maHoSo'] : null,
            'chuanDoan' => $r['chuanDoan'] ?? '',
            'loiDanBacSi' => $r['loiDanBacSi'] ?? '',
            'ngayKeDon' => $r['ngayKeDon'],
            'ngayKham' => $r['ngayKham'],
            'tongTienThuoc' => (float)($r['tongTienThuoc'] ?? 0),
            'trangThaiPhat' => $r['trangThaiPhat'],
            'lyDoHuy' => $r['lyDoHuy'] ?? '',
            'cancelledAt' => $r['cancelledAt'],
            'tenNhanVienHuy' => $r['tenNhanVienHuy'] ?: ($r['maNhanVienHuy'] ?? ''),
            'updatedAt' => $r['updatedAt'],
            'maBenhNhan' => $r['maBenhNhan'] ?? '',
            'tenBenhNhan' => $r['tenBenhNhan'] ?? 'Bệnh nhân',
            'ngaySinh' => $r['ngaySinh'],
            'gioiTinh' => $r['gioiTinh'],
            'soTheBHYT' => $r['soTheBHYT'],
            'soDienThoai' => $r['soDienThoai'] ?? '',
            'maBacSi' => $r['maBacSi'] ?? '',
            'tenBacSi' => $r['tenBacSi'] ?? '',
            'tenChuyenKhoa' => $r['tenChuyenKhoa'] ?: 'Đa khoa',
            'tongSoLuongKe' => $tongKe,
            'tongSoLuongDaPhat' => $tongDaPhat,
            'tongSoLuongConLai' => max(0, $tongKe - $tongDaPhat),
            'hasShortage' => $hasShortage,
            'chiTietThuoc' => $rxItems,
            'lichSuPhat' => $vouchersByRx[$rxId] ?? [],
        ];
    }, $rows);

    api_success([
        'tab' => $tab,
        'date' => $dateParam,
        'stats' => [
            'choPhat' => (int)($statsRow['choPhat'] ?? 0),
            'phatMotPhan' => (int)($statsRow['phatMotPhan'] ?? 0),
            'daPhatDuHomNay' => (int)($statsRow['daPhatDuHomNay'] ?? 0),
            'daHuyHomNay' => (int)($statsRow['daHuyHomNay'] ?? 0),
            'thuocSapHet' => (int)($lowStockRow['lowCount'] ?? 0),
        ],
        'items' => $items,
    ], 'Tải hàng đợi phát thuốc thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải danh sách đơn thuốc tại quầy dược.');
}
