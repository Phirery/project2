<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
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
        error_log('VNPAY return mail error: ' . $mailError->getMessage());
    }
}

$params = [
    'payment' => 'vnpay',
    'status' => $result['paymentSuccess'] ? 'success' : 'failed',
    'rspCode' => $result['rspCode'],
    'paymentCode' => $result['paymentCode'] ?? '',
    'message' => $result['paymentSuccess']
        ? 'Thanh toan VNPay thanh cong'
        : ($result['paymentMessage'] ?: $result['message']),
];

if (!empty($result['invoice']['maHoaDon'])) {
    $params['maHoaDon'] = (string)$result['invoice']['maHoaDon'];
}

if (!empty($result['invoice']['maLichKham'])) {
    $params['maLichKham'] = (string)$result['invoice']['maLichKham'];
}

if (!empty($result['invoice']['vnp_TransactionNo'])) {
    $params['vnp_TransactionNo'] = (string)$result['invoice']['vnp_TransactionNo'];
}

$redirectUrl = rtrim(APP_BASE_URL, '/') . '/dat-lich.html?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

header('Location: ' . $redirectUrl, true, 302);
exit;
