<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

ensureScheduleManagementSchema($conn);

$includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === '1';
$sql = "SELECT maGoi, tenGoi, moTa, thoiLuong, gia, isActive
        FROM goikham" . ($includeInactive ? '' : ' WHERE isActive = 1') . "
        ORDER BY isActive DESC, gia, maGoi";
$result = $conn->query($sql);
$packages = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['isActive'] = (int)($row['isActive'] ?? 1);
        $packages[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $packages], JSON_UNESCAPED_UNICODE);
$conn->close();
?>
