<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/clinical-orders.php';

api_require_method('GET');
require_role('bacsi');

$nhom = trim((string)($_GET['nhom'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

try {
    $services = clinical_fetch_service_catalog($conn, true, $nhom, $search);
    api_success([
        'groups' => array_values(CLINICAL_SERVICE_GROUPS),
        'services' => $services,
    ], 'Lấy danh mục dịch vụ cận lâm sàng thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải danh mục dịch vụ cận lâm sàng.');
}
