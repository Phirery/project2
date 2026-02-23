<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$maBenhNhan = trim($data['maBenhNhan'] ?? '');
$maBacSi = trim($data['maBacSi'] ?? '');
$ngayKham = trim($data['ngayKham'] ?? '');
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;
$maGoi = isset($data['maGoi']) && $data['maGoi'] !== '' ? (int)$data['maGoi'] : null;
$trangThai = trim($data['trangThai'] ?? 'Đã đặt');
$ghiChu = trim($data['ghiChu'] ?? '');

if ($maBenhNhan === '' || $maBacSi === '' || $ngayKham === '' || $maCa <= 0 || $maSuat <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->begin_transaction();

    if ($maGoi === null) {
        $stmt = $conn->prepare(
            "INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?)"
        );
        $stmt->bind_param('sssiiss', $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $trangThai, $ghiChu);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssiiiss', $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $maGoi, $trangThai, $ghiChu);
    }

    if (!$stmt->execute()) {
        throw new Exception('Không thể thêm lịch khám: ' . $stmt->error);
    }

    $maLichKham = (int)$conn->insert_id;
    $stmt->close();

    // Tạo hóa đơn mặc định nếu lịch có gói khám
    if ($maGoi !== null) {
        $priceStmt = $conn->prepare("SELECT gia FROM goikham WHERE maGoi = ? LIMIT 1");
        $priceStmt->bind_param('i', $maGoi);
        $priceStmt->execute();
        $row = $priceStmt->get_result()->fetch_assoc();
        $priceStmt->close();

        if ($row) {
            $amount = (float)$row['gia'];
            $invoiceStmt = $conn->prepare(
                "INSERT INTO hoadon (maLichKham, soTien, trangThai) VALUES (?, ?, 'Chưa thanh toán')"
            );
            $invoiceStmt->bind_param('id', $maLichKham, $amount);
            $invoiceStmt->execute();
            $invoiceStmt->close();
        }
    }

    $conn->commit();

    try {
        sendAppointmentBookedEmails($conn, $maLichKham);
    } catch (Throwable $mailError) {
        error_log('Admin create appointment mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thêm lịch khám thành công!',
        'maLichKham' => $maLichKham
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
