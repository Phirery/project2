<?php
require_once __DIR__ . '/../config/dp.php';
require_once __DIR__ . '/../includes/mail-events.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

function writeCronLog(string $fileName, array $payload): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = date('Y-m-d H:i:s') . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($logDir . '/' . $fileName, $line, FILE_APPEND);
}

function collectAppointmentIdsForReminder(mysqli $conn, int $minMinutes, int $maxMinutes): array {
    $sql = "
        SELECT lk.maLichKham
        FROM lichkham lk
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE lk.trangThai = 'Đã đặt'
          AND TIMESTAMPDIFF(
                MINUTE,
                NOW(),
                STR_TO_DATE(CONCAT(lk.ngayKham, ' ', sk.gioBatDau), '%Y-%m-%d %H:%i:%s')
              ) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $minMinutes, $maxMinutes);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['maLichKham'];
    }

    $stmt->close();
    return $ids;
}

$summary = [
    'timestamp' => date('Y-m-d H:i:s'),
    '24h' => ['found' => 0, 'sent' => 0],
    '2h' => ['found' => 0, 'sent' => 0],
];

try {
    $list24h = collectAppointmentIdsForReminder($conn, 1410, 1470);
    $summary['24h']['found'] = count($list24h);

    foreach ($list24h as $maLichKham) {
        $result = sendAppointmentReminderEmail($conn, $maLichKham, '24h');
        if (!empty($result['success'])) {
            $summary['24h']['sent']++;
        }
    }

    $list2h = collectAppointmentIdsForReminder($conn, 90, 150);
    $summary['2h']['found'] = count($list2h);

    foreach ($list2h as $maLichKham) {
        $result = sendAppointmentReminderEmail($conn, $maLichKham, '2h');
        if (!empty($result['success'])) {
            $summary['2h']['sent']++;
        }
    }

    if (PHP_SAPI === 'cli') {
        echo "[" . $summary['timestamp'] . "] Reminder summary: " . json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $summary], JSON_UNESCAPED_UNICODE);
    }

    writeCronLog('send-appointment-reminders.log', [
        'status' => 'success',
        'summary' => $summary
    ]);
} catch (Throwable $e) {
    writeCronLog('send-appointment-reminders.log', [
        'status' => 'error',
        'message' => $e->getMessage()
    ]);

    if (PHP_SAPI === 'cli') {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

$conn->close();
?>
