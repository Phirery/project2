<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/billing-helper.php';

api_require_method('GET');
require_staff_permission('billing.collect');

$tab = trim((string)($_GET['tab'] ?? 'unpaid'));
if (!in_array($tab, ['unpaid', 'paid', 'all'], true)) {
    $tab = 'unpaid';
}

$dateParam = trim((string)($_GET['date'] ?? ''));
if ($dateParam !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    api_error('Định dạng ngày không hợp lệ (YYYY-MM-DD).', 400);
}

$search = trim((string)($_GET['search'] ?? ''));

try {
    $where = " WHERE (lk.trangThai <> 'Hủy' OR EXISTS (SELECT 1 FROM hoadon h2 WHERE h2.maLichKham = lk.maLichKham AND h2.trangThai = 'Đã thanh toán')) ";
    $types = '';
    $params = [];

    if ($dateParam !== '') {
        $where .= " AND lk.ngayKham = ? ";
        $types .= 's';
        $params[] = $dateParam;
    }

    if ($search !== '') {
        $where .= " AND (bn.tenBenhNhan LIKE ? OR bn.maBenhNhan LIKE ? OR nd.soDienThoai LIKE ? OR bs.tenBacSi LIKE ? OR CAST(lk.maLichKham AS CHAR) LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'sssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT lk.maLichKham
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        $where
        ORDER BY lk.ngayKham DESC, lk.maLichKham DESC
        LIMIT 120
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn danh sách lượt khám thu ngân.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $lkRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $allSummaries = [];
    foreach ($lkRows as $row) {
        $summary = billing_build_encounter_summary($conn, (int)$row['maLichKham']);
        if ($summary) {
            $allSummaries[] = $summary;
        }
    }

    $choThuCount = 0;
    $motPhanCount = 0;
    $daThuDuCount = 0;
    $tongConPhaiThu = 0.0;
    $doanhThuHomNay = 0.0;

    $today = date('Y-m-d');
    $revStmt = $conn->prepare("
        SELECT COALESCE(SUM(soTien), 0) AS todayRevenue
        FROM hoadon
        WHERE trangThai = 'Đã thanh toán'
          AND DATE(COALESCE(thoiGianThanhToan, ngayTao)) = ?
    ");
    if ($revStmt) {
        $revStmt->bind_param('s', $today);
        $revStmt->execute();
        $doanhThuHomNay = (float)($revStmt->get_result()->fetch_assoc()['todayRevenue'] ?? 0);
        $revStmt->close();
    }

    $filtered = [];
    foreach ($allSummaries as $s) {
        $status = $s['billingStatus'];
        if ($status === 'Chưa thanh toán') {
            $choThuCount++;
        } elseif ($status === 'Thanh toán một phần') {
            $motPhanCount++;
        } elseif ($status === 'Đã thanh toán đủ') {
            $daThuDuCount++;
        }
        $tongConPhaiThu += (float)$s['totals']['conPhaiThu'];

        if ($tab === 'unpaid' && $s['totals']['conPhaiThu'] <= 0) {
            continue;
        }
        if ($tab === 'paid' && $status !== 'Đã thanh toán đủ') {
            continue;
        }
        $filtered[] = $s;
    }

    api_success([
        'tab' => $tab,
        'date' => $dateParam,
        'stats' => [
            'choThu' => $choThuCount,
            'thanhToanMotPhan' => $motPhanCount,
            'daThuDu' => $daThuDuCount,
            'tongConPhaiThu' => $tongConPhaiThu,
            'doanhThuHomNay' => $doanhThuHomNay,
        ],
        'items' => $filtered,
    ], 'Tải danh sách hóa đơn thu ngân thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải danh sách thu ngân.');
}
