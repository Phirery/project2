<?php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

try {
    $stats = [
        'patients' => 0,
        'doctors' => 0,
        'specialties' => 0,
        'appointments' => 0
    ];

    $result = $conn->query("SELECT COUNT(*) as count FROM benhnhan");
    if ($result) {
        $stats['patients'] = (int)$result->fetch_assoc()['count'];
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM bacsi");
    if ($result) {
        $stats['doctors'] = (int)$result->fetch_assoc()['count'];
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM chuyenkhoa");
    if ($result) {
        $stats['specialties'] = (int)$result->fetch_assoc()['count'];
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM lichkham");
    if ($result) {
        $stats['appointments'] = (int)$result->fetch_assoc()['count'];
    }

    echo json_encode([
        'success' => true,
        'data' => $stats
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Không thể tải thống kê.'
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
