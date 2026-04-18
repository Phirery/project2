<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/schedule-management.php';

require_role('benhnhan');

$data = json_decode(file_get_contents('php://input'), true);

$maBenhNhan = $data['maBenhNhan'] ?? '';
$maBacSi = $data['maBacSi'] ?? '';
$ngayKham = $data['ngayKham'] ?? '';
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;
$maGoi = isset($data['maGoi']) && $data['maGoi'] !== '' ? (int)$data['maGoi'] : null;
$ghiChu = trim($data['ghiChu'] ?? '');

// Validation
if (empty($maBenhNhan) || empty($maBacSi) || empty($ngayKham) || $maCa <= 0 || $maSuat <= 0) {
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
    ensureScheduleManagementSchema($conn);
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
    
    $selectedSlot = getSlotRowById($conn, $maSuat, $ngayKham);
    if (!$selectedSlot || $selectedSlot['maCa'] !== $maCa) {
        throw new Exception('Suất khám không tồn tại hoặc không thuộc ca đã chọn');
    }

    if ((int)$selectedSlot['isActive'] !== 1) {
        throw new Exception('Suất khám này không còn được áp dụng. Vui lòng tải lại và chọn suất mới');
    }

    $doctorConflict = findDoctorOverlap(
        $conn,
        $maBacSi,
        $ngayKham,
        $selectedSlot['gioBatDau'],
        $selectedSlot['gioKetThuc']
    );
    if ($doctorConflict) {
        throw new Exception('Suất khám này đã được đặt (Mã lịch: ' . $doctorConflict['maLichKham'] . '). Vui lòng chọn suất khác!');
    }

    $patientConflict = findPatientOverlap(
        $conn,
        $maBenhNhan,
        $ngayKham,
        $selectedSlot['gioBatDau'],
        $selectedSlot['gioKetThuc']
    );
    if ($patientConflict) {
        throw new Exception('Bạn đã có lịch khám trùng giờ (Mã lịch: ' . $patientConflict['maLichKham'] . '). Vui lòng chọn giờ khác!');
    }

    $packageRow = null;
    if ($maGoi !== null) {
        $packageRow = getPackageRowById($conn, $maGoi);
        if (!$packageRow || (int)$packageRow['isActive'] !== 1) {
            throw new Exception('Gói khám không tồn tại hoặc đã ngừng áp dụng');
        }
    }
    
    // 4. Thêm lịch khám
    if ($maGoi === null) {
        $stmt = $conn->prepare("
            INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
            VALUES (?, ?, ?, ?, ?, NULL, 'Đã đặt', ?)
        ");
        $stmt->bind_param("sssiis", $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $ghiChu);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
            VALUES (?, ?, ?, ?, ?, ?, 'Đã đặt', ?)
        ");
        $stmt->bind_param("sssiiis", $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $maGoi, $ghiChu);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Không thể tạo lịch khám: ' . $stmt->error);
    }
    
    $maLichKham = $conn->insert_id;
    $stmt->close();

    // 5. Tạo hóa đơn mặc định (nếu lịch khám có gói và chưa có hóa đơn)
    if ($packageRow) {
            $checkInvoiceStmt = $conn->prepare("SELECT maHoaDon FROM hoadon WHERE maLichKham = ? LIMIT 1");
            $checkInvoiceStmt->bind_param("i", $maLichKham);
            $checkInvoiceStmt->execute();
            $invoiceExists = $checkInvoiceStmt->get_result()->num_rows > 0;
            $checkInvoiceStmt->close();

            if (!$invoiceExists) {
                $amount = (float)$packageRow['gia'];
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
