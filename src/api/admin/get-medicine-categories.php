<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';
require_role('quantri');

try {
    $result = $conn->query(
        "SELECT DISTINCT loaiThuoc FROM thuoc
         WHERE loaiThuoc IS NOT NULL AND loaiThuoc != ''
         ORDER BY loaiThuoc"
    );
    $cats = [];
    while ($row = $result->fetch_row()) $cats[] = $row[0];
    echo json_encode(['success' => true, 'data' => $cats]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}