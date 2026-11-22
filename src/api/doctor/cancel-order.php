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
$lyDoHuy = trim((string)($input['lyDoHuy'] ?? ''));

if ($maChiTietChiDinh <= 0) {
    api_error('Thiếu mã chi tiết chỉ định cần hủy.', 400);
}
if ($lyDoHuy === '') {
    api_error('Vui lòng nhập lý do hủy chỉ định.', 422);
}

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
        api_error('Chỉ bác sĩ chỉ định hoặc bác sĩ phụ trách hồ sơ mới có quyền hủy.', 403);
    }

    if (($row['trangThaiHoSo'] ?? '') === CLINICAL_RECORD_LEGACY_COMPLETED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không thể hủy chỉ định của hồ sơ đã hoàn tất.', 409);
    }

    $currentStatus = (string)$row['trangThai'];
    if (!clinical_can_transition(clinical_order_transitions(), $currentStatus, CLINICAL_ORDER_STATUS_CANCELLED)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không thể hủy chỉ định ở trạng thái "' . $currentStatus . '".', 409);
    }

    $userId = (int)$_SESSION['id'];
    $cancelStatus = CLINICAL_ORDER_STATUS_CANCELLED;

    $upDetail = $conn->prepare("
        UPDATE chi_dinh_chi_tiet
        SET trangThai = ?, lyDoHuy = ?, cancelledBy = ?, cancelledAt = NOW()
        WHERE maChiTietChiDinh = ?
    ");
    $upDetail->bind_param('ssii', $cancelStatus, $lyDoHuy, $userId, $maChiTietChiDinh);
    if (!$upDetail->execute()) {
        throw new RuntimeException('Không thể cập nhật hủy chỉ định: ' . $upDetail->error);
    }
    $upDetail->close();

    $queueCancelStatus = CLINICAL_QUEUE_STATUS_CANCELLED;
    $upQueue = $conn->prepare("
        UPDATE hangdoi_dichvu
        SET trangThai = ?, ghiChu = CONCAT(COALESCE(ghiChu, ''), IF(COALESCE(ghiChu, '') = '', '', ' | '), '[Hủy]: ', ?)
        WHERE maChiTietChiDinh = ?
    ");
    $upQueue->bind_param('ssi', $queueCancelStatus, $lyDoHuy, $maChiTietChiDinh);
    $upQueue->execute();
    $upQueue->close();

    clinical_sync_parent_order_status($conn, (int)$row['maChiDinh']);
    $workflowStatus = clinical_recompute_record_workflow_status($conn, (string)$row['maHoSo']);

    clinical_append_order_history(
        $conn,
        $maChiTietChiDinh,
        'huy_chi_dinh',
        $currentStatus,
        CLINICAL_ORDER_STATUS_CANCELLED,
        $userId,
        null,
        $maBacSi,
        $lyDoHuy
    );

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maChiTietChiDinh' => $maChiTietChiDinh,
        'trangThai' => CLINICAL_ORDER_STATUS_CANCELLED,
        'lyDoHuy' => $lyDoHuy,
        'trangThaiQuyTrinh' => $workflowStatus,
    ], 'Đã hủy chỉ định cận lâm sàng và gỡ khỏi hàng đợi bộ phận.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể hủy chỉ định cận lâm sàng.');
}
