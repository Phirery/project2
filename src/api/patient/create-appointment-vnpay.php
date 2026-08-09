<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';
require_once '../../includes/vnpay.php';

require_role('benhnhan');

$data = json_decode(file_get_contents('php://input'), true);

$maBenhNhan = $data['maBenhNhan'] ?? '';
$maBacSi = $data['maBacSi'] ?? '';
$ngayKham = $data['ngayKham'] ?? '';
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;
$maGoi = isset($data['maGoi']) && $data['maGoi'] !== '' ? (int)$data['maGoi'] : 0;
$ghiChu = trim($data['ghiChu'] ?? '');

if (empty($maBenhNhan) || empty($maBacSi) || empty($ngayKham) || $maCa <= 0 || $maSuat <= 0 || $maGoi <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngayKham)) {
    echo json_encode([
        'success' => false,
        'message' => 'Định dạng ngày không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = date('Y-m-d');
if ($ngayKham < $today) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể đặt lịch cho ngày trong quá khứ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isDefaultWorkingScheduleDate($ngayKham)) {
    echo json_encode([
        'success' => false,
        'message' => 'Bác sĩ mặc định nghỉ vào Chủ nhật'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);
    $conn->begin_transaction();

    $doctorStmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM bacsi bs
        JOIN nguoidung nd ON bs.nguoiDungId = nd.id
        WHERE bs.maBacSi = ?
          AND nd.isDeleted = 0
    ");
    $doctorStmt->bind_param('s', $maBacSi);
    $doctorStmt->execute();
    $doctorExists = (int)($doctorStmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $doctorStmt->close();

    if (!$doctorExists) {
        throw new Exception('Bác sĩ không tồn tại hoặc đã ngừng hoạt động');
    }

    $checkOffStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM ngaynghi
        WHERE maBacSi = ? AND ngayNghi = ? AND (maCa = ? OR maCa IS NULL)
    ");
    $checkOffStmt->bind_param('ssi', $maBacSi, $ngayKham, $maCa);
    $checkOffStmt->execute();
    $isDoctorOff = (int)($checkOffStmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $checkOffStmt->close();

    if ($isDoctorOff) {
        throw new Exception('Bác sĩ nghỉ trong ca này. Vui lòng chọn bác sĩ hoặc ca khác!');
    }

    $selectedSlot = getSlotRowById($conn, $maSuat, $ngayKham);
    if (!$selectedSlot || (int)$selectedSlot['maCa'] !== $maCa) {
        throw new Exception('Suất khám không tồn tại hoặc không thuộc ca đã chọn');
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

    $packageRow = getPackageRowById($conn, $maGoi);
    if (!$packageRow || (int)$packageRow['isActive'] !== 1) {
        throw new Exception('Gói khám không tồn tại hoặc đã ngừng áp dụng');
    }

    $appointmentStmt = $conn->prepare("
        INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu)
        VALUES (?, ?, ?, ?, ?, ?, 'Chờ', ?)
    ");
    $appointmentStmt->bind_param('sssiiis', $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $maGoi, $ghiChu);
    if (!$appointmentStmt->execute()) {
        throw new Exception('Không thể tạo lịch giữ chỗ: ' . $appointmentStmt->error);
    }
    $maLichKham = (int)$conn->insert_id;
    $appointmentStmt->close();

    $amount = (float)$packageRow['gia'];
    $invoiceStmt = $conn->prepare("
        INSERT INTO hoadon (maLichKham, soTien, trangThai)
        VALUES (?, ?, 'Chưa thanh toán')
    ");
    $invoiceStmt->bind_param('id', $maLichKham, $amount);
    if (!$invoiceStmt->execute()) {
        throw new Exception('Không thể tạo hóa đơn: ' . $invoiceStmt->error);
    }
    $maHoaDon = (int)$conn->insert_id;
    $invoiceStmt->close();

    $conn->commit();

    $paymentParams = vnpay_build_payment_params([
        'vnp_Amount' => (int)round($amount * 100),
        'vnp_TxnRef' => (string)$maHoaDon,
        'vnp_OrderInfo' => 'Thanh toan lich kham #' . $maHoaDon,
    ]);
    $paymentUrl = vnpay_build_payment_url($paymentParams);

    echo json_encode([
        'success' => true,
        'message' => 'Đã giữ chỗ và tạo URL thanh toán',
        'data' => [
            'maLichKham' => $maLichKham,
            'maHoaDon' => $maHoaDon,
            'soTien' => $amount,
            'paymentUrl' => $paymentUrl,
            'paymentExpiresAt' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))
                ->modify('+' . vnpay_get_hold_minutes() . ' minutes')
                ->format(DateTimeInterface::ATOM),
        ]
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
