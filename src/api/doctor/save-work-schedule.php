<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/schedule-management.php';

require_role('bacsi');

function normalizeScheduleReferenceInput(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return $value . '-01';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    throw new InvalidArgumentException('Định dạng tháng không hợp lệ');
}

function getDoctorCodeForSession(mysqli $conn, int $userId): ?string
{
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['maBacSi'] ?? null;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$referenceInput = (string)($input['month'] ?? $input['thang'] ?? $input['referenceDate'] ?? '');
$selectedCells = $input['selectedCells'] ?? $input['cells'] ?? [];
$reason = trim((string)($input['reason'] ?? $input['lyDo'] ?? ''));

try {
    ensureScheduleManagementSchema($conn);

    $maBacSi = getDoctorCodeForSession($conn, (int)$_SESSION['id']);
    if (!$maBacSi) {
        throw new RuntimeException('Không tìm thấy bác sĩ');
    }

    $referenceDate = normalizeScheduleReferenceInput($referenceInput);
    if (!is_array($selectedCells)) {
        throw new InvalidArgumentException('Danh sách lịch không hợp lệ');
    }

    $plan = buildDoctorMonthlyScheduleUpdate($conn, $maBacSi, $referenceDate, $selectedCells, $reason);

    $conn->begin_transaction();
    $transactionStarted = true;

    $deleteNullStmt = $conn->prepare(
        "DELETE FROM ngaynghi
         WHERE maBacSi = ?
           AND ngayNghi BETWEEN ? AND ?
           AND maCa IS NULL"
    );
    if (!$deleteNullStmt) {
        throw new RuntimeException('Không thể chuẩn bị xóa lịch hiện có');
    }
    $deleteNullStmt->bind_param('sss', $maBacSi, $plan['startDate'], $plan['endDate']);
    if (!$deleteNullStmt->execute()) {
        throw new RuntimeException('Không thể xóa lịch hiện có: ' . $deleteNullStmt->error);
    }
    $deletedRows = (int)$deleteNullStmt->affected_rows;
    $deleteNullStmt->close();
    $insertedRows = 0;

    $existingCounts = [];
    foreach ($plan['leaveRowsByDate'] as $date => $dayState) {
        foreach ($dayState['rows'] as $row) {
            if ($row['maCa'] === null) {
                continue;
            }

            $key = $date . '|' . (int)$row['maCa'];
            $existingCounts[$key] = ($existingCounts[$key] ?? 0) + 1;
        }
    }

    $deleteKeyStmt = $conn->prepare(
        "DELETE FROM ngaynghi
         WHERE maBacSi = ?
           AND ngayNghi = ?
           AND maCa = ?"
    );
    if (!$deleteKeyStmt) {
        throw new RuntimeException('Không thể chuẩn bị cập nhật lịch tháng');
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO ngaynghi (maBacSi, ngayNghi, maCa, lyDo)
         VALUES (?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        throw new RuntimeException('Không thể chuẩn bị lưu lịch tháng');
    }

    foreach ($existingCounts as $key => $count) {
        $desiredCell = $plan['offCells'][$key] ?? null;
        if ($desiredCell !== null && $count === 1) {
            continue;
        }

        [$date, $shiftIdText] = explode('|', $key, 2);
        $shiftId = (int)$shiftIdText;
        $deleteKeyStmt->bind_param('ssi', $maBacSi, $date, $shiftId);
        if (!$deleteKeyStmt->execute()) {
            throw new RuntimeException('Không thể cập nhật lịch tháng: ' . $deleteKeyStmt->error);
        }
        $deletedRows += (int)$deleteKeyStmt->affected_rows;
    }

    foreach ($plan['offCells'] as $key => $cell) {
        if (($existingCounts[$key] ?? 0) === 1) {
            continue;
        }

        $cellDate = (string)$cell['date'];
        $cellShiftId = (int)$cell['maCa'];
        $cellReason = $reason !== '' ? $reason : 'Bác sĩ xếp lại lịch làm việc';
        $insertStmt->bind_param('ssis', $maBacSi, $cellDate, $cellShiftId, $cellReason);
        if (!$insertStmt->execute()) {
            throw new RuntimeException('Không thể lưu lịch tháng: ' . $insertStmt->error);
        }
        $insertedRows++;
    }

    $deleteKeyStmt->close();
    $insertStmt->close();

    $cancelledAppointmentIds = [];
    foreach ($plan['affectedAppointments'] as $appointment) {
        $maLichKham = (int)($appointment['maLichKham'] ?? 0);
        if ($maLichKham <= 0) {
            continue;
        }

        $cancelReason = $plan['cancelReason'];
        $updateStmt = $conn->prepare("
            UPDATE lichkham
            SET trangThai = 'Hủy',
                nguoiHuy = 'bacsi',
                ghiChu = CONCAT(COALESCE(ghiChu, ''), '\n[Lý do hủy]: ', ?)
            WHERE maLichKham = ?
              AND maBacSi = ?
              AND trangThai IN ('Chờ', 'Đã đặt')
        ");
        if (!$updateStmt) {
            throw new RuntimeException('Không thể chuẩn bị hủy lịch khám');
        }
        $updateStmt->bind_param('sis', $cancelReason, $maLichKham, $maBacSi);
        if (!$updateStmt->execute()) {
            throw new RuntimeException('Không thể hủy lịch khám bị ảnh hưởng: ' . $updateStmt->error);
        }

        if ($updateStmt->affected_rows > 0) {
            $cancelledAppointmentIds[] = $maLichKham;
        }
        $updateStmt->close();
    }

    $conn->commit();

    $mailSummary = [
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0
    ];

    foreach ($cancelledAppointmentIds as $appointmentId) {
        try {
            $mailSummary['attempted']++;
            $mailResult = sendAppointmentCancelledEmails($conn, $appointmentId, 'bacsi', $plan['cancelReason']);
            $patientMail = $mailResult['results']['patient'] ?? null;

            if ($patientMail && !empty($patientMail['success'])) {
                $mailSummary['sent']++;
            } elseif ($patientMail && (($patientMail['reason'] ?? '') === 'send_failed')) {
                $mailSummary['failed']++;
            } else {
                $mailSummary['skipped']++;
            }
        } catch (Throwable $mailError) {
            $mailSummary['failed']++;
            error_log('Save work schedule mail error: ' . $mailError->getMessage());
        }
    }

    $freshSchedule = buildDoctorMonthlySchedule($conn, $maBacSi, $referenceDate);
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật lịch làm việc thành công!',
        'data' => [
            'schedule' => $freshSchedule,
            'affectedAppointments' => count($cancelledAppointmentIds),
            'affectedPreview' => array_slice($plan['affectedAppointments'], 0, 5),
            'mailSummary' => $mailSummary,
            'deletedRows' => $deletedRows,
            'insertedRows' => $insertedRows
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
