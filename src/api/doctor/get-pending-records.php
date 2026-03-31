<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
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
    $sort = trim($_GET['sort'] ?? 'newest');

    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $whereSql = " WHERE h.maBacSi = ? AND h.trangThai = 'Chưa hoàn thành' AND h.isDeleted = 0 ";
    $whereTypes = 's';
    $whereParams = [$maBacSi];

    if ($search !== '') {
        $whereSql .= " AND bn.tenBenhNhan LIKE ? ";
        $whereTypes .= 's';
        $whereParams[] = '%' . $search . '%';
    }

    $orderBy = "sortDate DESC, sortTime DESC, page.maHoSo DESC";
    if ($sort === 'oldest') {
        $orderBy = "sortDate ASC, sortTime ASC, page.maHoSo ASC";
    } elseif ($sort === 'name') {
        $orderBy = "sortName ASC, sortDate DESC, sortTime DESC, page.maHoSo DESC";
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM hosobenhan h
        JOIN benhnhan bn ON h.maBenhNhan = bn.maBenhNhan
        LEFT JOIN lichkham l ON h.maLichKham = l.maLichKham
        $whereSql
    ";
    $countStmt = $conn->prepare($countSql);
    bind_params_dynamic($countStmt, $whereTypes, $whereParams);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $limit) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = max(0, ($page - 1) * $limit);

    $idSql = "
        SELECT 
            h.maHoSo,
            COALESCE(l.ngayKham, h.ngayKham) AS sortDate,
            COALESCE(s.gioBatDau, '00:00:00') AS sortTime,
            bn.tenBenhNhan AS sortName
        FROM hosobenhan h
        JOIN benhnhan bn ON h.maBenhNhan = bn.maBenhNhan
        LEFT JOIN lichkham l ON h.maLichKham = l.maLichKham
        LEFT JOIN suatkham s ON l.maSuat = s.maSuat
        $whereSql
        ORDER BY " . str_replace('page.', '', $orderBy) . "
        LIMIT ? OFFSET ?
    ";
    $idStmt = $conn->prepare($idSql);
    $idTypes = $whereTypes . 'ii';
    $idParams = array_merge($whereParams, [$limit, $offset]);
    bind_params_dynamic($idStmt, $idTypes, $idParams);
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    $recordIds = [];
    while ($row = $idResult->fetch_assoc()) {
        $recordIds[] = $row['maHoSo'];
    }
    $idStmt->close();

    $records = [];
    if (!empty($recordIds)) {
        $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
        $dataSql = "
            SELECT h.maHoSo, h.ngayTao, h.chanDoan, h.dieuTri, h.ghiChu,
                   bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh,
                   COALESCE(l.ngayKham, h.ngayKham) AS ngayKham, c.tenCa,
                   s.gioBatDau, s.gioKetThuc
            FROM hosobenhan h
            JOIN benhnhan bn ON h.maBenhNhan = bn.maBenhNhan
            LEFT JOIN lichkham l ON h.maLichKham = l.maLichKham
            LEFT JOIN calamviec c ON l.maCa = c.maCa
            LEFT JOIN suatkham s ON l.maSuat = s.maSuat
            WHERE h.maHoSo IN ($placeholders)
        ";
        $dataStmt = $conn->prepare($dataSql);
        $dataTypes = str_repeat('s', count($recordIds));
        bind_params_dynamic($dataStmt, $dataTypes, $recordIds);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        $recordMap = [];
        while ($row = $dataResult->fetch_assoc()) {
            $recordMap[$row['maHoSo']] = $row;
        }
        $dataStmt->close();

        foreach ($recordIds as $id) {
            if (isset($recordMap[$id])) {
                $records[] = $recordMap[$id];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $records,
        'pagination' => [
            'page' => $totalPages > 0 ? $page : 1,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
