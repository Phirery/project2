<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

global $conn;

if (isset($_SESSION['id']) && isset($_SESSION['vaiTro'])) {
    $userId = $_SESSION['id'];
    $userRole = $_SESSION['vaiTro'];
    $tenDangNhap = $_SESSION['tenDangNhap'];
    
    $hoTenHienThi = $tenDangNhap;
    $avatar = '';

    try {
        $avatarStmt = $conn->prepare("SELECT avatar FROM nguoidung WHERE id = ?");
        $avatarStmt->bind_param("i", $userId);
        $avatarStmt->execute();
        $avatarResult = $avatarStmt->get_result();
        if ($avatarResult->num_rows > 0) {
            $avatarRow = $avatarResult->fetch_assoc();
            $avatar = $avatarRow['avatar'] ?? '';
        }
        $avatarStmt->close();
    } catch (Exception $e) {
        $avatar = '';
    }

    if ($userRole === 'benhnhan') {
        try {
            $stmt = $conn->prepare("SELECT tenBenhNhan FROM benhnhan WHERE nguoiDungId = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $patient = $result->fetch_assoc();
                $hoTenHienThi = $patient['tenBenhNhan'];
            }
            $stmt->close();
        } catch (Exception $e) {
            // Log lỗi nếu cần, nhưng vẫn trả về tên đăng nhập nếu truy vấn lỗi
            // error_log("Lỗi truy vấn tên bệnh nhân: " . $e->getMessage()); 
        }
    } 

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $userId,
            'username' => $tenDangNhap, 
            'role' => $userRole,
            'fullName' => $hoTenHienThi,
            'avatar' => $avatar
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
}
?>
