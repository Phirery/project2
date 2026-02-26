<?php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/dp.php';

$stats = [
    'appointments' => 0,
    'patients' => 0,
    'doctors' => 0,
    'departments' => 0
];

try {
    $result = $conn->query("SELECT COUNT(*) as count FROM lichkham");
    $stats['appointments'] = (int)($result->fetch_assoc()['count'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) as count FROM benhnhan");
    $stats['patients'] = (int)($result->fetch_assoc()['count'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) as count FROM bacsi");
    $stats['doctors'] = (int)($result->fetch_assoc()['count'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) as count FROM khoa");
    $stats['departments'] = (int)($result->fetch_assoc()['count'] ?? 0);
} catch (Throwable $e) {
    http_response_code(500);
}

echo json_encode($stats, JSON_UNESCAPED_UNICODE);

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
