<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/vnpay.php';

$result = vnpay_process_callback($conn, $_REQUEST);

if (!empty($result['invoice']['maHoaDon'])) {
    try {
        sendPaymentStatusEmail(
            $conn,
            (int)$result['invoice']['maHoaDon'],
            $result['paymentSuccess'] ? 'Đã thanh toán' : 'Chưa thanh toán',
            $result['paymentSuccess'] ? '' : ($result['paymentMessage'] ?: $result['message'])
        );
    } catch (Throwable $mailError) {
        error_log('VNPAY IPN mail error: ' . $mailError->getMessage());
    }
}

echo json_encode([
    'RspCode' => $result['rspCode'],
    'Message' => $result['message'],
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
