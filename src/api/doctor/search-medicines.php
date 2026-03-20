<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';

require_role('bacsi');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT maThuoc, tenThuoc, donViTinh, soLuongTon, giaTien, cachDungMacDinh, loaiThuoc,
               COALESCE(nguongCanhBao, 10) AS nguongCanhBao
        FROM thuoc
        WHERE tenThuoc LIKE ? OR loaiThuoc LIKE ?
        ORDER BY
            CASE WHEN soLuongTon <= 0 THEN 1 ELSE 0 END,
            CASE WHEN tenThuoc LIKE ? THEN 0 ELSE 1 END,
            tenThuoc
        LIMIT 10
    ");
    $like = "%$q%";
    $prefix = "$q%";
    $stmt->bind_param('sss', $like, $like, $prefix);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
