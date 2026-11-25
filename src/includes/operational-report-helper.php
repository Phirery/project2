<?php
/**
 * Operational Reporting & Cross-Workflow Audit Helper (Task 10).
 * Computes daily operational metrics:
 * - check-in counts & average clinical wait minutes
 * - paraclinical (CLS) volume & average CLS wait minutes
 * - pharmacy dispensed voucher counts
 * - cashier collected revenue
 * - low-stock medicines (`soLuongTon <= nguongCanhBao`)
 * - unified actor/timestamp audit trail across screening, CLS, pharmacy, cancellations, billing, and stock log
 */

function admin_build_operational_report(mysqli $conn, string $dateFrom, string $dateTo, int $auditLimit = 60): array
{
    $auditLimit = max(10, min(200, $auditLimit));

    // Daily check-in + average wait time from check-in to nurse transfer / doctor record open
    $checkinByDate = [];
    $ckStmt = $conn->prepare("
        SELECT
            hd.ngay AS ngay,
            COUNT(*) AS soLuotCheckIn,
            ROUND(AVG(
                CASE
                    WHEN COALESCE(sl.thoiGianChuyenBacSi, hs.ngayTao) IS NOT NULL
                         AND COALESCE(sl.thoiGianChuyenBacSi, hs.ngayTao) >= hd.thoiGianCheckIn
                    THEN TIMESTAMPDIFF(MINUTE, hd.thoiGianCheckIn, COALESCE(sl.thoiGianChuyenBacSi, hs.ngayTao))
                    ELSE NULL
                END
            ), 1) AS thoiGianChoKhamPhut
        FROM hangdoikham hd
        LEFT JOIN sang_loc_dieu_duong sl ON sl.maHangDoi = hd.maHangDoi
        LEFT JOIN hosobenhan hs ON hs.maLichKham = hd.maLichKham AND hs.isDeleted = 0
        WHERE hd.ngay BETWEEN ? AND ?
        GROUP BY hd.ngay
    ");
    if ($ckStmt) {
        $ckStmt->bind_param('ss', $dateFrom, $dateTo);
        $ckStmt->execute();
        foreach ($ckStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $checkinByDate[$r['ngay']] = $r;
        }
        $ckStmt->close();
    }

    // Daily CLS volume + average wait time from queue entry to start/completion
    $clsByDate = [];
    $clsStmt = $conn->prepare("
        SELECT
            hq.ngay AS ngay,
            COUNT(*) AS soLuotCLS,
            ROUND(AVG(
                CASE
                    WHEN COALESCE(hq.thoiGianBatDau, hq.thoiGianHoanThanh) IS NOT NULL
                         AND COALESCE(hq.thoiGianBatDau, hq.thoiGianHoanThanh) >= hq.thoiGianVaoHangDoi
                    THEN TIMESTAMPDIFF(MINUTE, hq.thoiGianVaoHangDoi, COALESCE(hq.thoiGianBatDau, hq.thoiGianHoanThanh))
                    ELSE NULL
                END
            ), 1) AS thoiGianChoClsPhut
        FROM hangdoi_dichvu hq
        WHERE hq.ngay BETWEEN ? AND ?
          AND hq.trangThai <> 'Hủy'
        GROUP BY hq.ngay
    ");
    if ($clsStmt) {
        $clsStmt->bind_param('ss', $dateFrom, $dateTo);
        $clsStmt->execute();
        foreach ($clsStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $clsByDate[$r['ngay']] = $r;
        }
        $clsStmt->close();
    }

    // Daily pharmacy dispensed vouchers
    $dispByDate = [];
    $dispStmt = $conn->prepare("
        SELECT
            DATE(thoiGianPhat) AS ngay,
            COUNT(*) AS soDonPhat,
            COALESCE(SUM(tongTienPhat), 0) AS tongTienPhat
        FROM phieu_phat_thuoc
        WHERE DATE(thoiGianPhat) BETWEEN ? AND ?
          AND trangThai = 'Hoàn thành'
        GROUP BY DATE(thoiGianPhat)
    ");
    if ($dispStmt) {
        $dispStmt->bind_param('ss', $dateFrom, $dateTo);
        $dispStmt->execute();
        foreach ($dispStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $dispByDate[$r['ngay']] = $r;
        }
        $dispStmt->close();
    }

    // Daily cashier revenue
    $revByDate = [];
    $revStmt = $conn->prepare("
        SELECT
            DATE(COALESCE(thoiGianThanhToan, ngayTao)) AS ngay,
            COUNT(*) AS soHoaDonDaThu,
            COALESCE(SUM(soTien), 0) AS doanhThuThuNgan
        FROM hoadon
        WHERE trangThai = 'Đã thanh toán'
          AND DATE(COALESCE(thoiGianThanhToan, ngayTao)) BETWEEN ? AND ?
        GROUP BY DATE(COALESCE(thoiGianThanhToan, ngayTao))
    ");
    if ($revStmt) {
        $revStmt->bind_param('ss', $dateFrom, $dateTo);
        $revStmt->execute();
        foreach ($revStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $revByDate[$r['ngay']] = $r;
        }
        $revStmt->close();
    }

    // Build contiguous date range DESC
    $dailyMetrics = [];
    $startDt = new DateTimeImmutable($dateFrom);
    $endDt = new DateTimeImmutable($dateTo);
    $cursor = $endDt;

    $totalCheckIn = 0;
    $totalCls = 0;
    $totalDispensed = 0;
    $totalRevenue = 0.0;
    $waitKhamSamples = [];
    $waitClsSamples = [];

    while ($cursor >= $startDt) {
        $dStr = $cursor->format('Y-m-d');
        $ck = $checkinByDate[$dStr] ?? null;
        $cl = $clsByDate[$dStr] ?? null;
        $dp = $dispByDate[$dStr] ?? null;
        $rv = $revByDate[$dStr] ?? null;

        $checkInCnt = (int)($ck['soLuotCheckIn'] ?? 0);
        $clsCnt = (int)($cl['soLuotCLS'] ?? 0);
        $dispCnt = (int)($dp['soDonPhat'] ?? 0);
        $revAmt = (float)($rv['doanhThuThuNgan'] ?? 0);
        $waitKham = isset($ck['thoiGianChoKhamPhut']) && $ck['thoiGianChoKhamPhut'] !== null ? (float)$ck['thoiGianChoKhamPhut'] : null;
        $waitCls = isset($cl['thoiGianChoClsPhut']) && $cl['thoiGianChoClsPhut'] !== null ? (float)$cl['thoiGianChoClsPhut'] : null;

        $totalCheckIn += $checkInCnt;
        $totalCls += $clsCnt;
        $totalDispensed += $dispCnt;
        $totalRevenue += $revAmt;
        if ($waitKham !== null) {
            $waitKhamSamples[] = $waitKham;
        }
        if ($waitCls !== null) {
            $waitClsSamples[] = $waitCls;
        }

        $dailyMetrics[] = [
            'ngay' => $dStr,
            'soLuotCheckIn' => $checkInCnt,
            'thoiGianChoKhamPhut' => $waitKham,
            'soLuotCLS' => $clsCnt,
            'thoiGianChoClsPhut' => $waitCls,
            'soDonPhat' => $dispCnt,
            'soHoaDonDaThu' => (int)($rv['soHoaDonDaThu'] ?? 0),
            'doanhThuThuNgan' => $revAmt,
        ];

        $cursor = $cursor->modify('-1 day');
    }

    // Low-stock medicines
    $lowStockStmt = $conn->query("
        SELECT
            maThuoc,
            tenThuoc,
            donViTinh,
            soLuongTon,
            nguongCanhBao,
            hanSuDung
        FROM thuoc
        WHERE soLuongTon <= nguongCanhBao
        ORDER BY (soLuongTon - nguongCanhBao) ASC, soLuongTon ASC, maThuoc ASC
    ");
    $lowStockMedicines = $lowStockStmt ? $lowStockStmt->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($lowStockMedicines as &$m) {
        $m['maThuoc'] = (int)$m['maThuoc'];
        $m['soLuongTon'] = (int)$m['soLuongTon'];
        $m['nguongCanhBao'] = (int)$m['nguongCanhBao'];
    }
    unset($m);

    // Unified Audit Trail across workflows
    $auditSql = "
        (
            SELECT
                COALESCE(sl.thoiGianChuyenBacSi, sl.thoiGianSangLoc) AS thoiGian,
                'Điều dưỡng sàng lọc' AS phanHe,
                CONCAT('HĐK #', sl.maHangDoi) AS maDoiTuong,
                sl.trangThai AS trangThai,
                COALESCE(nv.tenNhanVien, sl.maNhanVienSangLoc) AS nguoiThaoTac,
                CONCAT('BN ', sl.maBenhNhan, COALESCE(CONCAT(' • ', sl.canhBaoLamSang), '')) AS chiTiet
            FROM sang_loc_dieu_duong sl
            LEFT JOIN nhanvien nv ON sl.maNhanVienSangLoc = nv.maNhanVien
        )
        UNION ALL
        (
            SELECT
                ls.createdAt AS thoiGian,
                'Cận lâm sàng' AS phanHe,
                CONCAT('CTCLS #', ls.maChiTietChiDinh) AS maDoiTuong,
                CONCAT(COALESCE(ls.trangThaiCu, 'Mới'), ' -> ', ls.trangThaiMoi) AS trangThai,
                COALESCE(nv.tenNhanVien, bs.tenBacSi, ls.actorMaNhanVien, ls.actorMaBacSi, CONCAT('User #', ls.actorUserId), 'Hệ thống') AS nguoiThaoTac,
                COALESCE(ls.ghiChu, ls.hanhDong) AS chiTiet
            FROM ket_qua_lich_su ls
            LEFT JOIN nhanvien nv ON ls.actorMaNhanVien = nv.maNhanVien
            LEFT JOIN bacsi bs ON ls.actorMaBacSi = bs.maBacSi
        )
        UNION ALL
        (
            SELECT
                p.thoiGianPhat AS thoiGian,
                'Quầy dược' AS phanHe,
                p.maPhieuCode AS maDoiTuong,
                CONCAT('Phát ', p.loaiPhat, ' (Đơn #', p.maDonThuoc, ')') AS trangThai,
                COALESCE(nv.tenNhanVien, p.maDuocSi) AS nguoiThaoTac,
                CONCAT('BN ', p.maBenhNhan, ' • ', FORMAT(p.tongTienPhat, 0), 'đ') AS chiTiet
            FROM phieu_phat_thuoc p
            LEFT JOIN nhanvien nv ON p.maDuocSi = nv.maNhanVien
        )
        UNION ALL
        (
            SELECT
                dt.cancelledAt AS thoiGian,
                'Hủy đơn thuốc' AS phanHe,
                CONCAT('Đơn #', dt.maDonThuoc) AS maDoiTuong,
                'Hủy' AS trangThai,
                COALESCE(nv.tenNhanVien, nd.tenDangNhap, CONCAT('User #', dt.cancelledBy), 'Nhân viên') AS nguoiThaoTac,
                COALESCE(dt.lyDoHuy, 'Đã hủy tại quầy dược') AS chiTiet
            FROM donthuoc dt
            LEFT JOIN nhanvien nv ON dt.cancelledBy = nv.nguoiDungId
            LEFT JOIN nguoidung nd ON dt.cancelledBy = nd.id
            WHERE dt.trangThaiPhat = 'Hủy' AND dt.cancelledAt IS NOT NULL
        )
        UNION ALL
        (
            SELECT
                COALESCE(hd.thoiGianThanhToan, hd.ngayTao) AS thoiGian,
                'Thu ngân' AS phanHe,
                CONCAT('HĐ #', hd.maHoaDon) AS maDoiTuong,
                CONCAT(hd.trangThai, ' (', COALESCE(hd.phuongThuc, 'N/A'), ')') AS trangThai,
                COALESCE(nv.tenNhanVien, hd.maThuNgan, IF(hd.phuongThuc = 'VNPAY', 'Cổng VNPAY', 'Quầy thu ngân')) AS nguoiThaoTac,
                CONCAT('LK #', COALESCE(hd.maLichKham, 0), ' • ', FORMAT(COALESCE(hd.soTien, 0), 0), 'đ') AS chiTiet
            FROM hoadon hd
            LEFT JOIN nhanvien nv ON hd.maThuNgan = nv.maNhanVien
            WHERE hd.trangThai = 'Đã thanh toán'
        )
        UNION ALL
        (
            SELECT
                msl.createdAt AS thoiGian,
                'Tồn kho thuốc' AS phanHe,
                CONCAT('Thuốc #', msl.maThuoc) AS maDoiTuong,
                msl.actionType AS trangThai,
                'Hệ thống / Dược sĩ' AS nguoiThaoTac,
                CONCAT('Biến động: ', IF(msl.changeQty >= 0, '+', ''), msl.changeQty, ' → Tồn: ', msl.balanceAfter, COALESCE(CONCAT(' • ', msl.note), '')) AS chiTiet
            FROM medicine_stock_log msl
        )
        ORDER BY thoiGian DESC
        LIMIT ?
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditTrail = [];
    if ($auditStmt) {
        $auditStmt->bind_param('i', $auditLimit);
        $auditStmt->execute();
        $auditTrail = $auditStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $auditStmt->close();
    }

    return [
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'summary' => [
            'totalCheckIn' => $totalCheckIn,
            'totalCLS' => $totalCls,
            'totalDispensed' => $totalDispensed,
            'totalRevenue' => $totalRevenue,
            'avgWaitKhamPhut' => !empty($waitKhamSamples) ? round(array_sum($waitKhamSamples) / count($waitKhamSamples), 1) : null,
            'avgWaitClsPhut' => !empty($waitClsSamples) ? round(array_sum($waitClsSamples) / count($waitClsSamples), 1) : null,
            'lowStockCount' => count($lowStockMedicines),
        ],
        'dailyMetrics' => $dailyMetrics,
        'lowStockMedicines' => $lowStockMedicines,
        'auditTrail' => $auditTrail,
    ];
}
