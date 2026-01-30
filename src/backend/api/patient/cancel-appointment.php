<?php
require_once '../../config/cors.php';
require_once '../../core/dp.php';
require_once '../../core/session.php';

require_role('benhnhan');

$data = json_decode(file_get_contents('php://input'), true);

$maLichKham = $data['maLichKham'] ?? '';
$lyDo = trim($data['lyDo'] ?? '');

// Validation
if (empty($maLichKham) || empty($lyDo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin hoặc lý do hủy lịch'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1. Lấy thông tin lịch khám
    $stmt = $conn->prepare("
        SELECT 
            lk.maBenhNhan,
            lk.ngayKham,
            lk.trangThai,
            sk.gioBatDau
        FROM lichkham lk
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE lk.maLichKham = ?
    ");
    $stmt->bind_param("i", $maLichKham);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Không tìm thấy lịch khám');
    }
    
    $appointment = $result->fetch_assoc();
    $stmt->close();
    
    // Kiểm tra quyền sở hữu lịch khám
    $checkOwnerStmt = $conn->prepare("
        SELECT maBenhNhan 
        FROM benhnhan 
        WHERE nguoiDungId = ?
    ");
    $checkOwnerStmt->bind_param("i", $_SESSION['id']);
    $checkOwnerStmt->execute();
    $ownerResult = $checkOwnerStmt->get_result();
    $owner = $ownerResult->fetch_assoc();
    $checkOwnerStmt->close();
    
    if (!$owner || $owner['maBenhNhan'] !== $appointment['maBenhNhan']) {
        throw new Exception('Bạn không có quyền hủy lịch khám này');
    }
    
    // Kiểm tra trạng thái
    if ($appointment['trangThai'] === 'Hủy') {
        throw new Exception('Lịch khám này đã được hủy trước đó');
    }
    
    if ($appointment['trangThai'] === 'Hoàn thành') {
        throw new Exception('Không thể hủy lịch khám đã hoàn thành');
    }
    
    // 2. Tính thời gian còn lại đến lịch khám
    $appointmentDateTime = new DateTime($appointment['ngayKham'] . ' ' . $appointment['gioBatDau']);
    $currentDateTime = new DateTime();
    $hoursDiff = ($appointmentDateTime->getTimestamp() - $currentDateTime->getTimestamp()) / 3600;
    
    // 3. Cập nhật trạng thái lịch khám thành 'Hủy' và set nguoiHuy = 'benhnhan'
    $updateStmt = $conn->prepare("
        UPDATE lichkham 
        SET trangThai = 'Hủy',
            nguoiHuy = 'benhnhan',
            ghiChu = CONCAT(COALESCE(ghiChu, ''), '\n[Lý do hủy]: ', ?)
        WHERE maLichKham = ?
    ");
    
    // Chú ý: Ở đây ta gán cứng 'benhnhan' trong SQL nên chỉ cần bind lý do và mã lịch
    $updateStmt->bind_param("si", $lyDo, $maLichKham);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Không thể cập nhật trạng thái lịch khám');
    }
    $updateStmt->close();
    
    // 4. Thông báo: Đã được xử lý tự động bởi Trigger (check cột nguoiHuy)
    
    // 5. Kiểm tra xem có cần khôi phục suất khám không (hủy trước 12h)
    $shouldRestore = $hoursDiff >= 12;
    $restoreMessage = '';
    
    if ($shouldRestore) {
        $restoreMessage = ' Suất khám đã được khôi phục để người khác có thể đặt.';
    } else {
        $restoreMessage = ' Suất khám không được khôi phục do hủy trong vòng 12 giờ.';
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Hủy lịch khám thành công!' . $restoreMessage,
        'restored' => $shouldRestore,
        'hoursDiff' => round($hoursDiff, 1)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>