<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../includes/schedule-management.php';

$maBacSi = $_GET['maBacSi'] ?? '';
$ngayKham = $_GET['ngayKham'] ?? '';
$maCa = $_GET['maCa'] ?? '';

if (empty($maBacSi) || empty($ngayKham) || empty($maCa)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin'
    ]);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);
    $isDefaultOffDay = isScheduleSunday($ngayKham);

    // Bác sĩ đã xóa mềm thì không trả về slot
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

    // Kiểm tra xem bác sĩ có nghỉ ca này không
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM ngaynghi 
        WHERE maBacSi = ? AND ngayNghi = ? AND (maCa = ? OR maCa IS NULL)
    ");
    $checkStmt->bind_param("ssi", $maBacSi, $ngayKham, $maCa);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $isOff = $checkResult->fetch_assoc()['count'] > 0;
    $checkStmt->close();
    
    $slots = array_values(array_filter(
        getScheduleSlotsForDate($conn, $ngayKham),
        function ($slot) use ($maCa) {
            return (int)$slot['maCa'] === (int)$maCa;
        }
    ));
    
    $availableSlots = [];
    foreach ($slots as $row) {
        $isBooked = findDoctorOverlap(
            $conn,
            $maBacSi,
            $ngayKham,
            $row['gioBatDau'],
            $row['gioKetThuc']
        ) !== null;

        $availableSlots[] = [
            'maSuat' => $row['maSuat'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc'],
            'available' => !$isDefaultOffDay && !$isOff && !$isBooked
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $availableSlots,
        'doctorOff' => $isOff || $isDefaultOffDay
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
