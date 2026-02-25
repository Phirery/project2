<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

$nguoiDungId = $_SESSION['id'];

try {
    $stmt = $conn->prepare("SELECT ngayCapNhatTaiKhoan FROM nguoidung WHERE id = ?");
    $stmt->bind_param("i", $nguoiDungId);
    $stmt->execute();
    $lastUpdate = $stmt->get_result()->fetch_assoc()['ngayCapNhatTaiKhoan'];
    $stmt->close();
    
    if (!$lastUpdate) {
        echo json_encode(['success' => true, 'canUpdate' => true]);
        exit;
    }
    
    $secondsSinceUpdate = time() - strtotime($lastUpdate);
    $canUpdate = $secondsSinceUpdate >= 60;

    $remainingSeconds = max(0, 60 - $secondsSinceUpdate);

    $remainingTime = sprintf('%d giây', $remainingSeconds);
    
    echo json_encode([
        'success' => true,
        'canUpdate' => $canUpdate,
        'remainingTime' => $remainingTime
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>