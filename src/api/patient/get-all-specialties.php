<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';

function buildSpecialtyFilterClause(
    string $ckAlias,
    string $kAlias,
    string $maKhoa,
    string $search,
    array &$params,
    string &$types
): array {
    $where = " WHERE 1=1";
    $requiresDepartmentJoin = false;

    if ($maKhoa !== '') {
        $where .= " AND {$ckAlias}.maKhoa = ?";
        $params[] = $maKhoa;
        $types .= 's';
    }

    if ($search !== '') {
        $requiresDepartmentJoin = true;
        $where .= " AND ({$ckAlias}.tenChuyenKhoa LIKE ? OR {$kAlias}.tenKhoa LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    return [
        'whereClause' => $where,
        'joinDepartmentClause' => $requiresDepartmentJoin
            ? " LEFT JOIN khoa {$kAlias} ON {$ckAlias}.maKhoa = {$kAlias}.maKhoa"
            : ''
    ];
}

function mapSpecialtyRow(array $row): array
{
    return [
        'maChuyenKhoa' => $row['maChuyenKhoa'],
        'tenChuyenKhoa' => $row['tenChuyenKhoa'],
        'maKhoa' => $row['maKhoa'],
        'tenKhoa' => $row['tenKhoa'],
        'moTa' => $row['moTa'],
        'soBacSi' => (int)($row['soBacSi'] ?? 0)
    ];
}

$maKhoa = trim($_GET['maKhoa'] ?? '');
$search = trim($_GET['search'] ?? '');
$pageInput = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $pageInput);
$limitInput = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = max(1, min($limitInput, 50));
$offset = ($page - 1) * $limit;

try {
    $countParams = [];
    $countTypes = '';
    $countFilter = buildSpecialtyFilterClause(
        'ck',
        'k',
        $maKhoa,
        $search,
        $countParams,
        $countTypes
    );

    $countSql = "
        SELECT COUNT(*) AS total
        FROM chuyenkhoa ck
        {$countFilter['joinDepartmentClause']}
        {$countFilter['whereClause']}
    ";

    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn tổng số chuyên khoa.');
    }
    if ($countTypes !== '') {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $total = (int)($countRow['total'] ?? 0);
    $countStmt->close();

    $queryParams = [];
    $queryTypes = '';
    $innerFilter = buildSpecialtyFilterClause(
        'cki',
        'ki',
        $maKhoa,
        $search,
        $queryParams,
        $queryTypes
    );

    $sql = "
        SELECT
            ck.maChuyenKhoa,
            ck.tenChuyenKhoa,
            ck.maKhoa,
            ck.moTa,
            k.tenKhoa,
            COALESCE(bs_count.soBacSi, 0) AS soBacSi
        FROM chuyenkhoa ck
        LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
        LEFT JOIN (
            SELECT bs.maChuyenKhoa, COUNT(*) AS soBacSi
            FROM bacsi bs
            INNER JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            WHERE nd.isDeleted = 0
            GROUP BY bs.maChuyenKhoa
        ) bs_count ON ck.maChuyenKhoa = bs_count.maChuyenKhoa
        INNER JOIN (
            SELECT cki.maChuyenKhoa
            FROM chuyenkhoa cki
            {$innerFilter['joinDepartmentClause']}
            {$innerFilter['whereClause']}
            ORDER BY cki.tenChuyenKhoa ASC, cki.maChuyenKhoa ASC
            LIMIT ? OFFSET ?
        ) page_ids ON page_ids.maChuyenKhoa = ck.maChuyenKhoa
        ORDER BY ck.tenChuyenKhoa ASC, ck.maChuyenKhoa ASC
    ";

    $queryParams[] = $limit;
    $queryParams[] = $offset;
    $queryTypes .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn danh sách chuyên khoa.');
    }
    if ($queryTypes !== '') {
        $stmt->bind_param($queryTypes, ...$queryParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $specialties = [];
    while ($row = $result->fetch_assoc()) {
        $specialties[] = mapSpecialtyRow($row);
    }
    $stmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $limit) : 0;

    echo json_encode([
        'success' => true,
        'data' => $specialties,
        'pagination' => [
            'mode' => 'deferred',
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'totalPages' => $totalPages
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage(),
        'data' => [],
        'pagination' => [
            'mode' => 'deferred',
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'totalPages' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
