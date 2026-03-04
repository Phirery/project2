<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

$conn->begin_transaction();

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $maBacSi = trim($data['maBacSi'] ?? '');
    $tenBacSi = trim($data['tenBacSi'] ?? '');
    $soDienThoai = trim($data['soDienThoai'] ?? '');
    $maChuyenKhoa = trim($data['maChuyenKhoa'] ?? '');
    $tenDangNhap = trim($data['tenDangNhap'] ?? '');
    $matKhau = isset($data['matKhau']) ? (string)$data['matKhau'] : '';
    $moTa = isset($data['moTa']) ? trim((string)$data['moTa']) : '';
    $gioiTinh = isset($data['gioiTinh']) ? trim((string)$data['gioiTinh']) : null;
    $namLamViec = isset($data['namLamViec']) && $data['namLamViec'] !== '' ? intval($data['namLamViec']) : null;
    $email = trim($data['email'] ?? '');
    $avatar = isset($data['avatar']) ? trim((string)$data['avatar']) : '';

    if ($maBacSi === '' || $tenBacSi === '' || $soDienThoai === '' || $maChuyenKhoa === '' || $tenDangNhap === '' || $email === '' || $gioiTinh === null || $gioiTinh === '' || $namLamViec === null) {
        throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc!');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email không hợp lệ!');
    }

    if (!in_array($gioiTinh, ['nam', 'nu'], true)) {
        throw new Exception('Giới tính không hợp lệ!');
    }

    if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
        throw new Exception('Đường dẫn avatar không hợp lệ!');
    }

    $currentYear = intval(date('Y'));
    if ($namLamViec !== null && ($namLamViec < 1950 || $namLamViec > $currentYear)) {
        throw new Exception('Năm làm việc không hợp lệ!');
    }

    $stmtUser = $conn->prepare("SELECT nguoiDungId FROM bacsi WHERE maBacSi = ?");
    $stmtUser->bind_param("s", $maBacSi);
    $stmtUser->execute();
    $userResult = $stmtUser->get_result();

    if ($userResult->num_rows === 0) {
        $stmtUser->close();
        throw new Exception('Không tìm thấy bác sĩ!');
    }
    $nguoiDungId = intval($userResult->fetch_assoc()['nguoiDungId']);
    $stmtUser->close();

    $stmtCheckUser = $conn->prepare("SELECT COUNT(*) as count FROM nguoidung WHERE tenDangNhap = ? AND id != ?");
    $stmtCheckUser->bind_param("si", $tenDangNhap, $nguoiDungId);
    $stmtCheckUser->execute();
    $count = $stmtCheckUser->get_result()->fetch_assoc()['count'] ?? 0;
    $stmtCheckUser->close();
    if (intval($count) > 0) {
        throw new Exception('Tên đăng nhập đã tồn tại!');
    }

    $stmtCheckEmail = $conn->prepare("SELECT COUNT(*) as count FROM nguoidung WHERE email = ? AND id != ?");
    $stmtCheckEmail->bind_param("si", $email, $nguoiDungId);
    $stmtCheckEmail->execute();
    $emailCount = $stmtCheckEmail->get_result()->fetch_assoc()['count'] ?? 0;
    $stmtCheckEmail->close();
    if (intval($emailCount) > 0) {
        throw new Exception('Email đã tồn tại!');
    }

    if ($matKhau !== '') {
        $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);
        if ($avatar !== '') {
            $stmtUpdateUser = $conn->prepare("
                UPDATE nguoidung
                SET tenDangNhap = ?, matKhau = ?, soDienThoai = ?, email = ?, avatar = ?
                WHERE id = ?
            ");
            $stmtUpdateUser->bind_param("sssssi", $tenDangNhap, $hashedPassword, $soDienThoai, $email, $avatar, $nguoiDungId);
        } else {
            $stmtUpdateUser = $conn->prepare("
                UPDATE nguoidung
                SET tenDangNhap = ?, matKhau = ?, soDienThoai = ?, email = ?
                WHERE id = ?
            ");
            $stmtUpdateUser->bind_param("ssssi", $tenDangNhap, $hashedPassword, $soDienThoai, $email, $nguoiDungId);
        }
    } else {
        if ($avatar !== '') {
            $stmtUpdateUser = $conn->prepare("
                UPDATE nguoidung
                SET tenDangNhap = ?, soDienThoai = ?, email = ?, avatar = ?
                WHERE id = ?
            ");
            $stmtUpdateUser->bind_param("ssssi", $tenDangNhap, $soDienThoai, $email, $avatar, $nguoiDungId);
        } else {
            $stmtUpdateUser = $conn->prepare("
                UPDATE nguoidung
                SET tenDangNhap = ?, soDienThoai = ?, email = ?
                WHERE id = ?
            ");
            $stmtUpdateUser->bind_param("sssi", $tenDangNhap, $soDienThoai, $email, $nguoiDungId);
        }
    }

    if (!$stmtUpdateUser->execute()) {
        $stmtUpdateUser->close();
        throw new Exception('Lỗi cập nhật tài khoản: ' . $conn->error);
    }
    $stmtUpdateUser->close();

    $stmtUpdateDoctor = $conn->prepare("
        UPDATE bacsi
        SET tenBacSi = ?, maChuyenKhoa = ?, moTa = ?, gioiTinh = ?, namLamViec = ?
        WHERE maBacSi = ?
    ");
    $stmtUpdateDoctor->bind_param("ssssis", $tenBacSi, $maChuyenKhoa, $moTa, $gioiTinh, $namLamViec, $maBacSi);
    if (!$stmtUpdateDoctor->execute()) {
        $stmtUpdateDoctor->close();
        throw new Exception('Lỗi cập nhật hồ sơ bác sĩ: ' . $conn->error);
    }
    $stmtUpdateDoctor->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thông tin bác sĩ thành công!'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
