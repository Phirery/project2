<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
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

$monthInput = $_GET['month'] ?? $_GET['thang'] ?? $_GET['referenceDate'] ?? '';

$stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
$stmt->close();

if (!$maBacSi) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
    exit;
}

try {
    ensureScheduleManagementSchema($conn);
    $referenceDate = normalizeScheduleReferenceInput((string)$monthInput);
    $payload = getDoctorMonthlySchedulePayload($conn, (string)$maBacSi, $referenceDate);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
