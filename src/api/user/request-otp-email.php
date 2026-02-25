<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/send-mail.php';
require_once '../../includes/mail-events.php';

$nguoiDungId = $_SESSION['id'];

try {
    // === CHỐNG SPAM: Kiểm tra cooldown ===
    $cooldownSeconds = 60;
    $currentTime = time();

    if (isset($_SESSION['profile_otp_last_request'])) {
        $elapsed = $currentTime - $_SESSION['profile_otp_last_request'];
        if ($elapsed < $cooldownSeconds) {
            $remaining = $cooldownSeconds - $elapsed;
            echo json_encode([
                'success' => false,
                'message' => "Vui lòng đợi {$remaining} giây trước khi gửi lại mã OTP",
                'cooldown' => $remaining
            ]);
            exit;
        }
    }

    // === Lấy email của user ===
    $stmt = $conn->prepare("
        SELECT email, tenDangNhap 
        FROM nguoidung 
        WHERE id = ? AND trangThai = 'Hoạt Động'
    ");
    $stmt->bind_param("i", $nguoiDungId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || empty($result['email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Tài khoản của bạn chưa có email. Vui lòng liên hệ quản trị viên.'
        ]);
        exit;
    }

    $email = $result['email'];

    // === Generate OTP ===
    $otp = sprintf('%06d', mt_rand(0, 999999));

    // === Lưu vào session (dùng key riêng để không xung đột với forgot-password) ===
    $_SESSION['profile_otp'] = $otp;
    $_SESSION['profile_otp_expiry'] = $currentTime + 300; // 5 phút
    $_SESSION['profile_otp_last_request'] = $currentTime;

    // === Gửi email OTP ===
    $emailSubject = "Mã OTP đổi mật khẩu - Eden Health";
    $emailBody = getOTPEmailTemplate($otp, 5);

    // Key duy nhất mỗi lần gửi (bao gồm OTP và timestamp để tránh bị block bởi dedup)
    $eventKey = 'profile_otp:' . $nguoiDungId . ':' . $otp . ':' . $currentTime;

    $emailResult = sendTransactionalMail(
        $conn,
        'auth_profile_otp',
        $eventKey,
        $email,
        $emailSubject,
        $emailBody
    );

    if (!($emailResult['success'] ?? false)) {
        echo json_encode([
            'success' => false,
            'message' => 'Không thể gửi email. Vui lòng thử lại sau.'
        ]);
        exit;
    }

    // === Che email để hiển thị an toàn ===
    $parts = explode('@', $email);
    $localPart = $parts[0];
    $domain = $parts[1] ?? '';
    $maskedLocal = substr($localPart, 0, min(2, strlen($localPart)))
        . str_repeat('*', max(strlen($localPart) - 2, 3));
    $maskedEmail = $maskedLocal . '@' . $domain;

    echo json_encode([
        'success' => true,
        'message' => 'Mã OTP đã được gửi đến email của bạn',
        'maskedEmail' => $maskedEmail,
        'expirySeconds' => 300
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();
?>