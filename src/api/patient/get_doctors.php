<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';

function buildDoctorFilterClause(
    string $bsAlias,
    string $ckAlias,
    string $maKhoa,
    string $maChuyenKhoa,
    string $search,
    array &$params,
    string &$types
): array {
    $where = " WHERE 1=1";
    $requiresSpecialtyJoin = false;

    if ($maChuyenKhoa !== '') {
        $where .= " AND {$bsAlias}.maChuyenKhoa = ?";
        $params[] = $maChuyenKhoa;
        $types .= 's';
    }

    if ($maKhoa !== '') {
        $requiresSpecialtyJoin = true;
        $where .= " AND {$ckAlias}.maKhoa = ?";
        $params[] = $maKhoa;
        $types .= 's';
    }

    if ($search !== '') {
        $requiresSpecialtyJoin = true;
        $where .= " AND ({$bsAlias}.tenBacSi LIKE ? OR {$ckAlias}.tenChuyenKhoa LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    return [
        'whereClause' => $where,
        'joinSpecialtyClause' => $requiresSpecialtyJoin
            ? " LEFT JOIN chuyenkhoa {$ckAlias} ON {$bsAlias}.maChuyenKhoa = {$ckAlias}.maChuyenKhoa"
            : ''
    ];
}

function mapDoctorRow(array $row, int $currentYear): array
{
    $experience = 0;
    if (!empty($row['namLamViec'])) {
        $experience = $currentYear - (int)$row['namLamViec'];
    }

    $maleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962515/doctor_male_pna01s.png';
    $femaleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962514/doctor_female_zvmhtg.png';
    $gender = strtolower((string)($row['gioiTinh'] ?? ''));
    $fallbackAvatar = ($gender === 'nu') ? $femaleDefault : $maleDefault;

    $avatar = trim((string)($row['avatar'] ?? ''));
    $hasCustomAvatar = $avatar !== '' && stripos($avatar, 'samples/paper.png') === false;
    $imageUrl = $hasCustomAvatar ? $avatar : $fallbackAvatar;

    return [
        'maBacSi' => $row['maBacSi'],
        'tenBacSi' => $row['tenBacSi'],
        'gioiTinh' => $row['gioiTinh'],
        'namLamViec' => $row['namLamViec'],
        'namKinhNghiem' => $experience,
        'moTa' => $row['moTa'],
        'maChuyenKhoa' => $row['maChuyenKhoa'],
        'tenChuyenKhoa' => $row['tenChuyenKhoa'],
        'maKhoa' => $row['maKhoa'],
        'tenKhoa' => $row['tenKhoa'],
        'anhDaiDien' => $imageUrl
    ];
}

$maKhoa = trim($_GET['maKhoa'] ?? '');
$maChuyenKhoa = trim($_GET['maChuyenKhoa'] ?? '');
$search = trim($_GET['search'] ?? '');
$mode = strtolower(trim($_GET['mode'] ?? 'all'));
$pageInput = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $pageInput);
$limitInput = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$limit = max(1, min($limitInput, 50));

try {
    $currentYear = (int)date('Y');

    if ($mode === 'deferred') {
        $countParams = [];
        $countTypes = '';
        $countFilter = buildDoctorFilterClause(
            'bs',
            'ck',
            $maKhoa,
            $maChuyenKhoa,
            $search,
            $countParams,
            $countTypes
        );

        $countSql = "
            SELECT COUNT(*) AS total
            FROM bacsi bs
            {$countFilter['joinSpecialtyClause']}
            {$countFilter['whereClause']}
        ";
        $countStmt = $conn->prepare($countSql);
        if ($countTypes !== '') {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $total = (int)($totalRow['total'] ?? 0);
        $countStmt->close();

        $offset = ($page - 1) * $limit;
        $queryParams = [];
        $queryTypes = '';
        $innerFilter = buildDoctorFilterClause(
            'bsi',
            'cki',
            $maKhoa,
            $maChuyenKhoa,
            $search,
            $queryParams,
            $queryTypes
        );

        $sql = "
            SELECT
                bs.maBacSi,
                bs.tenBacSi,
                bs.gioiTinh,
                bs.namLamViec,
                bs.moTa,
                ck.maChuyenKhoa,
                ck.tenChuyenKhoa,
                ck.maKhoa,
                k.tenKhoa,
                nd.avatar
            FROM bacsi bs
            LEFT JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
            LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
            INNER JOIN (
                SELECT bsi.maBacSi
                FROM bacsi bsi
                {$innerFilter['joinSpecialtyClause']}
                {$innerFilter['whereClause']}
                ORDER BY bsi.tenBacSi ASC, bsi.maBacSi ASC
                LIMIT ? OFFSET ?
            ) page_ids ON page_ids.maBacSi = bs.maBacSi
            ORDER BY bs.tenBacSi ASC, bs.maBacSi ASC
        ";

        $queryParams[] = $limit;
        $queryParams[] = $offset;
        $queryTypes .= 'ii';

        $stmt = $conn->prepare($sql);
        if ($queryTypes !== '') {
            $stmt->bind_param($queryTypes, ...$queryParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = mapDoctorRow($row, $currentYear);
        }
        $stmt->close();

        $totalPages = (int)ceil($total / $limit);
        echo json_encode([
            'success' => true,
            'data' => $doctors,
            'pagination' => [
                'mode' => 'deferred',
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $params = [];
        $types = '';
        $filter = buildDoctorFilterClause(
            'bs',
            'ck',
            $maKhoa,
            $maChuyenKhoa,
            $search,
            $params,
            $types
        );

        $sql = "
            SELECT
                bs.maBacSi,
                bs.tenBacSi,
                bs.gioiTinh,
                bs.namLamViec,
                bs.moTa,
                ck.maChuyenKhoa,
                ck.tenChuyenKhoa,
                ck.maKhoa,
                k.tenKhoa,
                nd.avatar
            FROM bacsi bs
            LEFT JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
            LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
            {$filter['whereClause']}
            ORDER BY bs.tenBacSi ASC, bs.maBacSi ASC
        ";

        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = mapDoctorRow($row, $currentYear);
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'data' => $doctors,
            'total' => count($doctors)
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
