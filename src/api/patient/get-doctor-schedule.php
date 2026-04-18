<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../includes/schedule-management.php';

$maBacSi = $_GET['maBacSi'] ?? '';
$ngayKham = $_GET['ngayKham'] ?? '';

if (empty($maBacSi) || empty($ngayKham)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin'
    ]);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);

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
        echo json_encode([
            'success' => false,
            'message' => 'Bác sĩ không tồn tại hoặc đã ngừng hoạt động'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kiểm tra ngày hợp lệ (không quá 14 ngày)
    $timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
    $today = new DateTime('now', $timezone);
    $today->setTime(0, 0, 0); // Đặt về đầu ngày để so sánh chính xác

    $maxDate = (clone $today)->modify('+14 days');

    $checkDate = new DateTime($ngayKham, $timezone);
    $checkDate->setTime(0, 0, 0);

    if ($checkDate < $today || $checkDate > $maxDate) {
        echo json_encode([
            'success' => false,
            'message' => 'Chỉ có thể xem lịch trong vòng 14 ngày tới'
        ]);
        exit;
    }
    
    // Kiểm tra bác sĩ có nghỉ ngày này không
    $offStmt = $conn->prepare("
        SELECT maCa FROM ngaynghi 
        WHERE maBacSi = ? AND ngayNghi = ?
    ");
    $offStmt->bind_param("ss", $maBacSi, $ngayKham);
    $offStmt->execute();
    $offResult = $offStmt->get_result();
    
    $offShifts = [];
    $offAllDay = false;
    
    while ($row = $offResult->fetch_assoc()) {
        if ($row['maCa'] === null) {
            $offAllDay = true;
            break;
        }
        $offShifts[] = (int)$row['maCa'];
    }
    $offStmt->close();
    
    $scheduleSlots = getScheduleSlotsForDate($conn, $ngayKham);
    
    $schedule = [
        'caSang' => [],
        'caChieu' => []
    ];
    
    foreach ($scheduleSlots as $row) {
        $maCa = (int)$row['maCa'];
        $isBooked = findDoctorOverlap(
            $conn,
            $maBacSi,
            $ngayKham,
            $row['gioBatDau'],
            $row['gioKetThuc']
        ) !== null;
        $isDoctorOff = $offAllDay || in_array($maCa, $offShifts);
        
        // Xác định trạng thái
        $status = 'available';
        $reason = null;
        
        if ($isDoctorOff) {
            $status = 'unavailable';
            $reason = 'Bác sĩ nghỉ phép';
        } elseif ($isBooked) {
            $status = 'unavailable';
            $reason = 'Đã có người đặt';
        }
        
        $slot = [
            'maSuat' => (int)$row['maSuat'],
            'gioBatDau' => substr($row['gioBatDau'], 0, 5),
            'gioKetThuc' => substr($row['gioKetThuc'], 0, 5),
            'status' => $status,
            'reason' => $reason
        ];
        
        // Phân loại theo ca
        if ($maCa === 1) {
            $schedule['caSang'][] = $slot;
        } else {
            $schedule['caChieu'][] = $slot;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $schedule,
        'ngayKham' => $ngayKham,
        'offAllDay' => $offAllDay
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
