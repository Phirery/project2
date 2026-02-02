<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';

session_start();

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['username'], $input['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin đăng nhập'
    ]);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

try {
    // Tìm user bằng username HOẶC soDienThoai HOẶC email
    $stmt = $conn->prepare("
        SELECT id, tenDangNhap, matKhau, vaiTro, trangThai 
        FROM nguoidung 
        WHERE (tenDangNhap = ? OR soDienThoai = ? OR email = ?)
    ");
    
    $stmt->bind_param("sss", $username, $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tài khoản không tồn tại'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();

    // Kiểm tra trạng thái tài khoản
    if ($user['trangThai'] === 'Khóa') {
        echo json_encode([
            'success' => false,
            'message' => 'Tài khoản đã bị khóa. Không thể đăng nhập'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // Kiểm tra mật khẩu
    $dbPassword = $user['matKhau'];
    $isHashVerified = password_verify($password, $dbPassword);

    if ($isHashVerified) {
        // Mật khẩu đã được hash - OK
    } else {
        // Thử so sánh plaintext (để tương thích ngược)
        $isPlaintextVerified = hash_equals($dbPassword, $password);

        if ($isPlaintextVerified) {
            // Hash lại mật khẩu plaintext thành bcrypt
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE nguoidung SET matKhau = ? WHERE id = ?");
            $update->bind_param("si", $newHash, $user['id']);
            $update->execute();
            $update->close();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Tên đăng nhập/Email/SĐT hoặc mật khẩu không đúng',
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
    }

    // Lưu session
    $_SESSION['id'] = $user['id'];
    $_SESSION['vaiTro'] = $user['vaiTro'];
    $_SESSION['tenDangNhap'] = $user['tenDangNhap'];

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập thành công',
        'role' => $user['vaiTro']
    ]);

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();