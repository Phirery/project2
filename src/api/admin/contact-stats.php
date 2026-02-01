<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';

// Kiểm tra quyền admin
require_role('quantri');

try {
    // Lấy thống kê tổng số liên hệ
    $queryTotal = "SELECT COUNT(*) as total FROM lienhe";
    $resultTotal = $conn->query($queryTotal);
    $total = $resultTotal->fetch_assoc()['total'];
    
    // Lấy thống kê chưa xử lý
    $queryPending = "SELECT COUNT(*) as pending FROM lienhe WHERE trangThai = 'Chưa xử lý'";
    $resultPending = $conn->query($queryPending);
    $pending = $resultPending->fetch_assoc()['pending'];
    
    // Lấy thống kê đã xử lý
    $queryCompleted = "SELECT COUNT(*) as completed FROM lienhe WHERE trangThai = 'Đã xử lý'";
    $resultCompleted = $conn->query($queryCompleted);
    $completed = $resultCompleted->fetch_assoc()['completed'];
    
    echo json_encode([
        'success' => true,
        'total' => (int)$total,
        'pending' => (int)$pending,
        'completed' => (int)$completed
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
    ]);
}

$conn->close();
?>