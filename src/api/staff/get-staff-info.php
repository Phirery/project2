<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

try {
    // Lấy thông tin nhân viên
    $stmt = $conn->prepare("
        SELECT nv.maNhanVien, nv.tenNhanVien, nv.loaiNhanVien, nd.tenDangNhap, nd.soDienThoai, nd.avatar
        FROM nhanvien nv
        JOIN nguoidung nd ON nv.nguoiDungId = nd.id
        WHERE nv.nguoiDungId = ?
    ");

    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        echo json_encode([
            "success" => true,
            "data" => [
                "maNhanVien" => $staff['maNhanVien'],
                "tenNhanVien" => $staff['tenNhanVien'],
                "loaiNhanVien" => $staff['loaiNhanVien'],
                "tenDangNhap" => $staff['tenDangNhap'],
                "soDienThoai" => $staff['soDienThoai'],
                "avatar" => $staff['avatar']
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Không tìm thấy thông tin nhân viên"
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi: " . $e->getMessage()
    ]);
}

$conn->close();
?>