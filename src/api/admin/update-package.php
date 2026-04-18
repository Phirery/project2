<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$maGoi = isset($data['maGoi']) ? (int)$data['maGoi'] : 0;
$tenGoi = trim($data['tenGoi'] ?? '');
$moTa = trim($data['moTa'] ?? '');
$gia = isset($data['gia']) ? (float)$data['gia'] : 0;
$isActive = isset($data['isActive']) ? (int)((bool)$data['isActive']) : 1;

if ($maGoi <= 0 || $tenGoi === '' || $gia <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu cập nhật gói khám'
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

    $durationMinutes = getCurrentSlotPresetMinutes($conn);

    $stmt = $conn->prepare(
        "UPDATE goikham
         SET tenGoi = ?, moTa = ?, thoiLuong = ?, gia = ?, isActive = ?
         WHERE maGoi = ?"
    );
    $stmt->bind_param('ssidii', $tenGoi, $moTa, $durationMinutes, $gia, $isActive, $maGoi);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật gói khám thành công!',
        'data' => [
            'maGoi' => $maGoi,
            'thoiLuong' => $durationMinutes
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể cập nhật gói khám: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
