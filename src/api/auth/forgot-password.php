<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../includes/send-mail.php';

// Start session
session_start();

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng nhập email'
    ]);
    exit;
}

$email = trim($input['email']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Định dạng email không hợp lệ'
    ]);
    exit;
}

try {
    // === CHỐNG SPAM: Kiểm tra thời gian gửi lại ===
    $currentTime = time();
    $cooldownSeconds = 60; // 60 giây = 1 phút
    
    if (isset($_SESSION['last_otp_request_time'])) {
        $timeSinceLastRequest = $currentTime - $_SESSION['last_otp_request_time'];
        
        if ($timeSinceLastRequest < $cooldownSeconds) {
            $remainingSeconds = $cooldownSeconds - $timeSinceLastRequest;
            echo json_encode([
                'success' => false,
                'message' => "Vui lòng đợi {$remainingSeconds} giây trước khi gửi lại mã OTP",
                'cooldown' => $remainingSeconds
            ]);
            exit;
        }
    }
    
    // === Tìm user bằng EMAIL ===
    $stmt = $conn->prepare("
        SELECT id, tenDangNhap, email, vaiTro 
        FROM nguoidung 
        WHERE email = ? AND trangThai = 'Hoạt Động'
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email không tồn tại hoặc tài khoản đã bị khóa'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // === Generate 6-digit OTP ===
    $otp = sprintf('%06d', mt_rand(0, 999999));
    
    // === Store OTP in session ===
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_user'] = $user['id'];
    $_SESSION['forgot_otp_email'] = $email;
    $_SESSION['forgot_otp_expiry'] = time() + 180; // 3 phút = 180 giây
    $_SESSION['last_otp_request_time'] = $currentTime; // Lưu thời gian gửi
    
    // === Gửi email OTP ===
    $emailSubject = "Mã OTP đặt lại mật khẩu - Eden Health";
    $emailBody = getOTPEmailTemplate($otp, 3); // 3 phút
    
    $emailSent = sendEmail($email, $emailSubject, $emailBody);
    
    if (!$emailSent) {
        echo json_encode([
            'success' => false,
            'message' => 'Không thể gửi email. Vui lòng thử lại sau.'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Mã OTP đã được gửi đến email của bạn',
        'email' => $email, // Trả về email để hiển thị
        'expirySeconds' => 180 // 3 phút
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();