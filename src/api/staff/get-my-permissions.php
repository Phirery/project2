<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('GET');
require_role('nhanvien');

try {
    $context = staff_get_rbac_context($conn, (int)$_SESSION['id']);
    if (empty($context['staff'])) {
        api_error('Không tìm thấy thông tin nhân viên.', 404);
    }

    api_success($context, 'Lấy thông tin phân quyền thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải thông tin phân quyền nhân viên.');
}
