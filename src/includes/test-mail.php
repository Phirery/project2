<?php
require_once 'send-mail.php';

$tieuDe = "Thông báo từ Đồ án Phòng khám";
$noiDung = "<h1>Chào bạn!</h1><p>Đây là mail thử nghiệm từ hệ thống quản lý phòng khám của bạn.</p>";

if (sendMail('dat123456789fa@gmail.com', $tieuDe, $noiDung)) {
    echo "Gửi mail thành công! Hãy kiểm tra hộp thư đến (hoặc Spam).";
} else {
    echo "Gửi mail thất bại. Hãy kiểm tra lại cấu hình.";
}