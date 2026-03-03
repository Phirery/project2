<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';
require_role('quantri');

try {
    $page     = max(1, (int)($_GET['page']  ?? 1));
    $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset   = ($page - 1) * $limit;
    $search   = trim($_GET['search']   ?? '');
    $loai     = trim($_GET['loai']     ?? '');
    $lowStock = isset($_GET['lowStock']) && $_GET['lowStock'] === '1';

    $where  = [];
    $params = [];
    $types  = '';

    if ($search !== '') {
        $where[]  = 'tenThuoc LIKE ?';
        $params[] = "%$search%";
        $types   .= 's';
    }
    if ($loai !== '') {
        $where[]  = 'loaiThuoc = ?';
        $params[] = $loai;
        $types   .= 's';
    }
    if ($lowStock) {
        $where[] = 'soLuongTon <= COALESCE(nguongCanhBao, 10)';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM thuoc $whereClause");
    if ($params) $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total = (int)$stmtCount->get_result()->fetch_row()[0];

    // Data
    $stmtData = $conn->prepare("SELECT * FROM thuoc $whereClause ORDER BY tenThuoc LIMIT ? OFFSET ?");
    $dataParams = array_merge($params, [$limit, $offset]);
    $dataTypes  = $types . 'ii';
    $stmtData->bind_param($dataTypes, ...$dataParams);
    $stmtData->execute();
    $rows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success'    => true,
        'data'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'totalPages' => (int)ceil($total / $limit),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}