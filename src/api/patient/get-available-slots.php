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
    
    // Lấy TẤT CẢ các suất khám của ca, kèm trạng thái
    $stmt = $conn->prepare("
        SELECT 
            sk.maSuat, 
            sk.gioBatDau, 
            sk.gioKetThuc,
            CASE 
                -- Nếu bác sĩ nghỉ ca này -> tất cả suất đều unavailable
                WHEN ? = 1 THEN 0
                -- Kiểm tra lịch khám đã đặt có chồng thời gian với slot hiện tại không
                WHEN EXISTS (
                    SELECT 1
                    FROM lichkham lk
                    JOIN suatkham bookedSk ON lk.maSuat = bookedSk.maSuat
                    WHERE lk.maBacSi = ?
                      AND lk.ngayKham = ?
                      AND lk.trangThai != 'Hủy'
                      AND (
                          (bookedSk.gioBatDau >= sk.gioBatDau AND bookedSk.gioBatDau < sk.gioKetThuc)
                          OR (bookedSk.gioKetThuc > sk.gioBatDau AND bookedSk.gioKetThuc <= sk.gioKetThuc)
                          OR (bookedSk.gioBatDau <= sk.gioBatDau AND bookedSk.gioKetThuc >= sk.gioKetThuc)
                      )
                ) THEN 0
                ELSE 1
            END as available
        FROM suatkham sk
        WHERE sk.maCa = ?
          AND sk.isActive = 1
        ORDER BY sk.gioBatDau
    ");
    $stmt->bind_param("issi", $isOff, $maBacSi, $ngayKham, $maCa);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = [
            'maSuat' => $row['maSuat'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc'],
            'available' => (bool)$row['available']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $slots,
        'doctorOff' => $isOff
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
