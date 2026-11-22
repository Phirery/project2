<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/clinical-orders.php';

api_require_method('POST');
$rbac = require_staff_permission([
    'cls.xet_nghiem.perform',
    'cls.cdha.perform',
    'cls.noi_soi.perform',
]);

$input = api_json_input(true);
$maHangDoiDichVu = isset($input['maHangDoiDichVu']) ? (int)$input['maHangDoiDichVu'] : 0;
$action = strtolower(trim((string)($input['action'] ?? '')));

$actionToTarget = [
    'start' => CLINICAL_QUEUE_STATUS_IN_PROGRESS,
    'miss' => CLINICAL_QUEUE_STATUS_MISSED,
    'recall' => CLINICAL_QUEUE_STATUS_WAITING,
];

if ($maHangDoiDichVu <= 0 || !isset($actionToTarget[$action])) {
    api_error('Yêu cầu cập nhật hàng đợi cận lâm sàng không hợp lệ.', 400);
}

$maNhanVien = (string)$rbac['staff']['maNhanVien'];
$userId = (int)$_SESSION['id'];
$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        SELECT
            hq.maHangDoiDichVu,
            hq.maChiTietChiDinh,
            hq.maChiDinh,
            hq.maHoSo,
            hq.nhomDichVu,
            hq.trangThai AS queueStatus,
            ct.trangThai AS orderStatus
        FROM hangdoi_dichvu hq
        JOIN chi_dinh_chi_tiet ct ON hq.maChiTietChiDinh = ct.maChiTietChiDinh
        WHERE hq.maHangDoiDichVu = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $maHangDoiDichVu);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy mục hàng đợi cận lâm sàng.', 404);
    }

    $requiredPerm = clinical_service_group_permission((string)$row['nhomDichVu']);
    if ($requiredPerm === null || !staff_has_permission($rbac['permissions'], $requiredPerm)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Bạn không có quyền thao tác trên nhóm dịch vụ này.', 403, ['code' => 'FORBIDDEN_PERMISSION']);
    }

    if ($row['orderStatus'] === CLINICAL_ORDER_STATUS_CANCELLED || $row['queueStatus'] === CLINICAL_QUEUE_STATUS_CANCELLED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ định đã bị hủy, không thể tiếp nhận thực hiện.', 409);
    }

    $fromQueueStatus = (string)$row['queueStatus'];
    $toQueueStatus = $actionToTarget[$action];

    if (!clinical_can_transition(clinical_queue_transitions(), $fromQueueStatus, $toQueueStatus)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không thể chuyển trạng thái hàng đợi từ "' . $fromQueueStatus . '" sang "' . $toQueueStatus . '".', 409);
    }

    if ($action === 'start') {
        $upQueue = $conn->prepare("
            UPDATE hangdoi_dichvu
            SET trangThai = ?,
                maNhanVienTiepNhan = COALESCE(maNhanVienTiepNhan, ?),
                maNhanVienThucHien = ?,
                thoiGianGoi = COALESCE(thoiGianGoi, NOW()),
                thoiGianBatDau = COALESCE(thoiGianBatDau, NOW())
            WHERE maHangDoiDichVu = ?
        ");
        $upQueue->bind_param('sssi', $toQueueStatus, $maNhanVien, $maNhanVien, $maHangDoiDichVu);
    } else {
        $upQueue = $conn->prepare("
            UPDATE hangdoi_dichvu
            SET trangThai = ?
            WHERE maHangDoiDichVu = ?
        ");
        $upQueue->bind_param('si', $toQueueStatus, $maHangDoiDichVu);
    }

    if (!$upQueue->execute()) {
        throw new RuntimeException('Không thể cập nhật hàng đợi dịch vụ: ' . $upQueue->error);
    }
    $upQueue->close();

    if ($action === 'start' && in_array($row['orderStatus'], [CLINICAL_ORDER_STATUS_CREATED, CLINICAL_ORDER_STATUS_WAITING], true)) {
        $inProgressOrder = CLINICAL_ORDER_STATUS_IN_PROGRESS;
        $upOrder = $conn->prepare("
            UPDATE chi_dinh_chi_tiet
            SET trangThai = ?, maNhanVienXuLy = ?
            WHERE maChiTietChiDinh = ?
        ");
        $upOrder->bind_param('ssi', $inProgressOrder, $maNhanVien, $row['maChiTietChiDinh']);
        $upOrder->execute();
        $upOrder->close();

        clinical_sync_parent_order_status($conn, (int)$row['maChiDinh']);
        clinical_recompute_record_workflow_status($conn, (string)$row['maHoSo']);

        clinical_append_order_history(
            $conn,
            (int)$row['maChiTietChiDinh'],
            'bat_dau_thuc_hien',
            $row['orderStatus'],
            $inProgressOrder,
            $userId,
            $maNhanVien,
            null,
            'KTV tiếp nhận bệnh nhân và bắt đầu thực hiện dịch vụ.'
        );
    }

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maHangDoiDichVu' => $maHangDoiDichVu,
        'maChiTietChiDinh' => (int)$row['maChiTietChiDinh'],
        'trangThaiHangDoi' => $toQueueStatus,
    ], 'Cập nhật trạng thái hàng đợi dịch vụ thành công.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể cập nhật trạng thái hàng đợi dịch vụ.');
}
