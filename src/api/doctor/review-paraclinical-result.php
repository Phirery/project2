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
$action = strtolower(trim((string)($input['action'] ?? '')));
$reason = trim((string)($input['lyDoYeuCauLamLai'] ?? ($input['ghiChu'] ?? '')));

if ($maChiTietChiDinh <= 0 || !in_array($action, ['approve', 'redo'], true)) {
    api_error('Yêu cầu duyệt/xử lý kết quả cận lâm sàng không hợp lệ.', 400);
}
if ($action === 'redo' && $reason === '') {
    api_error('Vui lòng nhập lý do khi yêu cầu bộ phận cận lâm sàng làm lại.', 422);
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
            ct.trangThai AS orderStatus,
            cd.maBacSiChiDinh,
            hs.maBacSi AS maBacSiHoSo,
            hs.trangThai AS recordLegacyStatus,
            hs.isDeleted,
            kq.maKetQua,
            kq.trangThai AS resultStatus,
            kq.phienBan
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN hosobenhan hs ON ct.maHoSo = hs.maHoSo
        LEFT JOIN ket_qua_can_lam_sang kq ON kq.maChiTietChiDinh = ct.maChiTietChiDinh
        WHERE ct.maChiTietChiDinh = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $maChiTietChiDinh);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)$row['isDeleted'] === 1) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy chỉ định cận lâm sàng.', 404);
    }

    if ((string)$row['maBacSiChiDinh'] !== (string)$maBacSi && (string)$row['maBacSiHoSo'] !== (string)$maBacSi) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ bác sĩ chỉ định hoặc bác sĩ phụ trách hồ sơ mới có quyền duyệt kết quả.', 403);
    }

    if (($row['recordLegacyStatus'] ?? '') === CLINICAL_RECORD_LEGACY_COMPLETED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Hồ sơ bệnh án đã hoàn tất, không thể thay đổi trạng thái duyệt kết quả.', 409);
    }

    if (empty($row['maKetQua'])) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ định này chưa có kết quả được trả.', 409);
    }

    $maKetQua = (int)$row['maKetQua'];
    $currentResultStatus = (string)$row['resultStatus'];
    $currentOrderStatus = (string)$row['orderStatus'];
    $targetResultStatus = $action === 'approve'
        ? CLINICAL_RESULT_STATUS_APPROVED
        : CLINICAL_RESULT_STATUS_REDO_REQUESTED;
    $targetOrderStatus = $action === 'approve'
        ? CLINICAL_ORDER_STATUS_APPROVED
        : CLINICAL_ORDER_STATUS_IN_PROGRESS;

    if (!clinical_can_transition(clinical_result_transitions(), $currentResultStatus, $targetResultStatus)
        || !clinical_can_transition(clinical_order_transitions(), $currentOrderStatus, $targetOrderStatus)
    ) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Trạng thái hiện tại không hợp lệ để duyệt hoặc yêu cầu làm lại (' . $currentResultStatus . ').', 409);
    }

    $userId = (int)$_SESSION['id'];
    $phienBan = (int)($row['phienBan'] ?? 1);

    if ($action === 'approve') {
        $upRes = $conn->prepare("
            UPDATE ket_qua_can_lam_sang
            SET trangThai = ?, maBacSiDuyet = ?, thoiGianDuyet = NOW()
            WHERE maKetQua = ?
        ");
        $upRes->bind_param('ssi', $targetResultStatus, $maBacSi, $maKetQua);
        $upRes->execute();
        $upRes->close();

        $upOrder = $conn->prepare("
            UPDATE chi_dinh_chi_tiet
            SET trangThai = ?, maBacSiDuyet = ?, thoiGianDuyet = NOW()
            WHERE maChiTietChiDinh = ?
        ");
        $upOrder->bind_param('ssi', $targetOrderStatus, $maBacSi, $maChiTietChiDinh);
        $upOrder->execute();
        $upOrder->close();
    } else {
        $upRes = $conn->prepare("
            UPDATE ket_qua_can_lam_sang
            SET trangThai = ?, lyDoYeuCauLamLai = ?
            WHERE maKetQua = ?
        ");
        $upRes->bind_param('ssi', $targetResultStatus, $reason, $maKetQua);
        $upRes->execute();
        $upRes->close();

        $upOrder = $conn->prepare("
            UPDATE chi_dinh_chi_tiet
            SET trangThai = ?
            WHERE maChiTietChiDinh = ?
        ");
        $upOrder->bind_param('si', $targetOrderStatus, $maChiTietChiDinh);
        $upOrder->execute();
        $upOrder->close();

        // Đưa lại hàng đợi dịch vụ sang Đang thực hiện để KTV xử lý lại
        $inProgressQueue = CLINICAL_QUEUE_STATUS_IN_PROGRESS;
        $upQueue = $conn->prepare("
            UPDATE hangdoi_dichvu
            SET trangThai = ?, thoiGianHoanThanh = NULL
            WHERE maChiTietChiDinh = ? AND trangThai <> 'Hủy'
        ");
        $upQueue->bind_param('si', $inProgressQueue, $maChiTietChiDinh);
        $upQueue->execute();
        $upQueue->close();
    }

    clinical_sync_parent_order_status($conn, (int)$row['maChiDinh']);
    $workflowStatus = clinical_recompute_record_workflow_status($conn, (string)$row['maHoSo']);

    clinical_append_order_history(
        $conn,
        $maChiTietChiDinh,
        $action === 'approve' ? 'duyet_ket_qua' : 'yeu_cau_lam_lai',
        $currentResultStatus,
        $targetResultStatus,
        $userId,
        null,
        $maBacSi,
        $action === 'approve' ? 'Bác sĩ duyệt và khóa kết quả cận lâm sàng.' : $reason,
        null,
        $maKetQua,
        $phienBan
    );

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maChiTietChiDinh' => $maChiTietChiDinh,
        'maKetQua' => $maKetQua,
        'trangThaiKetQua' => $targetResultStatus,
        'trangThaiChiDinh' => $targetOrderStatus,
        'trangThaiQuyTrinh' => $workflowStatus,
    ], $action === 'approve'
        ? 'Đã duyệt kết quả cận lâm sàng.'
        : 'Đã gửi yêu cầu làm lại cho bộ phận cận lâm sàng.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể xử lý duyệt kết quả cận lâm sàng.');
}
