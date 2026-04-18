<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('bacsi');

function bind_params_dynamic($stmt, $types, $params) {
    if ($types === '') return;
    $bind = [$types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;

    $search = trim($_GET['search'] ?? '');
    $visitType = trim($_GET['visitType'] ?? '');
    $gender = strtolower(trim($_GET['gender'] ?? ''));
    $sort = trim($_GET['sort'] ?? 'recent');
    $order = strtolower(trim($_GET['order'] ?? 'desc'));
    $orderDesc = $order !== 'asc';

    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $summarySql = "
        SELECT 
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            nd.email,
            COUNT(h.maHoSo) AS soLanKham,
            MIN(COALESCE(h.ngayKham, lk.ngayKham, DATE(h.ngayHoanThanh))) AS lanKhamDauTien,
            MAX(COALESCE(h.ngayKham, lk.ngayKham, DATE(h.ngayHoanThanh))) AS lanKhamGanNhat,
            (
                SELECT hs2.maHoSo
                FROM hosobenhan hs2
                LEFT JOIN lichkham lk2 ON hs2.maLichKham = lk2.maLichKham
                WHERE hs2.maBenhNhan = bn.maBenhNhan
                  AND hs2.maBacSi = ?
                  AND hs2.trangThai = 'Đã hoàn thành'
                  AND hs2.isDeleted = 0
                ORDER BY COALESCE(hs2.ngayKham, lk2.ngayKham, DATE(hs2.ngayHoanThanh)) DESC, hs2.ngayHoanThanh DESC, hs2.maHoSo DESC
                LIMIT 1
            ) AS maHoSoGanNhat,
            (
                SELECT hs2.chanDoan
                FROM hosobenhan hs2
                LEFT JOIN lichkham lk2 ON hs2.maLichKham = lk2.maLichKham
                WHERE hs2.maBenhNhan = bn.maBenhNhan
                  AND hs2.maBacSi = ?
                  AND hs2.trangThai = 'Đã hoàn thành'
                  AND hs2.isDeleted = 0
                ORDER BY COALESCE(hs2.ngayKham, lk2.ngayKham, DATE(hs2.ngayHoanThanh)) DESC, hs2.ngayHoanThanh DESC, hs2.maHoSo DESC
                LIMIT 1
            ) AS chanDoanGanNhat
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN hosobenhan h ON bn.maBenhNhan = h.maBenhNhan
        LEFT JOIN lichkham lk ON h.maLichKham = lk.maLichKham
        WHERE h.maBacSi = ?
          AND h.trangThai = 'Đã hoàn thành'
          AND h.isDeleted = 0
          AND nd.isDeleted = 0
        GROUP BY bn.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh, bn.soTheBHYT, nd.soDienThoai, nd.email
    ";

    $filterSql = " WHERE 1=1 ";
    $filterTypes = '';
    $filterParams = [];

    if ($search !== '') {
        $filterSql .= " AND (
            ps.maBenhNhan LIKE ? OR
            ps.tenBenhNhan LIKE ? OR
            COALESCE(ps.soDienThoai, '') LIKE ? OR
            COALESCE(ps.email, '') LIKE ? OR
            COALESCE(ps.chanDoanGanNhat, '') LIKE ?
        ) ";
        $searchLike = '%' . $search . '%';
        $filterTypes .= 'sssss';
        $filterParams[] = $searchLike;
        $filterParams[] = $searchLike;
        $filterParams[] = $searchLike;
        $filterParams[] = $searchLike;
        $filterParams[] = $searchLike;
    }

    if ($visitType === '1') {
        $filterSql .= " AND ps.soLanKham = 1 ";
    } elseif ($visitType === '2+') {
        $filterSql .= " AND ps.soLanKham > 1 ";
    }

    if (in_array($gender, ['nam', 'nu'], true)) {
        $filterSql .= " AND LOWER(ps.gioiTinh) = ? ";
        $filterTypes .= 's';
        $filterParams[] = $gender;
    }

    $orderBy = $orderDesc ? "ps.lanKhamGanNhat DESC, ps.tenBenhNhan ASC" : "ps.lanKhamGanNhat ASC, ps.tenBenhNhan DESC";
    if ($sort === 'name') {
        $orderBy = $orderDesc ? "ps.tenBenhNhan ASC, ps.lanKhamGanNhat DESC" : "ps.tenBenhNhan DESC, ps.lanKhamGanNhat ASC";
    } elseif ($sort === 'visits') {
        $orderBy = $orderDesc ? "ps.soLanKham DESC, ps.lanKhamGanNhat DESC, ps.tenBenhNhan ASC" : "ps.soLanKham ASC, ps.lanKhamGanNhat ASC, ps.tenBenhNhan DESC";
    }

    $baseTypes = 'sss';
    $baseParams = [$maBacSi, $maBacSi, $maBacSi];

    $countSql = "SELECT COUNT(*) AS total FROM ($summarySql) ps $filterSql";
    $countStmt = $conn->prepare($countSql);
    $countTypes = $baseTypes . $filterTypes;
    $countParams = array_merge($baseParams, $filterParams);
    bind_params_dynamic($countStmt, $countTypes, $countParams);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $limit) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = max(0, ($page - 1) * $limit);

    $idSql = "SELECT ps.maBenhNhan FROM ($summarySql) ps $filterSql ORDER BY $orderBy LIMIT ? OFFSET ?";
    $idStmt = $conn->prepare($idSql);
    $idTypes = $baseTypes . $filterTypes . 'ii';
    $idParams = array_merge($baseParams, $filterParams, [$limit, $offset]);
    bind_params_dynamic($idStmt, $idTypes, $idParams);
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    $patientIds = [];
    while ($row = $idResult->fetch_assoc()) {
        $patientIds[] = $row['maBenhNhan'];
    }
    $idStmt->close();

    $patients = [];
    if (!empty($patientIds)) {
        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $dataSql = "SELECT ps.* FROM ($summarySql) ps WHERE ps.maBenhNhan IN ($placeholders)";
        $dataStmt = $conn->prepare($dataSql);
        $dataTypes = $baseTypes . str_repeat('s', count($patientIds));
        $dataParams = array_merge($baseParams, $patientIds);
        bind_params_dynamic($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        $patientMap = [];
        while ($row = $dataResult->fetch_assoc()) {
            $row['soLanKham'] = (int)$row['soLanKham'];
            $patientMap[$row['maBenhNhan']] = $row;
        }
        $dataStmt->close();

        foreach ($patientIds as $id) {
            if (isset($patientMap[$id])) {
                $patients[] = $patientMap[$id];
            }
        }
    }

    $statsSql = "
        SELECT
            COUNT(*) AS totalPatients,
            SUM(CASE WHEN ps.soLanKham = 1 THEN 1 ELSE 0 END) AS newPatients,
            SUM(CASE WHEN ps.soLanKham > 1 THEN 1 ELSE 0 END) AS revisitPatients,
            SUM(CASE WHEN ps.lanKhamGanNhat >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recentPatients
        FROM ($summarySql) ps
    ";
    $statsStmt = $conn->prepare($statsSql);
    bind_params_dynamic($statsStmt, $baseTypes, $baseParams);
    $statsStmt->execute();
    $statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    echo json_encode([
        'success' => true,
        'data' => $patients,
        'pagination' => [
            'page' => $totalPages > 0 ? $page : 1,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages
        ],
        'stats' => [
            'totalPatients' => (int)($statsRow['totalPatients'] ?? 0),
            'newPatients' => (int)($statsRow['newPatients'] ?? 0),
            'revisitPatients' => (int)($statsRow['revisitPatients'] ?? 0),
            'recentPatients' => (int)($statsRow['recentPatients'] ?? 0)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
