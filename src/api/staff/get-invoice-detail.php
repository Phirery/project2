<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/billing-helper.php';

api_require_method('GET');
require_staff_permission('billing.collect');

$maLichKham = isset($_GET['maLichKham']) ? (int)$_GET['maLichKham'] : 0;
if ($maLichKham <= 0) {
    api_error('Thiếu mã lượt khám hợp lệ.', 400);
}

try {
    $detail = billing_build_encounter_summary($conn, $maLichKham);
    if (!$detail) {
        api_error('Không tìm thấy lượt khám.', 404);
    }

    api_success($detail, 'Lấy chi tiết hóa đơn tổng hợp thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải chi tiết hóa đơn.');
}
