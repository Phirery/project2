<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('POST');
$rbac = require_staff_permission('nursing.screen');
$maNhanVien = (string)$rbac['staff']['maNhanVien'];
$tenNhanVien = (string)$rbac['staff']['tenNhanVien'];

$input = api_json_input(true);
$maHangDoi = isset($input['maHangDoi']) ? (int)$input['maHangDoi'] : 0;
$action = strtolower(trim((string)($input['action'] ?? 'transfer')));

if ($maHangDoi <= 0) {
    api_error('Thiếu mã hàng đợi cần sàng lọc.', 400);
}
if (!in_array($action, ['draft', 'transfer'], true)) {
    api_error('Hành động không hợp lệ (chỉ chấp nhận draft hoặc transfer).', 400);
}

function parseOptionalInt($val, string $field, string $label, int $min, int $max, array &$errors): ?int
{
    if ($val === null || $val === '') {
        return null;
    }
    if (!is_numeric($val) || (string)(int)$val !== (string)trim((string)$val)) {
        $errors[$field] = "$label phải là số nguyên.";
        return null;
    }
    $num = (int)$val;
    if ($num < $min || $num > $max) {
        $errors[$field] = "$label phải nằm trong khoảng $min – $max.";
        return null;
    }
    return $num;
}

function parseOptionalFloat($val, string $field, string $label, float $min, float $max, array &$errors): ?float
{
    if ($val === null || $val === '') {
        return null;
    }
    if (!is_numeric($val)) {
        $errors[$field] = "$label phải là số hợp lệ.";
        return null;
    }
    $num = round((float)$val, 2);
    if ($num < $min || $num > $max) {
        $errors[$field] = "$label phải nằm trong khoảng $min – $max.";
        return null;
    }
    return $num;
}

$errors = [];
$mach = parseOptionalInt($input['mach'] ?? null, 'mach', 'Mạch', 30, 220, $errors);
$huyetApTamThu = parseOptionalInt($input['huyetApTamThu'] ?? null, 'huyetApTamThu', 'Huyết áp tâm thu', 50, 260, $errors);
$huyetApTamTruong = parseOptionalInt($input['huyetApTamTruong'] ?? null, 'huyetApTamTruong', 'Huyết áp tâm trương', 30, 160, $errors);
$nhietDo = parseOptionalFloat($input['nhietDo'] ?? null, 'nhietDo', 'Nhiệt độ', 34.0, 42.5, $errors);
$spo2 = parseOptionalInt($input['spo2'] ?? null, 'spo2', 'SpO2', 50, 100, $errors);
$nhipTho = parseOptionalInt($input['nhipTho'] ?? null, 'nhipTho', 'Nhịp thở', 6, 60, $errors);
$canNang = parseOptionalFloat($input['canNang'] ?? null, 'canNang', 'Cân nặng', 1.0, 300.0, $errors);
$chieuCao = parseOptionalFloat($input['chieuCao'] ?? null, 'chieuCao', 'Chiều cao', 30.0, 250.0, $errors);

if (($huyetApTamThu !== null && $huyetApTamTruong === null) || ($huyetApTamThu === null && $huyetApTamTruong !== null)) {
    $errors['huyetAp'] = 'Vui lòng nhập đầy đủ cả huyết áp tâm thu và tâm trương.';
} elseif ($huyetApTamThu !== null && $huyetApTamTruong !== null && $huyetApTamThu <= $huyetApTamTruong) {
    $errors['huyetAp'] = 'Huyết áp tâm thu phải lớn hơn huyết áp tâm trương.';
}

$diUng = trim((string)($input['diUng'] ?? ''));
$canhBaoLamSang = trim((string)($input['canhBaoLamSang'] ?? ''));
$ghiChu = trim((string)($input['ghiChu'] ?? ''));

$hasAnyClinicalData = $mach !== null
    || $huyetApTamThu !== null
    || $nhietDo !== null
    || $spo2 !== null
    || $nhipTho !== null
    || $canNang !== null
    || $chieuCao !== null
    || $diUng !== ''
    || $canhBaoLamSang !== ''
    || $ghiChu !== '';

if (!$hasAnyClinicalData) {
    $errors['general'] = 'Vui lòng nhập ít nhất một chỉ số sinh tồn hoặc ghi chú sàng lọc.';
}

if (!empty($errors)) {
    api_error('Dữ liệu sàng lọc chưa hợp lệ. Vui lòng kiểm tra lại các trường đánh dấu.', 422, ['errors' => $errors]);
}

$newStatus = $action === 'transfer' ? 'Đã chuyển bác sĩ' : 'Đã sàng lọc';
$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $queueStmt = $conn->prepare("
        SELECT hd.maHangDoi, hd.maLichKham, hd.maBacSi, hd.trangThai, lk.maBenhNhan
        FROM hangdoikham hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        WHERE hd.maHangDoi = ?
        FOR UPDATE
    ");
    $queueStmt->bind_param('i', $maHangDoi);
    $queueStmt->execute();
    $queueRow = $queueStmt->get_result()->fetch_assoc();
    $queueStmt->close();

    if (!$queueRow) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy lượt check-in trong hàng đợi.', 404);
    }

    if ($queueRow['trangThai'] !== 'Đang chờ') {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Bệnh nhân không còn ở trạng thái "Đang chờ" (hiện tại: ' . $queueRow['trangThai'] . ').', 409);
    }

    $existingStmt = $conn->prepare("
        SELECT maSangLoc, trangThai
        FROM sang_loc_dieu_duong
        WHERE maHangDoi = ?
        FOR UPDATE
    ");
    $existingStmt->bind_param('i', $maHangDoi);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing && $existing['trangThai'] === 'Đã chuyển bác sĩ') {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Bệnh nhân này đã được sàng lọc và chuyển sang hàng đợi bác sĩ trước đó.', 409);
    }

    $maLichKham = (int)$queueRow['maLichKham'];
    $maBenhNhan = (string)$queueRow['maBenhNhan'];

    $upsertSql = "
        INSERT INTO sang_loc_dieu_duong (
            maHangDoi, maLichKham, maBenhNhan, maNhanVienSangLoc,
            mach, huyetApTamThu, huyetApTamTruong, nhietDo, spo2, nhipTho, canNang, chieuCao,
            diUng, canhBaoLamSang, ghiChu, trangThai, thoiGianSangLoc, thoiGianChuyenBacSi
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, NOW(), " . ($action === 'transfer' ? 'NOW()' : 'NULL') . "
        )
        ON DUPLICATE KEY UPDATE
            maNhanVienSangLoc = VALUES(maNhanVienSangLoc),
            mach = VALUES(mach),
            huyetApTamThu = VALUES(huyetApTamThu),
            huyetApTamTruong = VALUES(huyetApTamTruong),
            nhietDo = VALUES(nhietDo),
            spo2 = VALUES(spo2),
            nhipTho = VALUES(nhipTho),
            canNang = VALUES(canNang),
            chieuCao = VALUES(chieuCao),
            diUng = VALUES(diUng),
            canhBaoLamSang = VALUES(canhBaoLamSang),
            ghiChu = VALUES(ghiChu),
            trangThai = VALUES(trangThai),
            thoiGianSangLoc = NOW(),
            thoiGianChuyenBacSi = " . ($action === 'transfer' ? 'NOW()' : 'NULL') . "
    ";

    $stmt = $conn->prepare($upsertSql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị lưu phiếu sàng lọc: ' . $conn->error);
    }

    $diUngParam = $diUng !== '' ? $diUng : null;
    $canhBaoParam = $canhBaoLamSang !== '' ? $canhBaoLamSang : null;
    $ghiChuParam = $ghiChu !== '' ? $ghiChu : null;

    $stmt->bind_param(
        'iissiiidiiddssss',
        $maHangDoi,
        $maLichKham,
        $maBenhNhan,
        $maNhanVien,
        $mach,
        $huyetApTamThu,
        $huyetApTamTruong,
        $nhietDo,
        $spo2,
        $nhipTho,
        $canNang,
        $chieuCao,
        $diUngParam,
        $canhBaoParam,
        $ghiChuParam,
        $newStatus
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Không thể lưu dữ liệu sàng lọc: ' . $stmt->error);
    }
    $stmt->close();

    $fetchStmt = $conn->prepare("
        SELECT maSangLoc, trangThai, maNhanVienSangLoc, thoiGianSangLoc, thoiGianChuyenBacSi
        FROM sang_loc_dieu_duong
        WHERE maHangDoi = ?
        LIMIT 1
    ");
    $fetchStmt->bind_param('i', $maHangDoi);
    $fetchStmt->execute();
    $saved = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maSangLoc' => (int)($saved['maSangLoc'] ?? 0),
        'maHangDoi' => $maHangDoi,
        'maLichKham' => $maLichKham,
        'trangThai' => $saved['trangThai'] ?? $newStatus,
        'maNhanVienSangLoc' => $saved['maNhanVienSangLoc'] ?? $maNhanVien,
        'tenNhanVienSangLoc' => $tenNhanVien,
        'thoiGianSangLoc' => $saved['thoiGianSangLoc'] ?? null,
        'thoiGianChuyenBacSi' => $saved['thoiGianChuyenBacSi'] ?? null,
    ], $action === 'transfer'
        ? 'Đã lưu sàng lọc và chuyển bệnh nhân tới hàng đợi bác sĩ.'
        : 'Đã lưu nháp thông tin sàng lọc điều dưỡng.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể lưu phiếu sàng lọc điều dưỡng.');
}
