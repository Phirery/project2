<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/db.php';
require_role('quantri');

try {
    $today    = date('Y-m-d');
    $plus30   = date('Y-m-d', strtotime('+30 days'));

    $total = (int)$conn->query("SELECT COUNT(*) FROM thuoc")->fetch_row()[0];

    $lowStock = (int)$conn->query(
        "SELECT COUNT(*) FROM thuoc WHERE soLuongTon <= COALESCE(nguongCanhBao, 10)"
    )->fetch_row()[0];

    $expiring = (int)$conn->query(
        "SELECT COUNT(*) FROM thuoc WHERE hanSuDung IS NOT NULL AND hanSuDung BETWEEN '$today' AND '$plus30'"
    )->fetch_row()[0];

    $categories = (int)$conn->query(
        "SELECT COUNT(DISTINCT loaiThuoc) FROM thuoc WHERE loaiThuoc IS NOT NULL AND loaiThuoc != ''"
    )->fetch_row()[0];

    echo json_encode([
        'success'    => true,
        'total'      => $total,
        'lowStock'   => $lowStock,
        'expiring'   => $expiring,
        'categories' => $categories,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}