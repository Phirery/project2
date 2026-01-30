<?php
require_once '../../config/cors.php';
require_once '../../core/dp.php';
require_once '../../core/session.php';

require_role('benhnhan'); 

try {
    $userId = $_SESSION['id'];

    $stmt = $conn->prepare("
        SELECT bn.maBenhNhan, bn.tenBenhNhan, nd.tenDangNhap
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        WHERE bn.nguoiDungId = ?
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'role' => 'benhnhan',
            'patientId' => $data['maBenhNhan'],
            'fullName' => $data['tenBenhNhan'],
            'username' => $data['tenDangNhap']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tài khoản chưa được liên kết hồ sơ bệnh nhân'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();