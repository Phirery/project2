<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';

// Kiểm tra quyền admin
require_role('quantri');

try {
    $query = "
        SELECT 
            l.maLienHe,
            l.hoTen,
            l.email,
            l.soDienThoai,
            l.chuDe,
            l.noiDung,
            l.trangThai,
            l.thoiGianGui,
            l.nguoiXuLy,
            l.thoiGianXuLy,
            l.ghiChu,
            n.tenDangNhap as tenNguoiXuLy
        FROM lienhe l
        LEFT JOIN nguoidung n ON l.nguoiXuLy = n.id
        ORDER BY 
            CASE WHEN l.trangThai = 'Chưa xử lý' THEN 0 ELSE 1 END,
            l.thoiGianGui DESC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = [
            'maLienHe' => $row['maLienHe'],
            'hoTen' => $row['hoTen'],
            'email' => $row['email'],
            'soDienThoai' => $row['soDienThoai'],
            'chuDe' => $row['chuDe'],
            'noiDung' => $row['noiDung'],
            'trangThai' => $row['trangThai'],
            'thoiGianGui' => $row['thoiGianGui'],
            'nguoiXuLy' => $row['nguoiXuLy'],
            'thoiGianXuLy' => $row['thoiGianXuLy'],
            'ghiChu' => $row['ghiChu'],
            'tenNguoiXuLy' => $row['tenNguoiXuLy']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $contacts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi lấy danh sách liên hệ: ' . $e->getMessage()
    ]);
}

$conn->close();
?>