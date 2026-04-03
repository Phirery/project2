<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/db.php';
require_role('quantri');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo json_encode(['success' => true, 'data' => []]); exit; }

try {
    $stmt = $conn->prepare(
        "SELECT maThuoc, tenThuoc, donViTinh, soLuongTon, loaiThuoc
         FROM thuoc WHERE tenThuoc LIKE ? ORDER BY tenThuoc LIMIT 10"
    );
    $like = "%$q%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}