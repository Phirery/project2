<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

global $conn;

if (isset($_SESSION['id']) && isset($_SESSION['vaiTro'])) {
    $userId = $_SESSION['id'];
    $userRole = $_SESSION['vaiTro'];
    $tenDangNhap = $_SESSION['tenDangNhap'];
    
    $hoTenHienThi = $tenDangNhap;
    $avatar = '';

    try {
        $avatarStmt = $conn->prepare("SELECT avatar, isDeleted, trangThai FROM nguoidung WHERE id = ?");
        $avatarStmt->bind_param("i", $userId);
        $avatarStmt->execute();
        $avatarResult = $avatarStmt->get_result();
        if ($avatarResult->num_rows > 0) {
            $avatarRow = $avatarResult->fetch_assoc();
            if ((int)($avatarRow['isDeleted'] ?? 0) === 1 || ($avatarRow['trangThai'] ?? '') === 'Khóa') {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();

                echo json_encode([
                    'success' => false,
                    'message' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.'
                ]);
                $avatarStmt->close();
                $conn->close();
                exit;
            }
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
    } elseif ($userRole === 'nhanvien') {
        try {
            $stmt = $conn->prepare("SELECT tenNhanVien FROM nhanvien WHERE nguoiDungId = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $staff = $result->fetch_assoc();
                if (!empty($staff['tenNhanVien'])) {
                    $hoTenHienThi = $staff['tenNhanVien'];
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            // Giữ tên đăng nhập nếu truy vấn lỗi
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