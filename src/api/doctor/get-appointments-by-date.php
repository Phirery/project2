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

$nguoiDungId = $_SESSION['id'];

$date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$countOnly = (($_GET['countOnly'] ?? '0') === '1');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 10);
if ($limit < 1) $limit = 10;
if ($limit > 100) $limit = 100;

    $search = trim($_GET['search'] ?? '');
    $shift = trim($_GET['shift'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $order = strtolower(trim($_GET['order'] ?? 'desc'));
    $orderDir = $order === 'asc' ? 'ASC' : 'DESC';

try {
    $stmt_bs = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt_bs->bind_param("i", $nguoiDungId);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();
    
    if ($result_bs->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin bác sĩ liên kết.']);
        exit;
    }
    
    $bacsi = $result_bs->fetch_assoc();
    $maBacSi = $bacsi['maBacSi'];
    $stmt_bs->close();

    $uiStatusSql = "CASE
        WHEN lk.trangThai = 'Hủy' THEN 'cancelled'
        WHEN EXISTS (
            SELECT 1
            FROM hosobenhan hx
            WHERE hx.maLichKham = lk.maLichKham
              AND hx.isDeleted = 0
              AND hx.trangThai = 'Chưa hoàn thành'
        ) THEN 'draft'
        WHEN lk.trangThai = 'Hoàn thành' OR EXISTS (
            SELECT 1
            FROM hosobenhan hx
            WHERE hx.maLichKham = lk.maLichKham
              AND hx.isDeleted = 0
              AND hx.trangThai = 'Đã hoàn thành'
        ) THEN 'completed'
        ELSE 'booked'
    END";

    $statsSql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN base.uiStatus = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN base.uiStatus = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN base.uiStatus IN ('booked', 'draft') THEN 1 ELSE 0 END) AS pending
        FROM (
            SELECT lk.maLichKham, $uiStatusSql AS uiStatus
            FROM lichkham lk
            WHERE lk.maBacSi = ? AND lk.ngayKham = ?
        ) base
    ";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bind_param("ss", $maBacSi, $date);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    if ($countOnly) {
        echo json_encode([
            'success' => true,
            'total' => (int)($stats['total'] ?? 0),
            'stats' => [
                'total' => (int)($stats['total'] ?? 0),
                'pending' => (int)($stats['pending'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'cancelled' => (int)($stats['cancelled'] ?? 0)
            ]
        ]);
        exit;
    }

    $whereSql = " WHERE lk.maBacSi = ? AND lk.ngayKham = ? ";
    $whereTypes = 'ss';
    $whereParams = [$maBacSi, $date];

    if ($search !== '') {
        $whereSql .= " AND bn.tenBenhNhan LIKE ? ";
        $whereTypes .= 's';
        $whereParams[] = '%' . $search . '%';
    }

    if ($shift !== '' && ctype_digit($shift)) {
        $whereSql .= " AND lk.maCa = ? ";
        $whereTypes .= 'i';
        $whereParams[] = (int)$shift;
    }

    $allowedStatuses = ['booked', 'draft', 'completed', 'cancelled'];
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $whereSql .= " AND $uiStatusSql = ? ";
        $whereTypes .= 's';
        $whereParams[] = $status;
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
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
        SELECT lk.maLichKham
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        $whereSql
        ORDER BY lk.maCa $orderDir, sk.gioBatDau $orderDir, lk.maLichKham $orderDir
        LIMIT ? OFFSET ?
    ";
    $idStmt = $conn->prepare($idSql);
    $idTypes = $whereTypes . 'ii';
    $idParams = array_merge($whereParams, [$limit, $offset]);
    bind_params_dynamic($idStmt, $idTypes, $idParams);
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    $appointmentIds = [];
    while ($row = $idResult->fetch_assoc()) {
        $appointmentIds[] = $row['maLichKham'];
    }
    $idStmt->close();

    $appointments = [];
    if (!empty($appointmentIds)) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $dataSql = "
            SELECT 
                lk.maLichKham, lk.ngayKham, lk.trangThai, lk.ghiChu,
                bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh,
                ca.tenCa, ca.maCa,
                sk.gioBatDau, sk.gioKetThuc,
                gk.tenGoi,
                h.maHoSo,
                h.trangThai AS trangThaiHoSo
            FROM lichkham lk
            LEFT JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
            LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
            LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
            LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
            LEFT JOIN (
                SELECT h1.maLichKham, h1.maHoSo, h1.trangThai
                FROM hosobenhan h1
                INNER JOIN (
                    SELECT maLichKham, MAX(maHoSo) AS maxMaHoSo
                    FROM hosobenhan
                    WHERE isDeleted = 0
                    GROUP BY maLichKham
                ) latest ON latest.maLichKham = h1.maLichKham AND latest.maxMaHoSo = h1.maHoSo
                WHERE h1.isDeleted = 0
            ) h ON h.maLichKham = lk.maLichKham
            WHERE lk.maLichKham IN ($placeholders)
            ORDER BY lk.maCa $orderDir, sk.gioBatDau $orderDir, lk.maLichKham $orderDir
        ";
        $dataStmt = $conn->prepare($dataSql);
        $dataTypes = str_repeat('s', count($appointmentIds));
        bind_params_dynamic($dataStmt, $dataTypes, $appointmentIds);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        while ($row = $dataResult->fetch_assoc()) {
            $appointments[] = $row;
        }
        $dataStmt->close();
    }

    echo json_encode([
        'success' => true,
        'data' => $appointments,
        'pagination' => [
            'page' => $totalPages > 0 ? $page : 1,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages
        ],
        'stats' => [
            'total' => (int)($stats['total'] ?? 0),
            'pending' => (int)($stats['pending'] ?? 0),
            'completed' => (int)($stats['completed'] ?? 0),
            'cancelled' => (int)($stats['cancelled'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}

$conn->close();
?>
