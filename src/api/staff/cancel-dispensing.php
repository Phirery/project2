<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('POST');
$rbac = require_staff_permission('pharmacy.dispense');
$cancelledByUserId = (int)$rbac['staff']['id'];
$tenNhanVien = (string)$rbac['staff']['tenNhanVien'];

$input = api_json_input(true);
$maDonThuoc = isset($input['maDonThuoc']) ? (int)$input['maDonThuoc'] : 0;
$lyDoHuy = trim((string)($input['lyDoHuy'] ?? ''));

if ($maDonThuoc <= 0) {
    api_error('Thiếu mã đơn thuốc hợp lệ.', 400);
}
if ($lyDoHuy === '' || mb_strlen($lyDoHuy, 'UTF-8') < 3) {
    api_error('Vui lòng nhập lý do hủy đơn thuốc (tối thiểu 3 ký tự).', 422);
}

try {
    $conn->begin_transaction();

    $lockStmt = $conn->prepare("
        SELECT
            dt.maDonThuoc,
            dt.trangThaiPhat,
            COALESCE(SUM(ct.soLuongDaPhat), 0) AS tongDaPhat
        FROM donthuoc dt
        LEFT JOIN chitietdonthuoc ct ON ct.maDonThuoc = dt.maDonThuoc
        WHERE dt.maDonThuoc = ?
        GROUP BY dt.maDonThuoc, dt.trangThaiPhat
        FOR UPDATE
    ");
    if (!$lockStmt) {
        throw new RuntimeException('Không thể khóa đơn thuốc để hủy.');
    }
    $lockStmt->bind_param('i', $maDonThuoc);
    $lockStmt->execute();
    $row = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$row) {
        $conn->rollback();
        api_error('Không tìm thấy đơn thuốc cần hủy.', 404);
    }

    if ($row['trangThaiPhat'] !== 'Chờ phát' || (int)$row['tongDaPhat'] > 0) {
        $conn->rollback();
        api_error('Chỉ có thể hủy đơn thuốc ở trạng thái Chờ phát và chưa phát lần nào.', 409);
    }

    $upStmt = $conn->prepare("
        UPDATE donthuoc
        SET trangThaiPhat = 'Hủy',
            lyDoHuy = ?,
            cancelledBy = ?,
            cancelledAt = NOW()
        WHERE maDonThuoc = ?
    ");
    if (!$upStmt) {
        throw new RuntimeException('Không thể cập nhật trạng thái hủy đơn thuốc.');
    }
    $upStmt->bind_param('sii', $lyDoHuy, $cancelledByUserId, $maDonThuoc);
    $upStmt->execute();
    $upStmt->close();

    $conn->commit();

    api_success([
        'maDonThuoc' => $maDonThuoc,
        'trangThaiPhat' => 'Hủy',
        'lyDoHuy' => $lyDoHuy,
        'tenNhanVienHuy' => $tenNhanVien,
    ], 'Đã hủy phát đơn thuốc theo yêu cầu.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    api_server_error($e, 'Không thể hủy đơn thuốc.');
}
