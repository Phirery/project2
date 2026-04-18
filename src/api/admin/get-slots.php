<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$maCa = isset($_GET['maCa']) ? intval($_GET['maCa']) : 0;
$includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === '1';

if (!$maCa) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã ca']);
    exit;
}

ensureScheduleManagementSchema($conn);

$sql = "SELECT
            maSuat,
            maCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            isActive
        FROM suatkham
        WHERE maCa = ?" . ($includeInactive ? '' : ' AND isActive = 1') . "
        ORDER BY isActive DESC, gioBatDau, maSuat";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $maCa);
$stmt->execute();
$result = $stmt->get_result();
$slots = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['isActive'] = (int)($row['isActive'] ?? 1);
        $slots[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $slots], JSON_UNESCAPED_UNICODE);
$stmt->close();
$conn->close();
?>
