<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/staff-permission.php';

require_role('nhanvien');

try {
    $context = staff_get_rbac_context($conn, (int)$_SESSION['id']);

    if (!empty($context['staff'])) {
        $staff = $context['staff'];
        echo json_encode([
            "success" => true,
            "data" => [
                "id" => $staff['id'],
                "maNhanVien" => $staff['maNhanVien'],
                "tenNhanVien" => $staff['tenNhanVien'],
                "loaiNhanVien" => $staff['loaiNhanVien'],
                "tenDangNhap" => $staff['tenDangNhap'],
                "soDienThoai" => $staff['soDienThoai'],
                "email" => $staff['email'],
                "avatar" => $staff['avatar'],
                "isDemo" => $context['isDemo'],
                "roles" => $context['roles'],
                "permissions" => $context['permissions'],
                "modules" => $context['modules'],
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Không tìm thấy thông tin nhân viên"
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
