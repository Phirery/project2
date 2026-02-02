<?php
require 'send-mail.php';

// Email nhận (đổi thành email của bạn)
$toEmail = 'dat123456789fa@gmail.com';

// Test OTP
$otp = rand(100000, 999999);
$subject = 'Test gửi OTP';
$html = getOTPEmailTemplate($otp);
$text = "Ma OTP cua ban la: $otp";

$result = sendEmail($toEmail, $subject, $html, $text);

if ($result) {
    echo "✅ Gửi email OTP thành công!";
} else {
    echo "❌ Gửi email thất bại. Kiểm tra log.";
}
