<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('benhnhan');

$data = json_decode(file_get_contents('php://input'), true);

$maBenhNhan = $data['maBenhNhan'] ?? '';
$maBacSi = $data['maBacSi'] ?? '';
$ngayKham = $data['ngayKham'] ?? '';
$maCa = $data['maCa'] ?? '';
$maSuat = $data['maSuat'] ?? '';
$maGoi = $data['maGoi'] ?? '';
$ghiChu = $data['ghiChu'] ?? '';

// Validation
if (empty($maBenhNhan) || empty($maBacSi) || empty($ngayKham) || empty($maCa) || empty($maSuat)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate ngày khám phải là ngày hợp lệ
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngayKham)) {
    echo json_encode([
        'success' => false,
        'message' => 'Định dạng ngày không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate ngày khám không được là ngày quá khứ
$today = date('Y-m-d');
if ($ngayKham < $today) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể đặt lịch cho ngày trong quá khứ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->begin_transaction();

    // 0. Kiểm tra bác sĩ còn hoạt động
    $doctorStmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM bacsi bs
        JOIN nguoidung nd ON bs.nguoiDungId = nd.id
        WHERE bs.maBacSi = ?
          AND nd.isDeleted = 0
    ");
    $doctorStmt->bind_param("s", $maBacSi);
    $doctorStmt->execute();
    $doctorExists = (int)($doctorStmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $doctorStmt->close();

    if (!$doctorExists) {
        throw new Exception('Bác sĩ không tồn tại hoặc đã ngừng hoạt động');
    }
    
    // 1. Kiểm tra bác sĩ có nghỉ ca này không
    $checkOffStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM ngaynghi 
        WHERE maBacSi = ? AND ngayNghi = ? AND (maCa = ? OR maCa IS NULL)
    ");
    $checkOffStmt->bind_param("ssi", $maBacSi, $ngayKham, $maCa);
    $checkOffStmt->execute();
    $checkOffResult = $checkOffStmt->get_result();
    $isDoctorOff = $checkOffResult->fetch_assoc()['count'] > 0;
    $checkOffStmt->close();
    
    if ($isDoctorOff) {
        throw new Exception('Bác sĩ nghỉ trong ca này. Vui lòng chọn bác sĩ hoặc ca khác!');
    }
    
    // 2. Kiểm tra suất khám có bị trùng KHÔNG (bất kể gói khám)
    // Quan trọng: Chỉ cần maBacSi + ngayKham + maSuat trùng là không cho đặt
    $checkStmt = $conn->prepare("
        SELECT maLichKham, maGoi, trangThai
        FROM lichkham 
        WHERE maBacSi = ? 
        AND ngayKham = ? 
        AND maSuat = ? 
        AND trangThai != 'Hủy'
    ");
    $checkStmt->bind_param("ssi", $maBacSi, $ngayKham, $maSuat);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $existingBooking = $checkResult->fetch_assoc();
        $checkStmt->close();
        throw new Exception('Suất khám này đã được đặt (Mã lịch: ' . $existingBooking['maLichKham'] . '). Vui lòng chọn suất khác!');
    }
    $checkStmt->close();
    
    // 3. Kiểm tra bệnh nhân đã đặt lịch trùng thời gian chưa
    // (Một bệnh nhân không thể đặt 2 lịch cùng lúc)
    $checkPatientStmt = $conn->prepare("
        SELECT lk.maLichKham
        FROM lichkham lk
        JOIN suatkham sk1 ON lk.maSuat = sk1.maSuat
        JOIN suatkham sk2 ON sk2.maSuat = ?
        WHERE lk.maBenhNhan = ?
        AND lk.ngayKham = ?
        AND lk.trangThai != 'Hủy'
        AND (
            (sk1.gioBatDau >= sk2.gioBatDau AND sk1.gioBatDau < sk2.gioKetThuc)
            OR (sk1.gioKetThuc > sk2.gioBatDau AND sk1.gioKetThuc <= sk2.gioKetThuc)
            OR (sk1.gioBatDau <= sk2.gioBatDau AND sk1.gioKetThuc >= sk2.gioKetThuc)
        )
    ");
    $checkPatientStmt->bind_param("iss", $maSuat, $maBenhNhan, $ngayKham);
    $checkPatientStmt->execute();
    $checkPatientResult = $checkPatientStmt->get_result();
    
    if ($checkPatientResult->num_rows > 0) {
        $conflictBooking = $checkPatientResult->fetch_assoc();
        $checkPatientStmt->close();
        throw new Exception('Bạn đã có lịch khám trùng giờ (Mã lịch: ' . $conflictBooking['maLichKham'] . '). Vui lòng chọn giờ khác!');
    }
    $checkPatientStmt->close();
    
    // 4. Thêm lịch khám
    $stmt = $conn->prepare("
        INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
        VALUES (?, ?, ?, ?, ?, ?, 'Đã đặt', ?)
    ");
    $stmt->bind_param("sssiiss", $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $maGoi, $ghiChu);
    
    if (!$stmt->execute()) {
        throw new Exception('Không thể tạo lịch khám: ' . $stmt->error);
    }
    
    $maLichKham = $conn->insert_id;
    $stmt->close();

    // 5. Tạo hóa đơn mặc định (nếu lịch khám có gói và chưa có hóa đơn)
    if (!empty($maGoi)) {
        $priceStmt = $conn->prepare("SELECT gia FROM goikham WHERE maGoi = ? LIMIT 1");
        $priceStmt->bind_param("i", $maGoi);
        $priceStmt->execute();
        $priceRow = $priceStmt->get_result()->fetch_assoc();
        $priceStmt->close();

        if ($priceRow) {
            $checkInvoiceStmt = $conn->prepare("SELECT maHoaDon FROM hoadon WHERE maLichKham = ? LIMIT 1");
            $checkInvoiceStmt->bind_param("i", $maLichKham);
            $checkInvoiceStmt->execute();
            $invoiceExists = $checkInvoiceStmt->get_result()->num_rows > 0;
            $checkInvoiceStmt->close();

            if (!$invoiceExists) {
                $amount = (float)$priceRow['gia'];
                $invoiceStmt = $conn->prepare("
                    INSERT INTO hoadon (maLichKham, soTien, trangThai)
                    VALUES (?, ?, 'Chưa thanh toán')
                ");
                $invoiceStmt->bind_param("id", $maLichKham, $amount);
                if (!$invoiceStmt->execute()) {
                    throw new Exception('Không thể tạo hóa đơn: ' . $invoiceStmt->error);
                }
                $invoiceStmt->close();
            }
        }
    }
    
    $conn->commit();

    // 6. Gửi mail giao dịch (không chặn luồng chính nếu gửi thất bại)
    try {
        sendAppointmentBookedEmails($conn, (int)$maLichKham);
    } catch (Throwable $mailError) {
        error_log('Appointment booked mail error: ' . $mailError->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Đặt lịch thành công!',
        'maLichKham' => $maLichKham
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
