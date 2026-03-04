<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/send-mail.php';
require_once '../../includes/mail-events.php';

function maskEmailAddress(string $email): string {
    $parts = explode('@', $email);
    $localPart = $parts[0] ?? '';
    $domainPart = $parts[1] ?? '';

    if ($localPart === '' || $domainPart === '') {
        return $email;
    }

    $visibleChars = min(2, strlen($localPart));
    $maskedLocal = substr($localPart, 0, $visibleChars)
        . str_repeat('*', max(strlen($localPart) - $visibleChars, 3));

    return $maskedLocal . '@' . $domainPart;
}

$input = json_decode(file_get_contents("php://input"), true);
$email = trim((string)($input['email'] ?? ''));

if ($email === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng nhập email'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Định dạng email không hợp lệ'
    ]);
    exit;
}

try {
    $currentTime = time();
    $cooldownSeconds = 60;
    $expirySeconds = 300;
    $normalizedEmail = strtolower($email);

    if (isset($_SESSION['register_otp_last_request'])) {
        $elapsed = $currentTime - (int)$_SESSION['register_otp_last_request'];
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

    $stmt = $conn->prepare("SELECT id FROM nguoidung WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $emailExists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($emailExists) {
        echo json_encode([
            'success' => false,
            'message' => 'Email đã được đăng ký'
        ]);
        exit;
    }

    $otp = sprintf('%06d', random_int(0, 999999));

    $_SESSION['register_otp'] = $otp;
    $_SESSION['register_otp_email'] = $normalizedEmail;
    $_SESSION['register_otp_expiry'] = $currentTime + $expirySeconds;
    $_SESSION['register_otp_last_request'] = $currentTime;

    $emailSubject = 'Mã OTP xác thực đăng ký tài khoản - Eden Health';
    $emailBody = getRegisterOTPEmailTemplate($otp, 5);
    $eventKey = 'register_otp:' . hash('sha256', $normalizedEmail) . ':' . $currentTime;

    $emailResult = sendTransactionalMail(
        $conn,
        'auth_register_otp',
        $eventKey,
        $email,
        $emailSubject,
        $emailBody
    );

    if (!($emailResult['success'] ?? false)) {
        unset(
            $_SESSION['register_otp'],
            $_SESSION['register_otp_email'],
            $_SESSION['register_otp_expiry'],
            $_SESSION['register_otp_last_request']
        );

        $response = [
            'success' => false,
            'message' => 'Không thể gửi email OTP. Vui lòng thử lại sau.'
        ];

        if (!empty($emailResult['reason'])) {
            $response['reason'] = $emailResult['reason'];
        }
        if (!empty($emailResult['mail_error'])) {
            $response['mailError'] = $emailResult['mail_error'];
        }

        echo json_encode($response);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Mã OTP đã được gửi đến email của bạn',
        'maskedEmail' => maskEmailAddress($email),
        'expirySeconds' => $expirySeconds,
        'cooldown' => $cooldownSeconds
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
