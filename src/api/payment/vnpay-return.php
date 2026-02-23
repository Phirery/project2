<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../includes/mail-events.php';

// Endpoint callback/return thanh toán VNPAY (bản demo).
// Khi tích hợp production cần verify chữ ký VNPAY trước khi cập nhật hóa đơn.

$maHoaDon = isset($_REQUEST['maHoaDon']) ? (int)$_REQUEST['maHoaDon'] : 0;
if ($maHoaDon <= 0 && isset($_REQUEST['vnp_TxnRef'])) {
    $maHoaDon = (int)$_REQUEST['vnp_TxnRef'];
}

$responseCode = trim($_REQUEST['vnp_ResponseCode'] ?? '');
$transactionNo = trim($_REQUEST['vnp_TransactionNo'] ?? '');

if ($maHoaDon <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu mã hóa đơn'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$isSuccess = ($responseCode === '00');
$newStatus = $isSuccess ? 'Đã thanh toán' : 'Chưa thanh toán';
$reason = $isSuccess ? '' : ('Mã lỗi VNPAY: ' . ($responseCode !== '' ? $responseCode : 'unknown'));

try {
    $stmt = $conn->prepare(
        "UPDATE hoadon
         SET trangThai = ?,
             phuongThuc = 'VNPAY',
             vnp_TransactionNo = ?
         WHERE maHoaDon = ?"
    );
    $stmt->bind_param('ssi', $newStatus, $transactionNo, $maHoaDon);
    $stmt->execute();
    $stmt->close();

    try {
        sendPaymentStatusEmail($conn, $maHoaDon, $newStatus, $reason);
    } catch (Throwable $mailError) {
        error_log('VNPAY callback mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => $isSuccess ? 'Thanh toán thành công' : 'Thanh toán thất bại',
        'data' => [
            'maHoaDon' => $maHoaDon,
            'trangThai' => $newStatus,
            'vnp_ResponseCode' => $responseCode,
            'vnp_TransactionNo' => $transactionNo
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
