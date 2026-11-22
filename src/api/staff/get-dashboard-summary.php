<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('GET');
$rbac = require_staff_permission('staff.dashboard.view');

$today = date('Y-m-d');
$permissions = $rbac['permissions'];

function countSingleInt(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

try {
    $metrics = [];

    if (staff_has_permission($permissions, 'reception.checkin')) {
        $metrics['reception'] = [
            'pendingCheckInToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM lichkham lk
                 LEFT JOIN hangdoikham hd ON hd.maLichKham = lk.maLichKham
                 WHERE lk.ngayKham = ? AND lk.trangThai = 'Đã đặt' AND hd.maHangDoi IS NULL",
                's',
                [$today]
            ),
            'waitingQueueToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total FROM hangdoikham WHERE ngay = ? AND trangThai = 'Đang chờ'",
                's',
                [$today]
            ),
        ];
    }

    if (staff_has_permission($permissions, 'nursing.screen')) {
        $metrics['nursing'] = [
            'pendingScreeningToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM hangdoikham hd
                 LEFT JOIN sang_loc_dieu_duong sl ON sl.maHangDoi = hd.maHangDoi
                 WHERE hd.ngay = ? AND hd.trangThai = 'Đang chờ'
                   AND (sl.maSangLoc IS NULL OR sl.trangThai <> 'Đã chuyển bác sĩ')",
                's',
                [$today]
            ),
            'screenedToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM sang_loc_dieu_duong
                 WHERE DATE(thoiGianSangLoc) = ?",
                's',
                [$today]
            ),
        ];
    }

    $clsGroups = [
        'xet-nghiem' => 'cls.xet_nghiem.perform',
        'chan-doan-hinh-anh' => 'cls.cdha.perform',
        'noi-soi' => 'cls.noi_soi.perform',
    ];
    foreach ($clsGroups as $groupCode => $perm) {
        if (staff_has_permission($permissions, $perm)) {
            $metrics['cls'][$groupCode] = [
                'waiting' => countSingleInt(
                    $conn,
                    "SELECT COUNT(*) AS total FROM hangdoi_dichvu WHERE ngay = ? AND nhomDichVu = ? AND trangThai = 'Đang chờ'",
                    'ss',
                    [$today, $groupCode]
                ),
                'inProgress' => countSingleInt(
                    $conn,
                    "SELECT COUNT(*) AS total FROM hangdoi_dichvu WHERE ngay = ? AND nhomDichVu = ? AND trangThai = 'Đang thực hiện'",
                    'ss',
                    [$today, $groupCode]
                ),
            ];
        }
    }

    if (staff_has_permission($permissions, 'pharmacy.dispense')) {
        $metrics['pharmacy'] = [
            'waitingDispense' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total FROM donthuoc WHERE trangThaiPhat IN ('Chờ phát', 'Đã phát một phần')"
            ),
            'dispensedToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total FROM phieu_phat_thuoc WHERE DATE(thoiGianPhat) = ? AND trangThai = 'Hoàn thành'",
                's',
                [$today]
            ),
        ];
    }

    if (staff_has_permission($permissions, 'billing.collect')) {
        $metrics['billing'] = [
            'unpaidInvoices' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total FROM hoadon WHERE trangThai = 'Chưa thanh toán'"
            ),
            'paidToday' => countSingleInt(
                $conn,
                "SELECT COUNT(*) AS total FROM hoadon WHERE trangThai = 'Đã thanh toán' AND DATE(ngayTao) = ?",
                's',
                [$today]
            ),
        ];
    }

    api_success([
        'date' => $today,
        'isDemo' => $rbac['isDemo'],
        'roles' => $rbac['roles'],
        'permissions' => $rbac['permissions'],
        'modules' => $rbac['modules'],
        'metrics' => $metrics,
    ], 'Lấy tổng quan cổng nhân viên thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải dữ liệu tổng quan nhân viên.');
}
