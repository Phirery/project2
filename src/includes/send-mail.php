<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

function sendMail($toEmail, $subject, $content) {
    $mail = new PHPMailer(true);

    try {
        // --- Cấu hình Server ---
        $mail->isSMTP();                                            // Gửi qua SMTP
        $mail->Host       = 'smtp.gmail.com';                       // Máy chủ Gmail
        $mail->SMTPAuth   = true;                                   // Bật xác thực SMTP
        $mail->Username   = 'ntdatcntt2311055@student.ctuet.edu.vn';// TÀI KHOẢN GMAIL CỦA BẠN
        $mail->Password   = '';                  // MẬT KHẨU ỨNG DỤNG 16 KÝ TỰ
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Mã hóa TLS
        $mail->Port       = 587;                                    // Cổng kết nối TLS
        $mail->CharSet    = 'UTF-8';                                // Hỗ trợ tiếng Việt có dấu

        // --- Người gửi & Người nhận ---
        $mail->setFrom('ntdatcntt2311055@student.ctuet.edu.vn', 'Phòng Khám Eden');
        $mail->addAddress($toEmail);                                // Email người nhận

        // --- Nội dung thư ---
        $mail->isHTML(true);                                        // Cho phép gửi định dạng HTML
        $mail->Subject = $subject;                                  // Tiêu đề thư
        $mail->Body    = $content;                                  // Nội dung thư

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Ghi log lỗi nếu không gửi được để bạn dễ debug
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}