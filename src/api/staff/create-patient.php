<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

/**
 * Tạo hồ sơ bệnh nhân mới khi check-in trực tiếp (walk-in, Case A - chưa có tài khoản).
 * taoTaiKhoan = true  -> nhân viên nhập tenDangNhap/matKhau, bệnh nhân dùng để đăng nhập sau này.
 * taoTaiKhoan = false -> hệ thống tự sinh tài khoản tạm (username/mật khẩu ngẫu nhiên,
 *                         đánh dấu taiKhoanTamThoi=1, bệnh nhân không dùng để đăng nhập).
 */

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $tenBenhNhan = trim($data['tenBenhNhan'] ?? '');
    $soDienThoai = trim($data['soDienThoai'] ?? '');
    $emailRaw = trim($data['email'] ?? '');
    $email = $emailRaw !== '' ? $emailRaw : null;
    $ngaySinh = trim($data['ngaySinh'] ?? '');
    $gioiTinh = trim($data['gioiTinh'] ?? '');
    $soTheBHYTRaw = trim($data['soTheBHYT'] ?? '');
    $soTheBHYT = $soTheBHYTRaw !== '' ? $soTheBHYTRaw : null;
    $taoTaiKhoan = !empty($data['taoTaiKhoan']);

    if ($tenBenhNhan === '' || $soDienThoai === '' || $ngaySinh === '' || $gioiTinh === '') {
        throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc (họ tên, SĐT, ngày sinh, giới tính)!');
    }

    if (strtotime($ngaySinh) === false || strtotime($ngaySinh) > time()) {
        throw new Exception('Ngày sinh không hợp lệ!');
    }

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email không hợp lệ!');
    }

    $taiKhoanTamThoi = 0;

    if ($taoTaiKhoan) {
        $tenDangNhap = trim($data['tenDangNhap'] ?? '');
        $matKhau = $data['matKhau'] ?? '';
        if ($tenDangNhap === '' || $matKhau === '') {
            throw new Exception('Vui lòng nhập tên đăng nhập và mật khẩu cho tài khoản!');
        }
        if (strlen($matKhau) < 6) {
            throw new Exception('Mật khẩu phải có ít nhất 6 ký tự!');
        }
    } else {
        // Tự sinh tài khoản tạm, bệnh nhân không dùng để đăng nhập
        $tenDangNhap = 'kh' . date('ymdHis') . rand(100, 999);
        $matKhau = bin2hex(random_bytes(12));
        $taiKhoanTamThoi = 1;
    }

    // Kiểm tra trùng SĐT
    $checkPhoneStmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE soDienThoai = ?");
    $checkPhoneStmt->bind_param('s', $soDienThoai);
    $checkPhoneStmt->execute();
    if ((int)$checkPhoneStmt->get_result()->fetch_assoc()['count'] > 0) {
        throw new Exception('Số điện thoại đã được đăng ký cho một tài khoản khác!');
    }
    $checkPhoneStmt->close();

    // Kiểm tra trùng username (chỉ có ý nghĩa khi nhân viên tự nhập)
    $checkUserStmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE tenDangNhap = ?");
    $checkUserStmt->bind_param('s', $tenDangNhap);
    $checkUserStmt->execute();
    if ((int)$checkUserStmt->get_result()->fetch_assoc()['count'] > 0) {
        throw new Exception('Tên đăng nhập đã tồn tại!');
    }
    $checkUserStmt->close();

    if ($email !== null) {
        $checkEmailStmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE email = ?");
        $checkEmailStmt->bind_param('s', $email);
        $checkEmailStmt->execute();
        if ((int)$checkEmailStmt->get_result()->fetch_assoc()['count'] > 0) {
            throw new Exception('Email đã tồn tại!');
        }
        $checkEmailStmt->close();
    }

    $conn->begin_transaction();
    $inTransaction = true;

    $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);

    $insertUserStmt = $conn->prepare("
        INSERT INTO nguoidung (tenDangNhap, matKhau, soDienThoai, email, vaiTro, trangThai, taiKhoanTamThoi)
        VALUES (?, ?, ?, ?, 'benhnhan', 'Hoạt Động', ?)
    ");
    $insertUserStmt->bind_param('ssssi', $tenDangNhap, $hashedPassword, $soDienThoai, $email, $taiKhoanTamThoi);
    if (!$insertUserStmt->execute()) {
        throw new Exception('Lỗi tạo tài khoản: ' . $insertUserStmt->error);
    }
    $nguoiDungId = $conn->insert_id;
    $insertUserStmt->close();

    // Tạo maBenhNhan tự động: BN + YYYYMMDDHHMM + random 3 số
    $maBenhNhan = 'BN' . date('YmdHi') . str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);

    $insertPatientStmt = $conn->prepare("
        INSERT INTO benhnhan (nguoiDungId, maBenhNhan, tenBenhNhan, ngaySinh, gioiTinh, soTheBHYT)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insertPatientStmt->bind_param('isssss', $nguoiDungId, $maBenhNhan, $tenBenhNhan, $ngaySinh, $gioiTinh, $soTheBHYT);
    if (!$insertPatientStmt->execute()) {
        throw new Exception('Lỗi tạo hồ sơ bệnh nhân: ' . $insertPatientStmt->error);
    }
    $insertPatientStmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tạo hồ sơ bệnh nhân thành công!',
        'maBenhNhan' => $maBenhNhan,
        'taiKhoanTamThoi' => (bool)$taiKhoanTamThoi
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (!empty($inTransaction)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>