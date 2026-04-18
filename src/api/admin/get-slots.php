<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('quantri');

$maCa = isset($_GET['maCa']) ? intval($_GET['maCa']) : 0;
$ngayKham = $_GET['ngayKham'] ?? null;
$includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === '1';

if (!$maCa) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã ca']);
    exit;
}

ensureScheduleManagementSchema($conn);

$allSlots = getScheduleSlotsForDate($conn, $ngayKham);
$slots = array_values(array_filter($allSlots, function ($slot) use ($maCa) {
    return (int)$slot['maCa'] === (int)$maCa;
}));
$slotsOut = [];

foreach ($slots as $row) {
    if (!$includeInactive && (int)($row['isActive'] ?? 1) !== 1) {
        continue;
    }

    $row['isActive'] = (int)($row['isActive'] ?? 1);
    $row['effectiveFrom'] = $row['effectiveFrom'] ?? null;
    $row['effectiveTo'] = $row['effectiveTo'] ?? null;
    $slotsOut[] = $row;
}

echo json_encode(['success' => true, 'data' => $slotsOut ?? []], JSON_UNESCAPED_UNICODE);
$conn->close();
?>
