<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('bacsi');

/**
 * Hàng đợi khám hôm nay của bác sĩ đang đăng nhập, đọc từ `hangdoikham`
 * (thay cho get-today-appointments.php vốn đọc thẳng `lichkham` theo giờ slot).
 * Sắp theo soThuTu (thứ tự check-in thực tế), không theo giờ hẹn danh nghĩa.
 */

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
    $shift = trim($_GET['shift'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $order = strtolower(trim($_GET['order'] ?? 'asc'));
    $orderDir = $order === 'desc' ? 'DESC' : 'ASC';

    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $whereSql = " WHERE hd.maBacSi = ? AND hd.ngay = CURDATE() ";
    $whereTypes = 's';
    $whereParams = [$maBacSi];

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

    $allowedStatuses = ['Đang chờ', 'Đang khám', 'Hoàn thành', 'Bỏ lỡ', 'Hủy'];
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $whereSql .= " AND hd.trangThai = ? ";
        $whereTypes .= 's';
        $whereParams[] = $status;
    }

    $statsSql = "
        SELECT
            SUM(CASE WHEN hd.trangThai = 'Đang chờ' THEN 1 ELSE 0 END) AS dangCho,
            SUM(CASE WHEN hd.trangThai = 'Đang khám' THEN 1 ELSE 0 END) AS dangKham,
            SUM(CASE WHEN hd.trangThai = 'Hoàn thành' THEN 1 ELSE 0 END) AS hoanThanh,
            SUM(CASE WHEN hd.trangThai = 'Bỏ lỡ' THEN 1 ELSE 0 END) AS boLo
        FROM hangdoikham hd
        WHERE hd.maBacSi = ? AND hd.ngay = CURDATE()
    ";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bind_param('s', $maBacSi);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    $countSql = "
        SELECT COUNT(*) AS total
        FROM hangdoikham hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
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

    $dataSql = "
        SELECT
            hd.maHangDoi, hd.soThuTu, hd.trangThai AS trangThaiHangDoi, hd.nguon,
            hd.thoiGianCheckIn, hd.thoiGianGoiKham, hd.thoiGianHoanThanh,
            lk.maLichKham, lk.ngayKham, lk.trangThai AS trangThaiLich, lk.ghiChu,
            bn.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh,
            ca.tenCa, ca.maCa,
            sk.gioBatDau, sk.gioKetThuc,
            gk.tenGoi,
            h.maHoSo, h.trangThai AS trangThaiHoSo
        FROM hangdoikham hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
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
        $whereSql
        ORDER BY hd.soThuTu $orderDir
        LIMIT ? OFFSET ?
    ";
    $dataStmt = $conn->prepare($dataSql);
    $dataTypes = $whereTypes . 'ii';
    $dataParams = array_merge($whereParams, [$limit, $offset]);
    bind_params_dynamic($dataStmt, $dataTypes, $dataParams);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();

    $appointments = [];
    while ($row = $dataResult->fetch_assoc()) {
        $appointments[] = $row;
    }
    $dataStmt->close();

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
            'dangCho' => (int)($stats['dangCho'] ?? 0),
            'dangKham' => (int)($stats['dangKham'] ?? 0),
            'hoanThanh' => (int)($stats['hoanThanh'] ?? 0),
            'boLo' => (int)($stats['boLo'] ?? 0)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
$conn->close();
?>