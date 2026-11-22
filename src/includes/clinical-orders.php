<?php
/**
 * Helper dùng chung cho chỉ định, hàng đợi và trạng thái cận lâm sàng (Task 5+).
 */

require_once __DIR__ . '/clinical-state-contract.php';

function clinical_fetch_service_catalog(mysqli $conn, bool $activeOnly = true, string $nhom = '', string $search = ''): array
{
    $where = [];
    $types = '';
    $params = [];

    if ($activeOnly) {
        $where[] = 'isActive = 1';
    }
    if ($nhom !== '' && isset(CLINICAL_SERVICE_GROUPS[$nhom])) {
        $where[] = 'nhomDichVu = ?';
        $types .= 's';
        $params[] = $nhom;
    }
    if ($search !== '') {
        $where[] = '(tenDichVu LIKE ? OR maCode LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "
        SELECT
            maDichVu,
            maCode,
            tenDichVu,
            nhomDichVu,
            tenNhom,
            quyenThucHien,
            donViTinh,
            gia,
            thoiGianDuKienPhut,
            chuanBiTruocKhiLam,
            mauKetQuaMacDinh,
            moTa,
            isActive
        FROM dichvu_can_lam_sang
        $whereSql
        ORDER BY FIELD(nhomDichVu, 'xet-nghiem', 'chan-doan-hinh-anh', 'noi-soi'), tenDichVu ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn danh mục dịch vụ CLS');
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $r): array {
        return [
            'maDichVu' => (int)$r['maDichVu'],
            'maCode' => $r['maCode'],
            'tenDichVu' => $r['tenDichVu'],
            'nhomDichVu' => $r['nhomDichVu'],
            'tenNhom' => $r['tenNhom'],
            'quyenThucHien' => $r['quyenThucHien'],
            'donViTinh' => $r['donViTinh'],
            'gia' => (float)$r['gia'],
            'thoiGianDuKienPhut' => (int)$r['thoiGianDuKienPhut'],
            'chuanBiTruocKhiLam' => $r['chuanBiTruocKhiLam'] ?? '',
            'moTa' => $r['moTa'] ?? '',
            'isActive' => (bool)$r['isActive'],
        ];
    }, $rows);
}

function clinical_next_service_queue_number(mysqli $conn, string $nhomDichVu, string $ngay): int
{
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(soThuTu), 0) AS maxStt
        FROM hangdoi_dichvu
        WHERE nhomDichVu = ? AND ngay = ?
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new RuntimeException('Không thể khóa hàng đợi dịch vụ CLS');
    }
    $stmt->bind_param('ss', $nhomDichVu, $ngay);
    $stmt->execute();
    $maxStt = (int)($stmt->get_result()->fetch_assoc()['maxStt'] ?? 0);
    $stmt->close();

    return $maxStt + 1;
}

function clinical_append_order_history(
    mysqli $conn,
    int $maChiTietChiDinh,
    string $hanhDong,
    ?string $trangThaiCu,
    string $trangThaiMoi,
    ?int $actorUserId = null,
    ?string $actorMaNhanVien = null,
    ?string $actorMaBacSi = null,
    ?string $ghiChu = null,
    ?array $snapshot = null,
    ?int $maKetQua = null,
    int $phienBan = 1
): void {
    $snapshotJson = $snapshot !== null ? json_encode($snapshot, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare("
        INSERT INTO ket_qua_lich_su (
            maKetQua, maChiTietChiDinh, phienBan, hanhDong,
            trangThaiCu, trangThaiMoi, duLieuSnapshot, ghiChu,
            actorUserId, actorMaNhanVien, actorMaBacSi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Không thể ghi lịch sử chỉ định/kết quả CLS: ' . $conn->error);
    }

    $stmt->bind_param(
        'iiisssssiss',
        $maKetQua,
        $maChiTietChiDinh,
        $phienBan,
        $hanhDong,
        $trangThaiCu,
        $trangThaiMoi,
        $snapshotJson,
        $ghiChu,
        $actorUserId,
        $actorMaNhanVien,
        $actorMaBacSi
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('Không thể lưu lịch sử thao tác CLS: ' . $stmt->error);
    }
    $stmt->close();
}

function clinical_recompute_record_workflow_status(mysqli $conn, string $maHoSo): string
{
    $recStmt = $conn->prepare("
        SELECT trangThai, trangThaiQuyTrinh
        FROM hosobenhan
        WHERE maHoSo = ? AND isDeleted = 0
        LIMIT 1
    ");
    $recStmt->bind_param('s', $maHoSo);
    $recStmt->execute();
    $rec = $recStmt->get_result()->fetch_assoc();
    $recStmt->close();

    if (!$rec) {
        return CLINICAL_RECORD_STATUS_EXAMINING;
    }

    $currentWorkflow = (string)($rec['trangThaiQuyTrinh'] ?? CLINICAL_RECORD_STATUS_EXAMINING);
    if ($currentWorkflow === CLINICAL_RECORD_STATUS_COMPLETED || $currentWorkflow === CLINICAL_RECORD_STATUS_CANCELLED) {
        return $currentWorkflow;
    }

    $stmt = $conn->prepare("
        SELECT trangThai
        FROM chi_dinh_chi_tiet
        WHERE maHoSo = ? AND trangThai <> 'Hủy'
    ");
    $stmt->bind_param('s', $maHoSo);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        $nextStatus = CLINICAL_RECORD_STATUS_EXAMINING;
    } else {
        $allReadyForConclusion = true;
        $hasProgress = false;
        foreach ($rows as $row) {
            $st = (string)$row['trangThai'];
            if (!in_array($st, [CLINICAL_ORDER_STATUS_RESULTED, CLINICAL_ORDER_STATUS_APPROVED], true)) {
                $allReadyForConclusion = false;
            }
            if (in_array($st, [CLINICAL_ORDER_STATUS_IN_PROGRESS, CLINICAL_ORDER_STATUS_RESULTED, CLINICAL_ORDER_STATUS_APPROVED], true)) {
                $hasProgress = true;
            }
        }
        if ($allReadyForConclusion) {
            $nextStatus = CLINICAL_RECORD_STATUS_WAITING_CONCLUSION;
        } elseif ($hasProgress) {
            $nextStatus = CLINICAL_RECORD_STATUS_WAITING_RESULT;
        } else {
            $nextStatus = CLINICAL_RECORD_STATUS_WAITING_CLS;
        }
    }

    if ($nextStatus !== $currentWorkflow) {
        $upStmt = $conn->prepare("UPDATE hosobenhan SET trangThaiQuyTrinh = ? WHERE maHoSo = ? AND isDeleted = 0");
        $upStmt->bind_param('ss', $nextStatus, $maHoSo);
        $upStmt->execute();
        $upStmt->close();
    }

    return $nextStatus;
}

function clinical_sync_parent_order_status(mysqli $conn, int $maChiDinh): void
{
    $stmt = $conn->prepare("SELECT trangThai FROM chi_dinh_chi_tiet WHERE maChiDinh = ?");
    $stmt->bind_param('i', $maChiDinh);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        return;
    }

    $statuses = array_map(static fn($r) => (string)$r['trangThai'], $rows);
    $nonCancelled = array_values(array_filter($statuses, static fn($s) => $s !== CLINICAL_ORDER_STATUS_CANCELLED));

    if (empty($nonCancelled)) {
        $parentStatus = CLINICAL_ORDER_STATUS_CANCELLED;
    } elseif (count(array_filter($nonCancelled, static fn($s) => $s !== CLINICAL_ORDER_STATUS_APPROVED)) === 0) {
        $parentStatus = CLINICAL_ORDER_STATUS_APPROVED;
    } elseif (in_array(CLINICAL_ORDER_STATUS_RESULTED, $nonCancelled, true)) {
        $parentStatus = CLINICAL_ORDER_STATUS_RESULTED;
    } elseif (in_array(CLINICAL_ORDER_STATUS_IN_PROGRESS, $nonCancelled, true)) {
        $parentStatus = CLINICAL_ORDER_STATUS_IN_PROGRESS;
    } elseif (in_array(CLINICAL_ORDER_STATUS_WAITING, $nonCancelled, true)) {
        $parentStatus = CLINICAL_ORDER_STATUS_WAITING;
    } else {
        $parentStatus = CLINICAL_ORDER_STATUS_CREATED;
    }

    $up = $conn->prepare("UPDATE chi_dinh_can_lam_sang SET trangThai = ? WHERE maChiDinh = ?");
    $up->bind_param('si', $parentStatus, $maChiDinh);
    $up->execute();
    $up->close();
}

function clinical_fetch_orders_for_record(mysqli $conn, string $maHoSo): array
{
    $sql = "
        SELECT
            cd.maChiDinh,
            cd.maPhieuChiDinh,
            cd.maHoSo,
            cd.maBacSiChiDinh,
            bsCd.tenBacSi AS tenBacSiChiDinh,
            cd.doUuTien AS headerDoUuTien,
            cd.ghiChuLamSang,
            cd.trangThai AS headerTrangThai,
            cd.createdAt AS ngayChiDinh,
            ct.maChiTietChiDinh,
            ct.maDichVu,
            dv.maCode,
            dv.tenDichVu,
            dv.nhomDichVu,
            dv.tenNhom,
            dv.donViTinh,
            ct.donGia,
            ct.batBuoc,
            ct.doUuTien,
            ct.ghiChu,
            ct.trangThai,
            ct.maNhanVienXuLy,
            nvXl.tenNhanVien AS tenNhanVienXuLy,
            ct.maBacSiDuyet,
            bsDuyet.tenBacSi AS tenBacSiDuyet,
            ct.thoiGianDuyet,
            ct.lyDoHuy,
            ct.cancelledAt,
            hq.maHangDoiDichVu,
            hq.ngay AS ngayHangDoi,
            hq.soThuTu AS soThuTuHangDoi,
            hq.trangThai AS trangThaiHangDoi,
            kq.maKetQua,
            kq.trangThai AS trangThaiKetQua,
            kq.ketLuan AS ketLuanKetQua,
            kq.nhanXet AS nhanXetKetQua,
            kq.deNghi AS deNghiKetQua,
            kq.chiSoKetQua,
            kq.tepDinhKem,
            kq.thoiGianTraKetQua,
            kq.thoiGianDuyet AS thoiGianDuyetKetQua,
            kq.lyDoYeuCauLamLai
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN dichvu_can_lam_sang dv ON ct.maDichVu = dv.maDichVu
        LEFT JOIN bacsi bsCd ON cd.maBacSiChiDinh = bsCd.maBacSi
        LEFT JOIN bacsi bsDuyet ON ct.maBacSiDuyet = bsDuyet.maBacSi
        LEFT JOIN nhanvien nvXl ON ct.maNhanVienXuLy = nvXl.maNhanVien
        LEFT JOIN hangdoi_dichvu hq ON hq.maChiTietChiDinh = ct.maChiTietChiDinh
        LEFT JOIN ket_qua_can_lam_sang kq ON kq.maChiTietChiDinh = ct.maChiTietChiDinh
        WHERE ct.maHoSo = ?
        ORDER BY cd.createdAt DESC, ct.maChiTietChiDinh ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn danh sách chỉ định CLS');
    }
    $stmt->bind_param('s', $maHoSo);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $r): array {
        $chiSo = null;
        if (!empty($r['chiSoKetQua'])) {
            $decoded = json_decode((string)$r['chiSoKetQua'], true);
            $chiSo = is_array($decoded) ? $decoded : null;
        }
        $attachments = [];
        if (!empty($r['tepDinhKem'])) {
            $decoded = json_decode((string)$r['tepDinhKem'], true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        return [
            'maChiDinh' => (int)$r['maChiDinh'],
            'maPhieuChiDinh' => $r['maPhieuChiDinh'],
            'maChiTietChiDinh' => (int)$r['maChiTietChiDinh'],
            'maDichVu' => (int)$r['maDichVu'],
            'maCode' => $r['maCode'],
            'tenDichVu' => $r['tenDichVu'],
            'nhomDichVu' => $r['nhomDichVu'],
            'tenNhom' => $r['tenNhom'],
            'donViTinh' => $r['donViTinh'],
            'donGia' => (float)$r['donGia'],
            'batBuoc' => (bool)$r['batBuoc'],
            'doUuTien' => $r['doUuTien'],
            'ghiChu' => $r['ghiChu'] ?? '',
            'ghiChuLamSang' => $r['ghiChuLamSang'] ?? '',
            'trangThai' => $r['trangThai'],
            'maBacSiChiDinh' => $r['maBacSiChiDinh'],
            'tenBacSiChiDinh' => $r['tenBacSiChiDinh'] ?: $r['maBacSiChiDinh'],
            'ngayChiDinh' => $r['ngayChiDinh'],
            'maNhanVienXuLy' => $r['maNhanVienXuLy'],
            'tenNhanVienXuLy' => $r['tenNhanVienXuLy'] ?: $r['maNhanVienXuLy'],
            'maBacSiDuyet' => $r['maBacSiDuyet'],
            'tenBacSiDuyet' => $r['tenBacSiDuyet'] ?: $r['maBacSiDuyet'],
            'thoiGianDuyet' => $r['thoiGianDuyet'],
            'lyDoHuy' => $r['lyDoHuy'] ?? '',
            'cancelledAt' => $r['cancelledAt'],
            'hangDoi' => $r['maHangDoiDichVu'] !== null ? [
                'maHangDoiDichVu' => (int)$r['maHangDoiDichVu'],
                'ngay' => $r['ngayHangDoi'],
                'soThuTu' => (int)$r['soThuTuHangDoi'],
                'trangThai' => $r['trangThaiHangDoi'],
            ] : null,
            'ketQua' => $r['maKetQua'] !== null ? [
                'maKetQua' => (int)$r['maKetQua'],
                'trangThai' => $r['trangThaiKetQua'],
                'chiSoKetQua' => $chiSo,
                'ketLuan' => $r['ketLuanKetQua'] ?? '',
                'nhanXet' => $r['nhanXetKetQua'] ?? '',
                'deNghi' => $r['deNghiKetQua'] ?? '',
                'tepDinhKem' => $attachments,
                'thoiGianTraKetQua' => $r['thoiGianTraKetQua'],
                'thoiGianDuyet' => $r['thoiGianDuyetKetQua'],
                'lyDoYeuCauLamLai' => $r['lyDoYeuCauLamLai'] ?? '',
            ] : null,
        ];
    }, $rows);
}
