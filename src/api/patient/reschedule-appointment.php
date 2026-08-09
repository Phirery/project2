<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';
require_once '../../includes/mail-events.php';

require_role('benhnhan');

$data = json_decode(file_get_contents('php://input'), true);

$maLichKham = isset($data['maLichKham']) ? (int)$data['maLichKham'] : 0;
$ngayKham = trim((string)($data['ngayKham'] ?? ''));
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;

if ($maLichKham <= 0 || $ngayKham === '' || $maCa <= 0 || $maSuat <= 0) {
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

function getPatientCode(mysqli $conn, int $userId): string
{
    $stmt = $conn->prepare("
        SELECT maBenhNhan
        FROM benhnhan
        WHERE nguoiDungId = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn thông tin bệnh nhân');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient || empty($patient['maBenhNhan'])) {
        throw new Exception('Không tìm thấy thông tin bệnh nhân');
    }

    return (string)$patient['maBenhNhan'];
}

try {
    ensureScheduleManagementSchema($conn);

    $patientId = getPatientCode($conn, (int)$_SESSION['id']);
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $now = new DateTimeImmutable('now', $tz);
    $cutoffHours = getAppointmentRescheduleLimitHours();
    $today = $now->format('Y-m-d');

    if ($ngayKham < $today) {
        throw new Exception('Không thể đổi lịch sang ngày trong quá khứ');
    }

    if (!isDefaultWorkingScheduleDate($ngayKham)) {
        throw new Exception('Bác sĩ mặc định nghỉ vào Chủ nhật');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT
            lk.maLichKham,
            lk.maBenhNhan,
            lk.maBacSi,
            lk.ngayKham,
            lk.maCa,
            lk.maSuat,
            lk.maGoi,
            lk.trangThai,
            lk.soLanDoiLich,
            TIME_FORMAT(sk.gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            ca.tenCa
        FROM lichkham lk
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        WHERE lk.maLichKham = ?
        LIMIT 1
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn lịch khám');
    }

    $stmt->bind_param('i', $maLichKham);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        throw new Exception('Không tìm thấy lịch khám');
    }

    if ((string)$appointment['maBenhNhan'] !== (string)$patientId) {
        throw new Exception('Bạn không có quyền đổi lịch khám này');
    }

    if (($appointment['trangThai'] ?? '') !== 'Đã đặt') {
        throw new Exception('Chỉ có thể đổi các lịch đang ở trạng thái Đã đặt');
    }

    if ((int)($appointment['soLanDoiLich'] ?? 0) >= 1) {
        throw new Exception('Lịch này chỉ được đổi 1 lần');
    }

    $originalStart = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string)$appointment['ngayKham'] . ' ' . substr((string)$appointment['gioBatDau'], 0, 8),
        $tz
    );
    if (!$originalStart) {
        throw new Exception('Không thể xác định thời gian lịch khám hiện tại');
    }

    $remainingMinutes = (int)floor(($originalStart->getTimestamp() - $now->getTimestamp()) / 60);
    if ($remainingMinutes < ($cutoffHours * 60)) {
        throw new Exception('Chỉ được đổi lịch trước ' . $cutoffHours . ' giờ so với giờ khám');
    }

    if ($appointment['ngayKham'] === $ngayKham && (int)$appointment['maCa'] === $maCa && (int)$appointment['maSuat'] === $maSuat) {
        throw new Exception('Lịch mới phải khác lịch hiện tại');
    }

    $selectedSlot = getSlotRowById($conn, $maSuat, $ngayKham);
    if (!$selectedSlot || (int)$selectedSlot['maCa'] !== $maCa) {
        throw new Exception('Suất khám không tồn tại hoặc không thuộc ca đã chọn');
    }

    $doctorConflict = findDoctorOverlap(
        $conn,
        (string)$appointment['maBacSi'],
        $ngayKham,
        $selectedSlot['gioBatDau'],
        $selectedSlot['gioKetThuc'],
        $maLichKham
    );
    if ($doctorConflict) {
        throw new Exception('Suất khám này đã được đặt (Mã lịch: ' . $doctorConflict['maLichKham'] . '). Vui lòng chọn suất khác!');
    }

    $patientConflict = findPatientOverlap(
        $conn,
        (string)$patientId,
        $ngayKham,
        $selectedSlot['gioBatDau'],
        $selectedSlot['gioKetThuc'],
        $maLichKham
    );
    if ($patientConflict) {
        throw new Exception('Bạn đã có lịch khám trùng giờ (Mã lịch: ' . $patientConflict['maLichKham'] . '). Vui lòng chọn giờ khác!');
    }

    $newStart = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $ngayKham . ' ' . substr((string)$selectedSlot['gioBatDau'], 0, 8),
        $tz
    );
    if (!$newStart) {
        throw new Exception('Không thể xác định thời gian lịch mới');
    }

    if ($newStart->getTimestamp() <= $now->getTimestamp()) {
        throw new Exception('Không thể đổi lịch sang thời gian trong quá khứ');
    }

    $oldContext = getAppointmentMailContext($conn, $maLichKham);

    $updateStmt = $conn->prepare("
        UPDATE lichkham
        SET ngayKham = ?,
            maCa = ?,
            maSuat = ?,
            soLanDoiLich = COALESCE(soLanDoiLich, 0) + 1,
            thoiGianDoiLich = NOW()
        WHERE maLichKham = ?
          AND maBenhNhan = ?
          AND trangThai = 'Đã đặt'
          AND COALESCE(soLanDoiLich, 0) = 0
    ");
    if (!$updateStmt) {
        throw new Exception('Không thể chuẩn bị cập nhật lịch khám');
    }

    $updateStmt->bind_param('siiis', $ngayKham, $maCa, $maSuat, $maLichKham, $patientId);
    $updateStmt->execute();

    if ($updateStmt->affected_rows <= 0) {
        $updateStmt->close();
        throw new Exception('Không thể cập nhật lịch khám hoặc lịch đã được đổi trước đó');
    }
    $updateStmt->close();

    $conn->commit();

    try {
        if ($oldContext) {
            sendAppointmentRescheduledEmails($conn, $maLichKham, $oldContext, 'benhnhan');
        }
    } catch (Throwable $mailError) {
        error_log('Patient reschedule mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đổi lịch thành công!',
        'data' => [
            'maLichKham' => $maLichKham,
            'ngayKham' => $ngayKham,
            'maCa' => $maCa,
            'maSuat' => $maSuat,
            'gioBatDau' => substr((string)$selectedSlot['gioBatDau'], 0, 5),
            'gioKetThuc' => substr((string)$selectedSlot['gioKetThuc'], 0, 5)
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
