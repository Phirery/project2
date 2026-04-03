<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/db.php';
require_role('quantri');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['maThuoc'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Thiếu mã thuốc']); exit; }

try {
    // Kiểm tra ràng buộc khóa ngoại (chi tiết đơn thuốc)
    $check = $conn->prepare("SELECT COUNT(*) FROM chitietdonthuoc WHERE maThuoc = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $count = (int)$check->get_result()->fetch_row()[0];
    if ($count > 0) {
        echo json_encode(['success'=>false,'message'=>'Không thể xóa: thuốc đang được sử dụng trong đơn thuốc']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM thuoc WHERE maThuoc = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0)
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy thuốc']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}