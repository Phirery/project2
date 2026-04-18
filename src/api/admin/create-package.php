<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$tenGoi = trim($data['tenGoi'] ?? '');
$moTa = trim($data['moTa'] ?? '');
$gia = isset($data['gia']) ? (float)$data['gia'] : 0;
$isActive = isset($data['isActive']) ? (int)((bool)$data['isActive']) : 1;

if ($tenGoi === '' || $gia <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tên gói và giá tiền là bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);
    $durationMinutes = getCurrentSlotPresetMinutes($conn);

    $stmt = $conn->prepare(
        "INSERT INTO goikham (tenGoi, moTa, thoiLuong, gia, isActive)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssidi', $tenGoi, $moTa, $durationMinutes, $gia, $isActive);
    $stmt->execute();
    $maGoi = (int)$conn->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Thêm gói khám thành công!',
        'data' => [
            'maGoi' => $maGoi,
            'thoiLuong' => $durationMinutes
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể thêm gói khám: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
