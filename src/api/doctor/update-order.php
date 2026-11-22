<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/clinical-orders.php';

api_require_method('POST');
require_role('bacsi');

$input = api_json_input(true);
$maChiTietChiDinh = isset($input['maChiTietChiDinh']) ? (int)$input['maChiTietChiDinh'] : 0;

if ($maChiTietChiDinh <= 0) {
    api_error('Thiếu mã chi tiết chỉ định cần chỉnh sửa.', 400);
}

$allowedPriorities = ['Bình thường', 'Ưu tiên', 'Khẩn'];
$transactionStarted = false;

try {
    $docStmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ? LIMIT 1");
    $docStmt->bind_param('i', $_SESSION['id']);
    $docStmt->execute();
    $maBacSi = $docStmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $docStmt->close();

    if (!$maBacSi) {
        api_error('Không tìm thấy thông tin bác sĩ.', 403);
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        SELECT
            ct.maChiTietChiDinh,
            ct.maChiDinh,
            ct.maHoSo,
            ct.doUuTien,
            ct.batBuoc,
            ct.ghiChu,
            ct.trangThai,
            cd.maBacSiChiDinh,
            hs.maBacSi AS maBacSiHoSo,
            hs.trangThai AS trangThaiHoSo
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN hosobenhan hs ON ct.maHoSo = hs.maHoSo
        WHERE ct.maChiTietChiDinh = ? AND hs.isDeleted = 0
        FOR UPDATE
    ");
    $stmt->bind_param('i', $maChiTietChiDinh);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy chỉ định cận lâm sàng.', 404);
    }

    if ((string)$row['maBacSiChiDinh'] !== (string)$maBacSi && (string)$row['maBacSiHoSo'] !== (string)$maBacSi) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ bác sĩ chỉ định hoặc bác sĩ phụ trách hồ sơ mới có quyền chỉnh sửa.', 403);
    }

    if (($row['trangThaiHoSo'] ?? '') === CLINICAL_RECORD_LEGACY_COMPLETED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không thể chỉnh chỉ định của hồ sơ đã hoàn tất.', 409);
    }

    if (!in_array($row['trangThai'], [CLINICAL_ORDER_STATUS_CREATED, CLINICAL_ORDER_STATUS_WAITING], true)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ được chỉnh sửa chỉ định khi chưa bắt đầu thực hiện (trạng thái hiện tại: ' . $row['trangThai'] . ').', 409);
    }

    $newPriority = array_key_exists('doUuTien', $input) ? trim((string)$input['doUuTien']) : $row['doUuTien'];
    if (!in_array($newPriority, $allowedPriorities, true)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Độ ưu tiên không hợp lệ.', 400);
    }

    $newBatBuoc = array_key_exists('batBuoc', $input) ? ((bool)$input['batBuoc'] ? 1 : 0) : (int)$row['batBuoc'];
    $newGhiChu = array_key_exists('ghiChu', $input) ? trim((string)$input['ghiChu']) : (string)($row['ghiChu'] ?? '');
    $ghiChuParam = $newGhiChu !== '' ? $newGhiChu : null;

    $upDetail = $conn->prepare("
        UPDATE chi_dinh_chi_tiet
        SET doUuTien = ?, batBuoc = ?, ghiChu = ?
        WHERE maChiTietChiDinh = ?
    ");
    $upDetail->bind_param('sisi', $newPriority, $newBatBuoc, $ghiChuParam, $maChiTietChiDinh);
    if (!$upDetail->execute()) {
        throw new RuntimeException('Không thể cập nhật chi tiết chỉ định: ' . $upDetail->error);
    }
    $upDetail->close();

    $upQueue = $conn->prepare("
        UPDATE hangdoi_dichvu
        SET doUuTien = ?, ghiChu = COALESCE(?, ghiChu)
        WHERE maChiTietChiDinh = ? AND trangThai IN ('Đang chờ', 'Bỏ lỡ')
    ");
    $upQueue->bind_param('ssi', $newPriority, $ghiChuParam, $maChiTietChiDinh);
    $upQueue->execute();
    $upQueue->close();

    $userId = (int)$_SESSION['id'];
    clinical_append_order_history(
        $conn,
        $maChiTietChiDinh,
        'sua_chi_dinh',
        $row['trangThai'],
        $row['trangThai'],
        $userId,
        null,
        $maBacSi,
        'Bác sĩ chỉnh sửa thông tin chỉ định trước khi thực hiện.',
        [
            'doUuTien' => $newPriority,
            'batBuoc' => (bool)$newBatBuoc,
            'ghiChu' => $newGhiChu,
        ]
    );

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maChiTietChiDinh' => $maChiTietChiDinh,
        'doUuTien' => $newPriority,
        'batBuoc' => (bool)$newBatBuoc,
        'ghiChu' => $newGhiChu,
    ], 'Cập nhật chỉ định cận lâm sàng thành công.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể cập nhật chỉ định cận lâm sàng.');
}
