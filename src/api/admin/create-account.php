<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$vaiTro = trim((string)($data['vaiTro'] ?? ''));
$tenDangNhap = trim((string)($data['tenDangNhap'] ?? ''));
$matKhau = (string)($data['matKhau'] ?? '');
$soDienThoai = trim((string)($data['soDienThoai'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$hoTen = trim((string)($data['hoTen'] ?? ''));

if ($vaiTro === '' || $tenDangNhap === '' || $matKhau === '' || $soDienThoai === '' || $email === '' || $hoTen === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Dữ liệu không đầy đủ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($vaiTro, ['benhnhan', 'bacsi', 'quantri'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Vai trò không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[0-9]{10,11}$/', $soDienThoai)) {
    echo json_encode([
        'success' => false,
        'message' => 'Số điện thoại không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE tenDangNhap = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể kiểm tra tên đăng nhập'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param("s", $tenDangNhap);
$stmt->execute();
$usernameCount = intval($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();
if ($usernameCount > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tên đăng nhập đã tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE soDienThoai = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể kiểm tra số điện thoại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param("s", $soDienThoai);
$stmt->execute();
$phoneCount = intval($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();
if ($phoneCount > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Số điện thoại đã tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM nguoidung WHERE email = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể kiểm tra email'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$emailCount = intval($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();
if ($emailCount > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Email đã tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->begin_transaction();

try {
    $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);

    $stmtInsertUser = $conn->prepare("
        INSERT INTO nguoidung (tenDangNhap, matKhau, soDienThoai, email, vaiTro)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtInsertUser) {
        throw new Exception('Không thể khởi tạo tạo tài khoản người dùng');
    }
    $stmtInsertUser->bind_param("sssss", $tenDangNhap, $hashedPassword, $soDienThoai, $email, $vaiTro);
    if (!$stmtInsertUser->execute()) {
        $stmtInsertUser->close();
        throw new Exception('Không thể tạo tài khoản người dùng');
    }
    $stmtInsertUser->close();

    $nguoiDungId = intval($conn->insert_id);

    if ($vaiTro === 'benhnhan') {
        $ngaySinh = trim((string)($data['ngaySinh'] ?? ''));
        $gioiTinh = trim((string)($data['gioiTinh'] ?? ''));
        $soTheBHYT = trim((string)($data['soTheBHYT'] ?? ''));
        $soTheBHYT = $soTheBHYT !== '' ? $soTheBHYT : null;

        if ($ngaySinh === '' || $gioiTinh === '') {
            throw new Exception('Bệnh nhân cần có ngày sinh và giới tính');
        }
        if (!in_array($gioiTinh, ['nam', 'nu', 'khac'], true)) {
            throw new Exception('Giới tính bệnh nhân không hợp lệ');
        }
        if (strtotime($ngaySinh) === false || strtotime($ngaySinh) > time()) {
            throw new Exception('Ngày sinh không hợp lệ');
        }

        $maBenhNhan = 'BN' . date('YmdHi') . sprintf('%03d', rand(0, 999));
        $stmtInsertPatient = $conn->prepare("
            INSERT INTO benhnhan (nguoiDungId, maBenhNhan, tenBenhNhan, ngaySinh, gioiTinh, soTheBHYT)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtInsertPatient) {
            throw new Exception('Không thể khởi tạo tạo hồ sơ bệnh nhân');
        }
        $stmtInsertPatient->bind_param(
            "isssss",
            $nguoiDungId,
            $maBenhNhan,
            $hoTen,
            $ngaySinh,
            $gioiTinh,
            $soTheBHYT
        );
        if (!$stmtInsertPatient->execute()) {
            $stmtInsertPatient->close();
            throw new Exception('Không thể tạo hồ sơ bệnh nhân');
        }
        $stmtInsertPatient->close();
    } elseif ($vaiTro === 'bacsi') {
        $gioiTinh = trim((string)($data['gioiTinh'] ?? ''));
        $namLamViec = intval($data['namLamViec'] ?? 0);
        $maChuyenKhoa = trim((string)($data['maChuyenKhoa'] ?? ''));
        $moTa = trim((string)($data['moTa'] ?? ''));
        $moTa = $moTa !== '' ? $moTa : null;

        if ($gioiTinh === '' || $namLamViec <= 0 || $maChuyenKhoa === '') {
            throw new Exception('Bác sĩ cần đủ giới tính, năm làm việc và chuyên khoa');
        }
        if (!in_array($gioiTinh, ['nam', 'nu'], true)) {
            throw new Exception('Giới tính bác sĩ không hợp lệ');
        }

        $currentYear = intval(date('Y'));
        if ($namLamViec < 1950 || $namLamViec > $currentYear) {
            throw new Exception('Năm làm việc không hợp lệ');
        }

        $stmtCheckSpecialty = $conn->prepare("SELECT COUNT(*) AS count FROM chuyenkhoa WHERE maChuyenKhoa = ?");
        if (!$stmtCheckSpecialty) {
            throw new Exception('Không thể khởi tạo kiểm tra chuyên khoa');
        }
        $stmtCheckSpecialty->bind_param("s", $maChuyenKhoa);
        if (!$stmtCheckSpecialty->execute()) {
            $stmtCheckSpecialty->close();
            throw new Exception('Không thể kiểm tra chuyên khoa');
        }
        $specialtyCount = intval($stmtCheckSpecialty->get_result()->fetch_assoc()['count'] ?? 0);
        $stmtCheckSpecialty->close();
        if ($specialtyCount === 0) {
            throw new Exception('Chuyên khoa không tồn tại');
        }

        $maBacSi = 'BS' . date('YmdHi') . sprintf('%03d', rand(0, 999));
        $stmtInsertDoctor = $conn->prepare("
            INSERT INTO bacsi (nguoiDungId, maBacSi, tenBacSi, maChuyenKhoa, gioiTinh, namLamViec, moTa)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtInsertDoctor) {
            throw new Exception('Không thể khởi tạo tạo hồ sơ bác sĩ');
        }
        $stmtInsertDoctor->bind_param(
            "issssis",
            $nguoiDungId,
            $maBacSi,
            $hoTen,
            $maChuyenKhoa,
            $gioiTinh,
            $namLamViec,
            $moTa
        );
        if (!$stmtInsertDoctor->execute()) {
            $stmtInsertDoctor->close();
            throw new Exception('Không thể tạo hồ sơ bác sĩ');
        }
        $stmtInsertDoctor->close();
    } else {
        $maQuanTriVien = 'ADMIN' . date('YmdHi') . sprintf('%03d', rand(0, 999));
        $stmtInsertAdmin = $conn->prepare("
            INSERT INTO quantrivien (nguoiDungId, maQuanTriVien)
            VALUES (?, ?)
        ");
        if (!$stmtInsertAdmin) {
            throw new Exception('Không thể khởi tạo tạo hồ sơ quản trị viên');
        }
        $stmtInsertAdmin->bind_param("is", $nguoiDungId, $maQuanTriVien);
        if (!$stmtInsertAdmin->execute()) {
            $stmtInsertAdmin->close();
            throw new Exception('Không thể tạo hồ sơ quản trị viên');
        }
        $stmtInsertAdmin->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tạo tài khoản thành công!'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
