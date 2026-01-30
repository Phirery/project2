<?php
require_once '../../config/cors.php';
require_once '../../core/session.php';
require_once '../../core/dp.php';

// Kiểm tra quyền admin
require_role('quantri');

// Lấy dữ liệu JSON từ request
$data = json_decode(file_get_contents('php://input'), true);

// Validate dữ liệu
if (!isset($data['maLienHe'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin mã liên hệ'
    ]);
    exit;
}

$maLienHe = $conn->real_escape_string($data['maLienHe']);
$ghiChu = isset($data['ghiChu']) && !empty($data['ghiChu']) 
    ? $conn->real_escape_string($data['ghiChu']) 
    : null;
$nguoiXuLy = $_SESSION['id'];

try {
    // Kiểm tra liên hệ tồn tại
    $checkQuery = "SELECT maLienHe, trangThai FROM lienhe WHERE maLienHe = '$maLienHe'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy liên hệ'
        ]);
        exit;
    }
    
    $contact = $checkResult->fetch_assoc();
    
    // Kiểm tra trạng thái hiện tại
    if ($contact['trangThai'] === 'Đã xử lý') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Liên hệ này đã được xử lý trước đó'
        ]);
        exit;
    }
    
    // Cập nhật trạng thái
    $updateQuery = "
        UPDATE lienhe 
        SET 
            trangThai = 'Đã xử lý',
            nguoiXuLy = $nguoiXuLy,
            thoiGianXuLy = NOW()
    ";
    
    // Thêm ghi chú nếu có
    if ($ghiChu !== null) {
        $updateQuery .= ", ghiChu = '$ghiChu'";
    }
    
    $updateQuery .= " WHERE maLienHe = '$maLienHe'";
    
    if ($conn->query($updateQuery)) {
        echo json_encode([
            'success' => true,
            'message' => 'Xử lý liên hệ thành công'
        ]);
    } else {
        throw new Exception($conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi xử lý liên hệ: ' . $e->getMessage()
    ]);
}

$conn->close();
?>