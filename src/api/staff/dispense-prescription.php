<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/medicine-stock.php';

api_require_method('POST');
$rbac = require_staff_permission('pharmacy.dispense');
$maDuocSi = (string)$rbac['staff']['maNhanVien'];
$nguoiPhatUserId = (int)$rbac['staff']['id'];

$input = api_json_input(true);
$maDonThuoc = isset($input['maDonThuoc']) ? (int)$input['maDonThuoc'] : 0;
$items = is_array($input['items'] ?? null) ? $input['items'] : [];
$clientRequestId = isset($input['clientRequestId']) ? trim((string)$input['clientRequestId']) : null;
$huongDanSuDung = trim((string)($input['huongDanSuDung'] ?? ''));
$ghiChu = trim((string)($input['ghiChu'] ?? ''));

if ($maDonThuoc <= 0) {
    api_error('Thiếu mã đơn thuốc hợp lệ.', 400);
}

if ($clientRequestId !== null && $clientRequestId !== '' && strlen($clientRequestId) > 64) {
    api_error('clientRequestId vượt quá 64 ký tự.', 400);
}

if (empty($items)) {
    api_error('Vui lòng chọn ít nhất một thuốc có số lượng phát hợp lệ.', 422);
}

try {
    ensureMedicineStockLogTable($conn);
    $conn->begin_transaction();

    $result = dispensePrescriptionWithStockLock(
        $conn,
        $maDonThuoc,
        $maDuocSi,
        $nguoiPhatUserId,
        $items,
        $clientRequestId,
        $huongDanSuDung,
        $ghiChu
    );

    $conn->commit();

    $msg = !empty($result['idempotentReplay'])
        ? 'Yêu cầu phát thuốc này đã được xử lý trước đó (không trừ kho lại).'
        : ($result['trangThaiPhat'] === 'Đã phát đủ'
            ? 'Đã phát đủ đơn thuốc và cập nhật tồn kho.'
            : 'Đã phát một phần đơn thuốc và lưu phiếu phát.');

    api_success($result, $msg, !empty($result['idempotentReplay']) ? 200 : 201);
} catch (RuntimeException $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    $code = (int)$e->getCode();
    if ($code >= 400 && $code < 500) {
        api_error($e->getMessage(), $code);
    }
    api_server_error($e, $e->getMessage() ?: 'Không thể phát thuốc.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    api_server_error($e, 'Lỗi hệ thống khi phát thuốc.');
}
