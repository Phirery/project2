<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';

function buildFilterClause(string $maKhoa, string $search, array &$params, string &$types): string
{
    $where = " WHERE 1=1";

    if ($maKhoa !== '') {
        $where .= " AND ck.maKhoa = ?";
        $params[] = $maKhoa;
        $types .= 's';
    }

    if ($search !== '') {
        $where .= " AND (ck.tenChuyenKhoa LIKE ? OR k.tenKhoa LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    return $where;
}

function getTotalSpecialties(mysqli $conn, string $whereClause, string $types, array $params): int
{
    $countSql = "
        SELECT COUNT(*) as total
        FROM chuyenkhoa ck
        LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
        {$whereClause}
    ";

    $countStmt = $conn->prepare($countSql);
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $countStmt->close();

    return (int)($totalRow['total'] ?? 0);
}

$maKhoa = trim($_GET['maKhoa'] ?? '');
$search = trim($_GET['search'] ?? '');
$mode = strtolower(trim($_GET['mode'] ?? 'offset'));
$limitInput = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = max(1, min($limitInput, 50));

try {
    $baseSelect = "
        SELECT 
            ck.maChuyenKhoa,
            ck.tenChuyenKhoa,
            ck.maKhoa,
            ck.moTa,
            k.tenKhoa,
            COALESCE(bs_count.soBacSi, 0) as soBacSi
        FROM chuyenkhoa ck
        LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
        LEFT JOIN (
            SELECT bs.maChuyenKhoa, COUNT(*) AS soBacSi
            FROM bacsi bs
            JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            WHERE nd.isDeleted = 0
            GROUP BY bs.maChuyenKhoa
        ) bs_count ON ck.maChuyenKhoa = bs_count.maChuyenKhoa
    ";

    $params = [];
    $types = '';
    $whereClause = buildFilterClause($maKhoa, $search, $params, $types);

    if ($mode === 'cursor') {
        $cursor = trim($_GET['cursor'] ?? '');
        $includeTotal = filter_var($_GET['includeTotal'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $queryParams = $params;
        $queryTypes = $types;
        $cursorWhereClause = $whereClause;

        if ($cursor !== '') {
            $cursorWhereClause .= " AND ck.maChuyenKhoa > ?";
            $queryParams[] = $cursor;
            $queryTypes .= 's';
        }

        $sql = $baseSelect
            . $cursorWhereClause
            . " ORDER BY ck.maChuyenKhoa ASC LIMIT ?";

        $queryParams[] = $limit + 1;
        $queryTypes .= 'i';

        $stmt = $conn->prepare($sql);
        if ($queryTypes !== '') {
            $stmt->bind_param($queryTypes, ...$queryParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $specialties = [];
        while ($row = $result->fetch_assoc()) {
            $specialties[] = [
                'maChuyenKhoa' => $row['maChuyenKhoa'],
                'tenChuyenKhoa' => $row['tenChuyenKhoa'],
                'maKhoa' => $row['maKhoa'],
                'tenKhoa' => $row['tenKhoa'],
                'moTa' => $row['moTa'],
                'soBacSi' => (int)$row['soBacSi']
            ];
        }
        $stmt->close();

        $hasNext = count($specialties) > $limit;
        if ($hasNext) {
            array_pop($specialties);
        }

        $nextCursor = null;
        if ($hasNext && !empty($specialties)) {
            $lastRow = end($specialties);
            $nextCursor = $lastRow['maChuyenKhoa'];
        }

        $pagination = [
            'mode' => 'cursor',
            'limit' => $limit,
            'hasNext' => $hasNext,
            'nextCursor' => $nextCursor
        ];

        if ($includeTotal) {
            $pagination['total'] = getTotalSpecialties($conn, $whereClause, $types, $params);
        }

        echo json_encode([
            'success' => true,
            'data' => $specialties,
            'pagination' => $pagination
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $pageInput = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $pageInput);
        $offset = ($page - 1) * $limit;

        $sql = $baseSelect
            . $whereClause
            . " ORDER BY ck.maChuyenKhoa ASC LIMIT ? OFFSET ?";

        $queryParams = $params;
        $queryParams[] = $limit;
        $queryParams[] = $offset;
        $queryTypes = $types . 'ii';

        $stmt = $conn->prepare($sql);
        if ($queryTypes !== '') {
            $stmt->bind_param($queryTypes, ...$queryParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $specialties = [];
        while ($row = $result->fetch_assoc()) {
            $specialties[] = [
                'maChuyenKhoa' => $row['maChuyenKhoa'],
                'tenChuyenKhoa' => $row['tenChuyenKhoa'],
                'maKhoa' => $row['maKhoa'],
                'tenKhoa' => $row['tenKhoa'],
                'moTa' => $row['moTa'],
                'soBacSi' => (int)$row['soBacSi']
            ];
        }
        $stmt->close();

        $total = getTotalSpecialties($conn, $whereClause, $types, $params);
        $totalPages = (int)ceil($total / $limit);

        echo json_encode([
            'success' => true,
            'data' => $specialties,
            'pagination' => [
                'mode' => 'offset',
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
