<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('quantri');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $maBenhNhan = $conn->real_escape_string($data['maBenhNhan'] ?? '');
    $tenBenhNhan = trim($conn->real_escape_string($data['tenBenhNhan'] ?? ''));
    $soDienThoai = trim($conn->real_escape_string($data['soDienThoai'] ?? ''));
    $emailRaw = trim($data['email'] ?? '');
    $email = $emailRaw !== '' ? $conn->real_escape_string($emailRaw) : null;
    $ngaySinh = $conn->real_escape_string($data['ngaySinh'] ?? '');
    $gioiTinh = $conn->real_escape_string($data['gioiTinh'] ?? '');
    $soTheBHYT = $conn->real_escape_string($data['soTheBHYT'] ?? null);
    $tenDangNhap = trim($conn->real_escape_string($data['tenDangNhap'] ?? ''));
    $matKhau = $data['matKhau'] ?? '';  // Nếu có, update password

    if (empty($maBenhNhan) || empty($tenBenhNhan) || empty($tenDangNhap) || empty($ngaySinh) || empty($gioiTinh) || empty($emailRaw)) {
        throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc!');
    }

    if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email không hợp lệ!');
    }

    // Lấy nguoiDungId từ benhnhan
    $getIdSql = "SELECT nguoiDungId FROM benhnhan WHERE maBenhNhan = '$maBenhNhan'";
    $result = $conn->query($getIdSql);
    if ($result->num_rows === 0) {
        throw new Exception('Không tìm thấy bệnh nhân!');
    }
    $nguoiDungId = $result->fetch_assoc()['nguoiDungId'];

    // Kiểm tra username bị trùng (trừ chính tài khoản đang sửa)
    $checkUserSql = "SELECT COUNT(*) as count FROM nguoidung WHERE tenDangNhap = '$tenDangNhap' AND id != $nguoiDungId";
    $checkUser = $conn->query($checkUserSql)->fetch_assoc()['count'];
    if ($checkUser > 0) {
        throw new Exception('Tên đăng nhập đã tồn tại!');
    }

    // Kiểm tra email bị trùng (trừ chính tài khoản đang sửa)
    $checkEmailSql = "SELECT COUNT(*) as count FROM nguoidung WHERE email = '$email' AND id != $nguoiDungId";
    $checkEmail = $conn->query($checkEmailSql)->fetch_assoc()['count'];
    if ($checkEmail > 0) {
        throw new Exception('Email đã tồn tại!');
    }
    
    if (strtotime($ngaySinh) > time()) {
        throw new Exception('Ngày sinh không được vượt quá ngày hiện tại!');
    }
    // Update nguoidung
    $updateUserSql = "UPDATE nguoidung SET 
                      tenDangNhap = '$tenDangNhap',
                      soDienThoai = '$soDienThoai',
                      email = " . ($email ? "'$email'" : "NULL") .
                      (!empty($matKhau) ? ", matKhau = '" . password_hash($matKhau, PASSWORD_DEFAULT) . "'" : "") .
                      " WHERE id = $nguoiDungId";
    if (!$conn->query($updateUserSql)) {
        throw new Exception('Lỗi cập nhật tài khoản: ' . $conn->error);
    }

    // Update benhnhan
    $updatePatientSql = "UPDATE benhnhan SET 
                         tenBenhNhan = '$tenBenhNhan',
                         ngaySinh = '$ngaySinh',
                         gioiTinh = '$gioiTinh',
                         soTheBHYT = " . ($soTheBHYT ? "'$soTheBHYT'" : "NULL") . "
                         WHERE maBenhNhan = '$maBenhNhan'";
    if (!$conn->query($updatePatientSql)) {
        throw new Exception('Lỗi cập nhật bệnh nhân: ' . $conn->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật bệnh nhân thành công!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
