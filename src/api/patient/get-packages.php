<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../includes/schedule-management.php';

try {
    ensureScheduleManagementSchema($conn);

    $sql = "SELECT maGoi, tenGoi, moTa, gia
            FROM goikham
            WHERE isActive = 1
            ORDER BY gia, maGoi";
    $result = $conn->query($sql);
    
    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $packages
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
