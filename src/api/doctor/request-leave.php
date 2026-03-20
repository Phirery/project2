<?php
// request-leave.php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('bacsi');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['ngayNghi']) || !isset($input['lyDo'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

// Lấy mã bác sĩ từ session ID
$stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
$stmt->close();

if (!$maBacSi) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
    exit;
}

$ngayNghi = $input['ngayNghi'];
$maCa = $input['maCa']; // null nếu nghỉ cả ngày
$lyDo = trim($input['lyDo']);

$tomorrow = date('Y-m-d', strtotime('+1 day'));
if ($ngayNghi < $tomorrow) {
    echo json_encode(['success' => false, 'message' => 'Chỉ được xin nghỉ từ ngày mai trở đi']);
    exit;
}

try {
    $conn->begin_transaction();

    $cancelReason = $lyDo !== ''
        ? 'Bác sĩ nghỉ phép/bận việc: ' . $lyDo
        : 'Bác sĩ nghỉ phép';

    $affectedAppointments = [];
    $previewSql = "
        SELECT
            lk.maLichKham,
            bn.tenBenhNhan,
            ca.tenCa,
            sk.gioBatDau,
            sk.gioKetThuc
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE lk.maBacSi = ?
          AND lk.ngayKham = ?
          AND lk.trangThai IN ('Chờ', 'Đã đặt')
          " . ($maCa ? " AND lk.maCa = ?" : "") . "
        ORDER BY lk.maCa ASC, sk.gioBatDau ASC, lk.maLichKham ASC
    ";

    $previewStmt = $conn->prepare($previewSql);
    if ($maCa) {
        $previewStmt->bind_param("ssi", $maBacSi, $ngayNghi, $maCa);
    } else {
        $previewStmt->bind_param("ss", $maBacSi, $ngayNghi);
    }
    $previewStmt->execute();
    $previewResult = $previewStmt->get_result();
    while ($row = $previewResult->fetch_assoc()) {
        $affectedAppointments[] = $row;
    }
    $previewStmt->close();
    
    // Insert vào bảng ngaynghi
    // Trigger 'after_ngaynghi_insert' sẽ tự động tạo thông báo Admin
    if ($maCa === null) {
        // Nghỉ cả ngày (Ca 1 và Ca 2)
        $stmt = $conn->prepare("
            INSERT INTO ngaynghi (maBacSi, ngayNghi, maCa, lyDo) 
            VALUES (?, ?, 1, ?), (?, ?, 2, ?)
        ");
        $stmt->bind_param("ssssss", $maBacSi, $ngayNghi, $lyDo, $maBacSi, $ngayNghi, $lyDo);
    } else {
        // Nghỉ 1 ca
        $stmt = $conn->prepare("
            INSERT INTO ngaynghi (maBacSi, ngayNghi, maCa, lyDo) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssis", $maBacSi, $ngayNghi, $maCa, $lyDo);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Không thể tạo đơn nghỉ phép: ' . $stmt->error);
    }
    $stmt->close();
    
    $cancelledAppointmentIds = [];
    foreach ($affectedAppointments as $appointment) {
        $maLichKham = (int)($appointment['maLichKham'] ?? 0);
        if ($maLichKham <= 0) {
            continue;
        }

        $stmt = $conn->prepare("
            UPDATE lichkham
            SET trangThai = 'Hủy',
                nguoiHuy = 'bacsi',
                ghiChu = CONCAT(COALESCE(ghiChu, ''), '\n[Lý do hủy]: ', ?)
            WHERE maLichKham = ?
              AND maBacSi = ?
              AND trangThai IN ('Chờ', 'Đã đặt')
        ");
        $stmt->bind_param("sis", $cancelReason, $maLichKham, $maBacSi);

        if (!$stmt->execute()) {
            throw new Exception('Không thể hủy lịch khám bị ảnh hưởng: ' . $stmt->error);
        }

        if ($stmt->affected_rows > 0) {
            $cancelledAppointmentIds[] = $maLichKham;
        }
        $stmt->close();
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
            $mailResult = sendAppointmentCancelledEmails($conn, $appointmentId, 'bacsi', $cancelReason);
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
            error_log('Leave request cancel mail error: ' . $mailError->getMessage());
        }
    }
    
    $cancelledCount = count($cancelledAppointmentIds);
    $message = 'Gửi yêu cầu nghỉ phép thành công!';
    if ($cancelledCount > 0) {
        $message .= " Đã hủy $cancelledCount lịch khám bị ảnh hưởng và gửi thông báo cho bệnh nhân.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'affectedAppointments' => $cancelledCount,
        'affectedPreview' => array_slice($affectedAppointments, 0, 5),
        'cancelReason' => $cancelReason,
        'mailSummary' => $mailSummary
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
