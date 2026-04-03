<?php
require_once __DIR__ . '/../config/dp.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

function writeCronLog(string $fileName, array $payload): void
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = date('Y-m-d H:i:s') . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($logDir . '/' . $fileName, $line, FILE_APPEND);
}

function isAuthorizedHttpRequest(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $requiredKey = getenv('CRON_HTTP_KEY') ?: '';
    if ($requiredKey === '') {
        return true;
    }

    $providedKey = isset($_GET['key']) ? (string)$_GET['key'] : '';
    return hash_equals($requiredKey, $providedKey);
}

if (!isAuthorizedHttpRequest()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$summary = [
    'timestamp' => date('Y-m-d H:i:s'),
    'affectedRows' => 0
];

try {
    $sql = "
        UPDATE lichkham
        SET
            trangThai = 'Hủy',
            nguoiHuy = 'hethong',
            ghiChu = CASE
                WHEN ghiChu IS NULL OR TRIM(ghiChu) = '' THEN
                    '[Lý do hủy]: Quá thời gian khám trong ngày'
                WHEN ghiChu LIKE '%[Lý do hủy]:%' THEN
                    ghiChu
                ELSE
                    CONCAT(ghiChu, '\n[Lý do hủy]: Quá thời gian khám trong ngày')
            END
        WHERE
            ngayKham <= CURDATE()
            AND trangThai NOT IN ('Hoàn thành', 'Hủy')
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $summary['affectedRows'] = $stmt->affected_rows;
    $stmt->close();

    if (PHP_SAPI === 'cli') {
        echo '[' . $summary['timestamp'] . '] Auto-cancel summary: ' . json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $summary], JSON_UNESCAPED_UNICODE);
    }

    writeCronLog('auto-cancel-expired-appointments.log', [
        'status' => 'success',
        'summary' => $summary
    ]);
} catch (Throwable $e) {
    writeCronLog('auto-cancel-expired-appointments.log', [
        'status' => 'error',
        'message' => $e->getMessage()
    ]);

    if (PHP_SAPI === 'cli') {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

$conn->close();
?>
