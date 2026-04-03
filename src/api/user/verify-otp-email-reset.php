<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

$nguoiDungId = $_SESSION['id'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // === Kiểm tra OTP có trong session không ===
    if (!isset($_SESSION['profile_otp']) || !isset($_SESSION['profile_otp_expiry'])) {
        throw new Exception('Không có mã OTP nào được tạo. Vui lòng yêu cầu mã mới.');
    }

    // === Kiểm tra hết hạn ===
    if (time() > $_SESSION['profile_otp_expiry']) {
        unset($_SESSION['profile_otp'], $_SESSION['profile_otp_expiry'], $_SESSION['profile_otp_last_request']);
        throw new Exception('Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.');
    }

    // === Xác thực OTP ===
    $inputOtp = trim($input['otp'] ?? '');
    if ($inputOtp !== $_SESSION['profile_otp']) {
        throw new Exception('Mã OTP không chính xác!');
    }

    // === Validate mật khẩu mới ===
    $newPassword = $input['newPassword'] ?? '';
    if (strlen($newPassword) < 6) {
        throw new Exception('Mật khẩu phải có ít nhất 6 ký tự!');
    }

    // === Xóa OTP khỏi session ===
    unset(
        $_SESSION['profile_otp'],
        $_SESSION['profile_otp_expiry'],
        $_SESSION['profile_otp_last_request']
    );

    // === Cập nhật mật khẩu ===
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE nguoidung 
        SET matKhau = ?, ngayCapNhatMatKhau = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $hashedPassword, $nguoiDungId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>