<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../config/app-env.php';

$mailConfig = require __DIR__ . '/../config/mail.php';
$lastMailError = null;

function isMailDebugEnabled(): bool {
    global $mailConfig;
    return !empty($mailConfig['debug']);
}

function setLastMailError(?string $error): void {
    global $lastMailError;
    $lastMailError = $error;
}

function getLastMailError(): ?string {
    global $lastMailError;
    return $lastMailError;
}

function getMailConfigValue(string $key, $default = null) {
    global $mailConfig;
    return array_key_exists($key, $mailConfig) ? $mailConfig[$key] : $default;
}

function resolveSmtpHost(string $host, bool $forceIpv4): string {
    $host = trim($host);
    if ($host === '' || !$forceIpv4) {
        return $host;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }

    $ipv4 = gethostbyname($host);
    if ($ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $ipv4;
    }

    return $host;
}

function parseHttpStatusFromHeaders(array $headers): int {
    foreach ($headers as $line) {
        if (preg_match('/^HTTP\/\d+\.\d+\s+(\d{3})/i', (string)$line, $matches)) {
            return (int)$matches[1];
        }
    }
    return 0;
}

function sendEmailViaBrevoApi(string $toEmail, string $subject, string $htmlContent, string $textContent = ''): bool {
    $apiKey = trim((string)getMailConfigValue('brevo_api_key', ''));
    if ($apiKey === '') {
        $errorDetail = 'Brevo API key is missing';
        setLastMailError($errorDetail);
        error_log("Email Error: {$errorDetail}");
        return false;
    }

    $username = (string)getMailConfigValue('username', '');
    $fromName = (string)getMailConfigValue('from_name', 'Eden Health - Phong Kham');
    $fromEmail = (string)getMailConfigValue('from_email', $username);
    $timeout = (int)getMailConfigValue('timeout', 20);

    $payload = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => [
            ['email' => $toEmail]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ];

    if ($textContent !== '') {
        $payload['textContent'] = $textContent;
    }

    $url = 'https://api.brevo.com/v3/smtp/email';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $errorDetail = 'Failed to encode Brevo payload';
        setLastMailError($errorDetail);
        error_log("Email Error: {$errorDetail}");
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeout
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $errorDetail = 'Brevo API curl error: ' . ($curlError ?: 'unknown');
            setLastMailError($errorDetail);
            error_log("Email Error: {$errorDetail}");
            return false;
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        $errorDetail = "Brevo API error (HTTP {$status}): " . trim((string)$response);
        setLastMailError($errorDetail);
        error_log("Email Error: {$errorDetail}");
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\n"
                . "Content-Type: application/json\r\n"
                . "api-key: {$apiKey}\r\n",
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    $status = parseHttpStatusFromHeaders($headers);

    if ($response === false) {
        $errorDetail = 'Brevo API request failed via stream context';
        setLastMailError($errorDetail);
        error_log("Email Error: {$errorDetail}");
        return false;
    }

    if ($status >= 200 && $status < 300) {
        return true;
    }

    $errorDetail = "Brevo API error (HTTP {$status}): " . trim((string)$response);
    setLastMailError($errorDetail);
    error_log("Email Error: {$errorDetail}");
    return false;
}

function sendEmailViaSmtp(string $toEmail, string $subject, string $htmlContent, string $textContent = ''): bool {
    $mail = new PHPMailer(true);

    try {
        $host = (string)getMailConfigValue('host', 'smtp.gmail.com');
        $port = (int)getMailConfigValue('port', 587);
        $username = (string)getMailConfigValue('username', '');
        $password = (string)getMailConfigValue('password', '');
        $smtpAuth = (bool)getMailConfigValue('smtp_auth', true);
        $timeout = (int)getMailConfigValue('timeout', 20);
        $forceIpv4 = (bool)getMailConfigValue('force_ipv4', true);
        $fromName = (string)getMailConfigValue('from_name', 'Eden Health - Phong Kham');
        $fromEmail = (string)getMailConfigValue('from_email', $username);
        $encryption = strtolower((string)getMailConfigValue('encryption', 'tls'));

        $smtpHost = resolveSmtpHost($host, $forceIpv4);

        // === Cấu hình Server ===
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = $smtpAuth;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->Port       = $port;
        $mail->Timeout    = $timeout;

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);

        // === Nội dung email ===
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;

        // Nội dung văn bản thuần (cho email client không hỗ trợ HTML)
        if ($textContent) {
            $mail->AltBody = $textContent;
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorDetail = trim((string)$mail->ErrorInfo);
        if ($errorDetail === '') {
            $errorDetail = trim((string)$e->getMessage());
        }
        if ($errorDetail === '') {
            $errorDetail = 'Unknown SMTP error';
        }

        setLastMailError($errorDetail);
        error_log("Email Error: {$errorDetail}");
        return false;
    }
}

function getAppLoginUrl(): string {
    return rtrim(APP_BASE_URL, '/') . '/login.html';
}

/**
 * @param string $toEmail Email người nhận
 * @param string $subject Tiêu đề email
 * @param string $htmlContent Nội dung HTML
 * @param string $textContent Nội dung văn bản thuần (fallback)
 * @return bool Trả về true nếu gửi thành công, false nếu thất bại
 */
function sendEmail($toEmail, $subject, $htmlContent, $textContent = '') {
    setLastMailError(null);
    $transport = strtolower((string)getMailConfigValue('transport', 'smtp'));

    if ($transport === 'brevo_api') {
        return sendEmailViaBrevoApi($toEmail, $subject, $htmlContent, $textContent);
    }

    return sendEmailViaSmtp($toEmail, $subject, $htmlContent, $textContent);
}

/**
 * Template HTML cho email OTP
 */
function getOTPEmailTemplate($otp, $expiryMinutes = 3) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #4A90E2 0%); padding: 30px; text-align: center; color: white; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 10px 0 0; font-size: 14px; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .otp-box { background: linear-gradient(135deg, #4A90E2); color: white; text-align: center; padding: 25px; border-radius: 10px; margin: 30px 0; }
            .otp-code { font-size: 36px; font-weight: 700; letter-spacing: 8px; margin: 10px 0; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 6px; }
            .warning-icon { color: #ffc107; font-size: 20px; margin-right: 10px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 13px; border-top: 1px solid #e0e0e0; }
            .footer a { color: #4A90E2; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Eden Health</h1>
                <p>Hệ thống Quản lý Phòng Khám</p>
            </div>
            
            <div class='content'>
                <h2 style='color: #333; margin-top: 0;'>Xác thực tài khoản của bạn</h2>
                <p style='color: #666; line-height: 1.6;'>Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng sử dụng mã OTP bên dưới để xác thực:</p>
                
                <div class='otp-box'>
                    <p style='margin: 0; font-size: 14px; opacity: 0.9;'>Mã xác thực OTP của bạn</p>
                    <div class='otp-code'>{$otp}</div>
                    <p style='margin: 0; font-size: 13px; opacity: 0.8;'>⏱️ Có hiệu lực trong {$expiryMinutes} phút</p>
                </div>
                
                <div class='warning'>
                    <p style='margin: 0; color: #856404;'><span class='warning-icon'>⚠️</span><strong>Lưu ý bảo mật:</strong> Không chia sẻ mã này với bất kỳ ai. Nhân viên Eden Health sẽ không bao giờ yêu cầu mã OTP của bạn.</p>
                </div>
                
                <p style='color: #666; margin-top: 30px;'>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này hoặc liên hệ với chúng tôi ngay.</p>
            </div>
            
            <div class='footer'>
                <p style='margin: 0 0 10px;'><strong>Eden Health - Chăm sóc sức khỏe toàn diện</strong></p>
                <p style='margin: 0;'>📧 Email: support@edenhealth.vn | 📞 Hotline: 1900-xxxx</p>
                <p style='margin: 10px 0 0;'>© 2025 Eden Health. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Template HTML cho email đổi mật khẩu thành công
 */
function getPasswordChangedEmailTemplate($username, $changeTime) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #4A90E2); padding: 30px; text-align: center; color: white; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .content { padding: 40px 30px; }
            .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 6px; margin: 20px 0; }
            .info-box { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 13px; border-top: 1px solid #e0e0e0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Đổi mật khẩu thành công</h1>
            </div>
            
            <div class='content'>
                <div class='success-box'>
                    <p style='margin: 0; color: #155724;'><strong>Mật khẩu của bạn đã được thay đổi thành công!</strong></p>
                </div>
                
                <h3 style='color: #333;'>Thông tin chi tiết:</h3>
                <div class='info-box'>
                    <p style='margin: 5px 0; color: #666;'><strong>Tên đăng nhập:</strong> {$username}</p>
                    <p style='margin: 5px 0; color: #666;'><strong>Thời gian:</strong> {$changeTime}</p>
                    <p style='margin: 5px 0; color: #666;'><strong>Địa chỉ IP:</strong> {$_SERVER['REMOTE_ADDR']}</p>
                </div>
                
                <p style='color: #666; line-height: 1.6;'>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ với chúng tôi ngay lập tức qua hotline <strong>1900-xxxx</strong> để bảo vệ tài khoản của bạn.</p>
                
                <p style='color: #666; margin-top: 30px;'>Trân trọng,<br><strong>Đội ngũ Eden Health</strong></p>
            </div>
            
            <div class='footer'>
                <p style='margin: 0 0 10px;'><strong>Eden Health - Chăm sóc sức khỏe toàn diện</strong></p>
                <p style='margin: 0;'>📧 Email: support@edenhealth.vn | 📞 Hotline: 1900-xxxx</p>
                <p style='margin: 10px 0 0;'>© 2025 Eden Health. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Template cho email chào mừng (đăng ký thành công)
 */
function getWelcomeEmailTemplate($fullName, $username) {
    $loginUrl = getAppLoginUrl();

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #4A90E2); padding: 40px 30px; text-align: center; color: white; }
            .header h1 { margin: 0; font-size: 32px; font-weight: 700; }
            .content { padding: 40px 30px; }
            .welcome-text { font-size: 18px; color: #333; margin-bottom: 20px; }
            .feature-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .feature-item { margin: 10px 0; color: #666; }
            .cta-button { display: inline-block; background: linear-gradient(135deg, #4A90E2, #52c41a); color: white; padding: 15px 40px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 13px; border-top: 1px solid #e0e0e0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Chào mừng đến với Eden Health!</h1>
            </div>
            
            <div class='content'>
                <p class='welcome-text'>Xin chào <strong>{$fullName}</strong>,</p>
                <p style='color: #666; line-height: 1.6;'>Cảm ơn bạn đã đăng ký tài khoản tại Eden Health! Chúng tôi rất vui mừng được đồng hành cùng bạn trong hành trình chăm sóc sức khỏe.</p>
                
                <div class='feature-box'>
                    <h3 style='margin-top: 0; color: #333;'>Thông tin tài khoản:</h3>
                    <p style='margin: 5px 0; color: #666;'><strong>Tên đăng nhập:</strong> {$username}</p>
                    <p style='margin: 5px 0; color: #666;'><strong>Trạng thái:</strong> ✅ Đã kích hoạt</p>
                </div>
                
                <h3 style='color: #333;'>Bạn có thể:</h3>
                <div class='feature-item'>✅ Đặt lịch khám với bác sĩ chuyên khoa</div>
                <div class='feature-item'>✅ Quản lý hồ sơ bệnh án cá nhân</div>
                <div class='feature-item'>✅ Tra cứu lịch sử khám bệnh</div>
                <div class='feature-item'>✅ Nhận thông báo nhắc nhở lịch hẹn</div>
                
                <div style='text-align: center;'>
                    <a href='{$loginUrl}' class='cta-button'>Đăng nhập ngay</a>
                </div>
                
                <p style='color: #666; margin-top: 30px;'>Nếu bạn có bất kỳ câu hỏi nào, đừng ngần ngại liên hệ với chúng tôi!</p>
            </div>
            
            <div class='footer'>
                <p style='margin: 0 0 10px;'><strong>Eden Health - Chăm sóc sức khỏe toàn diện</strong></p>
                <p style='margin: 0;'>📧 Email: support@edenhealth.vn | 📞 Hotline: 19xx-xxxx</p>
                <p style='margin: 10px 0 0;'>© 2025 Eden Health. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
