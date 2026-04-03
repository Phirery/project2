<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('quantri');

$conn->begin_transaction();

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $tenBacSi = trim($data['tenBacSi'] ?? '');
    $soDienThoai = trim($data['soDienThoai'] ?? '');
    $maChuyenKhoa = trim($data['maChuyenKhoa'] ?? '');
    $tenDangNhap = trim($data['tenDangNhap'] ?? '');
    $matKhau = $data['matKhau'] ?? '';
    $email = trim($data['email'] ?? '');
    $gioiTinh = isset($data['gioiTinh']) ? trim((string)$data['gioiTinh']) : null;
    $namLamViec = isset($data['namLamViec']) && $data['namLamViec'] !== '' ? intval($data['namLamViec']) : null;
    $moTa = isset($data['moTa']) ? trim((string)$data['moTa']) : '';
    $avatar = isset($data['avatar']) ? trim((string)$data['avatar']) : '';

    if ($tenBacSi === '' || $soDienThoai === '' || $maChuyenKhoa === '' || $tenDangNhap === '' || $matKhau === '' || $email === '' || $gioiTinh === null || $gioiTinh === '' || $namLamViec === null) {
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

    $stmtCheck = $conn->prepare("SELECT COUNT(*) as count FROM nguoidung WHERE tenDangNhap = ?");
    $stmtCheck->bind_param("s", $tenDangNhap);
    $stmtCheck->execute();
    $count = $stmtCheck->get_result()->fetch_assoc()['count'] ?? 0;
    $stmtCheck->close();
    if (intval($count) > 0) {
        throw new Exception('Tên đăng nhập đã tồn tại!');
    }

    $stmtCheckEmail = $conn->prepare("SELECT COUNT(*) as count FROM nguoidung WHERE email = ?");
    $stmtCheckEmail->bind_param("s", $email);
    $stmtCheckEmail->execute();
    $emailCount = $stmtCheckEmail->get_result()->fetch_assoc()['count'] ?? 0;
    $stmtCheckEmail->close();
    if (intval($emailCount) > 0) {
        throw new Exception('Email đã tồn tại!');
    }

    $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);

    if ($avatar !== '') {
        $stmtInsertUser = $conn->prepare("
            INSERT INTO nguoidung (tenDangNhap, matKhau, soDienThoai, email, vaiTro, avatar)
            VALUES (?, ?, ?, ?, 'bacsi', ?)
        ");
        $stmtInsertUser->bind_param("sssss", $tenDangNhap, $hashedPassword, $soDienThoai, $email, $avatar);
    } else {
        $stmtInsertUser = $conn->prepare("
            INSERT INTO nguoidung (tenDangNhap, matKhau, soDienThoai, email, vaiTro)
            VALUES (?, ?, ?, ?, 'bacsi')
        ");
        $stmtInsertUser->bind_param("ssss", $tenDangNhap, $hashedPassword, $soDienThoai, $email);
    }

    if (!$stmtInsertUser->execute()) {
        $stmtInsertUser->close();
        throw new Exception('Lỗi tạo tài khoản: ' . $conn->error);
    }
    $stmtInsertUser->close();

    $nguoiDungId = $conn->insert_id;

    $maBacSi = 'BS' . date('YmdHi') . sprintf('%03d', rand(0, 999));

    $stmtInsertDoctor = $conn->prepare("
        INSERT INTO bacsi (nguoiDungId, maBacSi, tenBacSi, maChuyenKhoa, gioiTinh, namLamViec, moTa)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsertDoctor->bind_param(
        "issssis",
        $nguoiDungId,
        $maBacSi,
        $tenBacSi,
        $maChuyenKhoa,
        $gioiTinh,
        $namLamViec,
        $moTa
    );
    if (!$stmtInsertDoctor->execute()) {
        $stmtInsertDoctor->close();
        throw new Exception('Lỗi tạo hồ sơ bác sĩ: ' . $conn->error);
    }
    $stmtInsertDoctor->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Thêm bác sĩ thành công!',
        'maBacSi' => $maBacSi
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
