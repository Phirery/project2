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
    $type = trim($_GET['type'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $order = strtolower(trim($_GET['order'] ?? 'desc'));
    $orderDir = $order === 'asc' ? 'ASC' : 'DESC';

    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $loaiExpr = "CASE
        WHEN t.maLichKham IS NULL AND (t.tieuDe LIKE '%mật khẩu%' OR t.noiDung LIKE '%mật khẩu%') THEN 'Cấp lại mật khẩu'
        ELSE t.loai
    END";

    $whereSql = " WHERE t.maBacSi = ? ";
    $whereTypes = 's';
    $whereParams = [$maBacSi];

    if ($search !== '') {
        $whereSql .= " AND (
            t.tieuDe LIKE ? OR
            t.noiDung LIKE ? OR
            COALESCE(bn.tenBenhNhan, '') LIKE ?
        ) ";
        $searchLike = '%' . $search . '%';
        $whereTypes .= 'sss';
        $whereParams[] = $searchLike;
        $whereParams[] = $searchLike;
        $whereParams[] = $searchLike;
    }

    if ($type !== '') {
        $allowedTypes = ['Đặt lịch', 'Hủy lịch', 'Cấp lại mật khẩu'];
        if (in_array($type, $allowedTypes, true)) {
            $whereSql .= " AND $loaiExpr = ? ";
            $whereTypes .= 's';
            $whereParams[] = $type;
        }
    }

    if ($status === 'unread') {
        $whereSql .= " AND t.daXem = 0 ";
    } elseif ($status === 'read') {
        $whereSql .= " AND t.daXem = 1 ";
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM thongbaolichkham t
        LEFT JOIN lichkham l ON t.maLichKham = l.maLichKham
        LEFT JOIN benhnhan bn ON l.maBenhNhan = bn.maBenhNhan
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
        SELECT t.maThongBao
        FROM thongbaolichkham t
        LEFT JOIN lichkham l ON t.maLichKham = l.maLichKham
        LEFT JOIN benhnhan bn ON l.maBenhNhan = bn.maBenhNhan
        $whereSql
        ORDER BY t.thoiGian $orderDir, t.maThongBao $orderDir
        LIMIT ? OFFSET ?
    ";
    $idStmt = $conn->prepare($idSql);
    $idTypes = $whereTypes . 'ii';
    $idParams = array_merge($whereParams, [$limit, $offset]);
    bind_params_dynamic($idStmt, $idTypes, $idParams);
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    $notificationIds = [];
    while ($row = $idResult->fetch_assoc()) {
        $notificationIds[] = $row['maThongBao'];
    }
    $idStmt->close();

    $notifications = [];
    if (!empty($notificationIds)) {
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $dataSql = "
            SELECT 
                t.maThongBao, 
                $loaiExpr AS loai, 
                t.tieuDe, 
                t.noiDung, 
                t.thoiGian, 
                t.daXem,
                t.maLichKham,
                bn.tenBenhNhan, 
                l.ngayKham, 
                c.tenCa
            FROM thongbaolichkham t
            LEFT JOIN lichkham l ON t.maLichKham = l.maLichKham
            LEFT JOIN benhnhan bn ON l.maBenhNhan = bn.maBenhNhan
            LEFT JOIN calamviec c ON l.maCa = c.maCa
            WHERE t.maThongBao IN ($placeholders)
            ORDER BY t.thoiGian $orderDir, t.maThongBao $orderDir
        ";
        $dataStmt = $conn->prepare($dataSql);
        $dataTypes = str_repeat('s', count($notificationIds));
        bind_params_dynamic($dataStmt, $dataTypes, $notificationIds);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        while ($row = $dataResult->fetch_assoc()) {
            $row['daXem'] = (bool)$row['daXem'];
            $notifications[] = $row;
        }
        $dataStmt->close();
    }

    $statsSql = "
        SELECT
            COUNT(*) AS totalNotifications,
            SUM(CASE WHEN t.daXem = 0 THEN 1 ELSE 0 END) AS unreadNotifications,
            SUM(CASE WHEN $loaiExpr = 'Đặt lịch' THEN 1 ELSE 0 END) AS bookingNotifications,
            SUM(CASE WHEN $loaiExpr = 'Hủy lịch' THEN 1 ELSE 0 END) AS cancelNotifications,
            SUM(CASE WHEN $loaiExpr = 'Cấp lại mật khẩu' THEN 1 ELSE 0 END) AS passwordNotifications
        FROM thongbaolichkham t
        LEFT JOIN lichkham l ON t.maLichKham = l.maLichKham
        LEFT JOIN benhnhan bn ON l.maBenhNhan = bn.maBenhNhan
        WHERE t.maBacSi = ?
    ";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bind_param("s", $maBacSi);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'pagination' => [
            'page' => $totalPages > 0 ? $page : 1,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages
        ],
        'stats' => [
            'totalNotifications' => (int)($stats['totalNotifications'] ?? 0),
            'unreadNotifications' => (int)($stats['unreadNotifications'] ?? 0),
            'bookingNotifications' => (int)($stats['bookingNotifications'] ?? 0),
            'cancelNotifications' => (int)($stats['cancelNotifications'] ?? 0),
            'passwordNotifications' => (int)($stats['passwordNotifications'] ?? 0),
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
