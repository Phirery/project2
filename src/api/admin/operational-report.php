<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/operational-report-helper.php';

api_require_method('GET');
require_role('quantri');
date_default_timezone_set('Asia/Ho_Chi_Minh');

$dateTo = trim((string)($_GET['dateTo'] ?? date('Y-m-d')));
$dateFrom = trim((string)($_GET['dateFrom'] ?? date('Y-m-d', strtotime('-6 days'))));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 60;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    api_error('Định dạng ngày không hợp lệ (YYYY-MM-DD).', 400);
}
if ($dateFrom > $dateTo) {
    api_error('dateFrom phải nhỏ hơn hoặc bằng dateTo.', 422);
}

try {
    $report = admin_build_operational_report($conn, $dateFrom, $dateTo, $limit);
    api_success($report, 'Tải báo cáo vận hành và nhật ký audit thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải báo cáo vận hành.');
}
