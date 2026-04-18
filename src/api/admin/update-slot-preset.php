<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);
$durationMinutes = isset($data['durationMinutes']) ? (int)$data['durationMinutes'] : 0;

if (!isValidSlotPreset($durationMinutes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Preset slot không hợp lệ. Chỉ chấp nhận 30, 40 hoặc 60 phút.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $currentDuration = getCurrentSlotPresetMinutes($conn);
    $effectiveFrom = getNextScheduleEffectiveDate($conn);

    if ($currentDuration === $durationMinutes) {
        echo json_encode([
            'success' => true,
            'message' => 'Cấu hình hiện tại đã dùng preset này.',
            'data' => getScheduleConfigData($conn)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $scheduleConfig = applySlotPreset($conn, $durationMinutes);
    $notificationResult = sendSchedulePresetChangedNotifications($conn, $currentDuration, $durationMinutes, $effectiveFrom);
    $mailResult = sendSchedulePresetChangedEmails($conn, $currentDuration, $durationMinutes, $effectiveFrom);

    $notificationSummary = isset($notificationResult['sent']) ? (' Đã tạo ' . (int)$notificationResult['sent'] . ' thông báo bác sĩ.') : '';
    $mailSummary = isset($mailResult['sent']) ? (' Đã gửi ' . (int)$mailResult['sent'] . ' email bác sĩ.') : '';

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật preset suất khám thành công!' . $notificationSummary . $mailSummary,
        'data' => $scheduleConfig
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể cập nhật preset suất khám: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
