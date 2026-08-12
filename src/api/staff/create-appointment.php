<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/schedule-management.php';

require_role('nhanvien');

/**
 * Tạo lịch khám cho bệnh nhân đến trực tiếp (walk-in, Case A/B) với 1 slot còn trống
 * trong ca hiện tại. Nếu hết slot -> báo lỗi để nhân viên báo bệnh nhân quay lại
 * buổi chiều / chờ (không tạo "lịch chờ" không có slot).
 */

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$maBenhNhan = trim($data['maBenhNhan'] ?? '');
$maBacSi = trim($data['maBacSi'] ?? '');
$ngayKham = trim($data['ngayKham'] ?? '');
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;
$ghiChu = trim($data['ghiChu'] ?? '');

if ($maBenhNhan === '' || $maBacSi === '' || $ngayKham === '' || $maCa <= 0 || $maSuat <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Chỉ cho tạo lịch walk-in cho hôm nay
if ($ngayKham !== date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Lịch tiếp nhận trực tiếp chỉ áp dụng cho ngày hôm nay'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);

    // Lấy mã nhân viên đang thực hiện (để lưu maNhanVienTao)
    $staffStmt = $conn->prepare("SELECT maNhanVien FROM nhanvien WHERE nguoiDungId = ?");
    $staffStmt->bind_param('i', $_SESSION['id']);
    $staffStmt->execute();
    $staffRow = $staffStmt->get_result()->fetch_assoc();
    $staffStmt->close();
    if (!$staffRow) {
        throw new Exception('Không xác định được nhân viên thực hiện');
    }
    $maNhanVienTao = $staffRow['maNhanVien'];

    $conn->begin_transaction();

    $selectedSlot = getSlotRowById($conn, $maSuat, $ngayKham);
    if (!$selectedSlot || $selectedSlot['maCa'] !== $maCa) {
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
        throw new Exception('Đã hết chỗ ở khung giờ này. Vui lòng báo bệnh nhân quay lại buổi chiều hoặc chờ (Mã lịch trùng: ' . $doctorConflict['maLichKham'] . ')');
    }

    $patientConflict = findPatientOverlap(
        $conn,
        $maBenhNhan,
        $ngayKham,
        $selectedSlot['gioBatDau'],
        $selectedSlot['gioKetThuc']
    );
    if ($patientConflict) {
        throw new Exception('Bệnh nhân đã có lịch trùng giờ (Mã lịch: ' . $patientConflict['maLichKham'] . ')');
    }

    $trangThai = 'Đã đặt';
    $nguon = 'truc_tiep';

    $stmt = $conn->prepare(
        "INSERT INTO lichkham (maBenhNhan, maBacSi, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu, nguon, maNhanVienTao)
         VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssiissss', $maBenhNhan, $maBacSi, $ngayKham, $maCa, $maSuat, $trangThai, $ghiChu, $nguon, $maNhanVienTao);

    if (!$stmt->execute()) {
        throw new Exception('Không thể thêm lịch khám: ' . $stmt->error);
    }

    $maLichKham = (int)$conn->insert_id;
    $stmt->close();

    $conn->commit();

    try {
        sendAppointmentBookedEmails($conn, $maLichKham);
    } catch (Throwable $mailError) {
        error_log('Staff create-appointment mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tạo lịch khám thành công!',
        'maLichKham' => $maLichKham
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>