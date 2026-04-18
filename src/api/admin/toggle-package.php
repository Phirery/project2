<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$maGoi = isset($data['maGoi']) ? (int)$data['maGoi'] : 0;
$isActive = isset($data['isActive']) ? (int)((bool)$data['isActive']) : null;

if ($maGoi <= 0 || $isActive === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu thay đổi trạng thái gói khám'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);
    $package = getPackageRowById($conn, $maGoi);

    if (!$package) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy gói khám'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("UPDATE goikham SET isActive = ? WHERE maGoi = ?");
    $stmt->bind_param('ii', $isActive, $maGoi);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => $isActive === 1 ? 'Đã bật gói khám.' : 'Đã ẩn gói khám.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể thay đổi trạng thái gói khám: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
