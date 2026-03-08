<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

// Lấy danh sách bệnh nhân
$sql = "SELECT 
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            nd.email,
            nd.tenDangNhap
        FROM benhnhan bn
        LEFT JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        WHERE nd.isDeleted = 0
        ORDER BY bn.tenBenhNhan ASC";

$result = $conn->query($sql);

if ($result === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi truy vấn: ' . $conn->error
    ]);
    $conn->close();
    exit;
}

$patients = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $patients[] = [
            'maBenhNhan' => $row['maBenhNhan'],
            'tenBenhNhan' => $row['tenBenhNhan'],
            'ngaySinh' => $row['ngaySinh'],
            'gioiTinh' => $row['gioiTinh'],
            'soTheBHYT' => $row['soTheBHYT'],
            'soDienThoai' => $row['soDienThoai'],
            'email' => $row['email'],
            'tenDangNhap' => $row['tenDangNhap']
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => $patients
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
