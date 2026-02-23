<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$maHoaDon = isset($data['maHoaDon']) ? (int)$data['maHoaDon'] : 0;
$trangThai = trim($data['trangThai'] ?? '');
$phuongThuc = trim($data['phuongThuc'] ?? '');
$transactionNo = trim($data['vnp_TransactionNo'] ?? '');
$reason = trim($data['reason'] ?? '');

if ($maHoaDon <= 0 || !in_array($trangThai, ['Chưa thanh toán', 'Đã thanh toán'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Dữ liệu không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($phuongThuc !== '' && !in_array($phuongThuc, ['TienMat', 'VNPAY'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức thanh toán không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $oldStmt = $conn->prepare("SELECT maHoaDon, trangThai, phuongThuc, vnp_TransactionNo FROM hoadon WHERE maHoaDon = ? LIMIT 1");
    $oldStmt->bind_param('i', $maHoaDon);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    if (!$oldRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy hóa đơn'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updateSql = "UPDATE hoadon SET trangThai = ?, phuongThuc = ?, vnp_TransactionNo = ? WHERE maHoaDon = ?";
    $updateStmt = $conn->prepare($updateSql);

    $methodParam = $phuongThuc !== '' ? $phuongThuc : null;
    $txnParam = $transactionNo !== '' ? $transactionNo : null;

    $updateStmt->bind_param('sssi', $trangThai, $methodParam, $txnParam, $maHoaDon);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affected <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Không có thay đổi dữ liệu'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        sendPaymentStatusEmail($conn, $maHoaDon, $trangThai, $reason);
    } catch (Throwable $mailError) {
        error_log('Admin invoice status mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật trạng thái thanh toán thành công'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
