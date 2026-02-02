<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../includes/send-email.php';

// Start session
session_start();

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['email'], $input['otp'], $input['newPassword'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin cần thiết'
    ]);
    exit;
}

$email = trim($input['email']);
$otp = trim($input['otp']);
$newPassword = $input['newPassword'];

try {
    // === Validate OTP từ session ===
    if (!isset($_SESSION['forgot_otp']) || !isset($_SESSION['forgot_otp_user']) || !isset($_SESSION['forgot_otp_expiry'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Không có mã OTP nào được tạo. Vui lòng yêu cầu mã mới.'
        ]);
        exit;
    }
    
    // === Kiểm tra OTP đã hết hạn chưa ===
    if (time() > $_SESSION['forgot_otp_expiry']) {
        unset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_user'], $_SESSION['forgot_otp_expiry'], $_SESSION['forgot_otp_email']);
        echo json_encode([
            'success' => false,
            'message' => 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.'
        ]);
        exit;
    }
    
    // === Verify OTP ===
    if ($otp !== $_SESSION['forgot_otp']) {
        echo json_encode([
            'success' => false,
            'message' => 'Mã OTP không chính xác!'
        ]);
        exit;
    }
    
    // === Validate password length ===
    if (strlen($newPassword) < 6) {
        echo json_encode([
            'success' => false,
            'message' => 'Mật khẩu phải có ít nhất 6 ký tự!'
        ]);
        exit;
    }
    
    $userId = $_SESSION['forgot_otp_user'];
    
    // === Verify email matches ===
    $stmt = $conn->prepare("
        SELECT id, tenDangNhap, email FROM nguoidung 
        WHERE id = ? AND email = ?
    ");
    $stmt->bind_param("is", $userId, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Thông tin không khớp!'
        ]);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // === Hash new password ===
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // === Update password ===
    $stmt = $conn->prepare("
        UPDATE nguoidung 
        SET matKhau = ?, ngayCapNhatMatKhau = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();
    $stmt->close();
    
    // === Gửi email xác nhận đổi mật khẩu ===
    $changeTime = date('d/m/Y H:i:s');
    $emailSubject = "Mật khẩu đã được thay đổi - Eden Health";
    $emailBody = getPasswordChangedEmailTemplate($user['tenDangNhap'], $changeTime);
    
    sendEmail($email, $emailSubject, $emailBody);
    
    // === Clear OTP from session ===
    unset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_user'], $_SESSION['forgot_otp_expiry'], $_SESSION['forgot_otp_email'], $_SESSION['last_otp_request_time']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đổi mật khẩu thành công! Email xác nhận đã được gửi.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();