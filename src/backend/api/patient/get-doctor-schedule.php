<?php
require_once '../../config/cors.php';
require_once '../../core/dp.php';

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
    
    // Lấy TẤT CẢ suất khám (12 suất: 6 sáng + 6 chiều)
    $stmt = $conn->prepare("
        SELECT 
            sk.maSuat,
            sk.maCa,
            sk.gioBatDau,
            sk.gioKetThuc,
            c.tenCa,
            lk.maLichKham,
            lk.trangThai
        FROM suatkham sk
        JOIN calamviec c ON sk.maCa = c.maCa
        LEFT JOIN lichkham lk ON (
            lk.maBacSi = ? AND 
            lk.ngayKham = ? AND 
            lk.maSuat = sk.maSuat AND
            lk.trangThai != 'Hủy'
        )
        ORDER BY sk.maCa, sk.gioBatDau
    ");
    $stmt->bind_param("ss", $maBacSi, $ngayKham);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedule = [
        'caSang' => [],
        'caChieu' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $maCa = (int)$row['maCa'];
        $isBooked = !empty($row['maLichKham']);
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
    
    $stmt->close();
    
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