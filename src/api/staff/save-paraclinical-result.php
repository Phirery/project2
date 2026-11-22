<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/clinical-orders.php';

api_require_method('POST');
$rbac = require_staff_permission([
    'cls.xet_nghiem.perform',
    'cls.cdha.perform',
    'cls.noi_soi.perform',
]);

$input = api_json_input(true);
$maChiTietChiDinh = isset($input['maChiTietChiDinh']) ? (int)$input['maChiTietChiDinh'] : 0;
$action = strtolower(trim((string)($input['action'] ?? 'submit')));

if ($maChiTietChiDinh <= 0) {
    api_error('Thiếu mã chi tiết chỉ định cận lâm sàng.', 400);
}
if (!in_array($action, ['draft', 'submit'], true)) {
    api_error('Hành động không hợp lệ (chỉ chấp nhận draft hoặc submit).', 400);
}

$ketLuan = trim((string)($input['ketLuan'] ?? ''));
$nhanXet = trim((string)($input['nhanXet'] ?? ''));
$deNghi = trim((string)($input['deNghi'] ?? ''));
$chiSoKetQua = $input['chiSoKetQua'] ?? null;
$attachmentsInput = $input['tepDinhKem'] ?? [];

if ($action === 'submit' && $ketLuan === '') {
    api_error('Vui lòng nhập kết luận cận lâm sàng trước khi trả kết quả để bác sĩ duyệt.', 422);
}

if ($chiSoKetQua !== null && !is_array($chiSoKetQua)) {
    api_error('Dữ liệu chỉ số kết quả có cấu trúc không hợp lệ.', 422);
}

$chiSoJson = null;
if (is_array($chiSoKetQua)) {
    if (count($chiSoKetQua) > 100) {
        api_error('Số lượng trường kết quả vượt quá giới hạn cho phép (tối đa 100).', 422);
    }
    $chiSoJson = json_encode($chiSoKetQua, JSON_UNESCAPED_UNICODE);
    if ($chiSoJson === false || strlen($chiSoJson) > 65535) {
        api_error('Dung lượng dữ liệu chỉ số kết quả vượt quá giới hạn (64KB).', 422);
    }
}

// Validate danh sách tệp đính kèm / URL và metadata
if (!is_array($attachmentsInput)) {
    api_error('Danh sách tệp đính kèm không hợp lệ.', 422);
}
if (count($attachmentsInput) > 5) {
    api_error('Chỉ được đính kèm tối đa 5 ảnh/liên kết kết quả.', 422);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$maxFileSizeBytes = 10 * 1024 * 1024; // 10 MB
$normalizedAttachments = [];

foreach ($attachmentsInput as $idx => $att) {
    if (!is_array($att)) {
        api_error('Metadata tệp đính kèm không hợp lệ tại vị trí #' . ($idx + 1) . '.', 422);
    }
    $url = trim((string)($att['url'] ?? ''));
    $title = trim((string)($att['title'] ?? ''));
    $mimeType = trim((string)($att['mimeType'] ?? ''));
    $sizeBytes = isset($att['sizeBytes']) && $att['sizeBytes'] !== '' && $att['sizeBytes'] !== null
        ? (int)$att['sizeBytes']
        : null;

    if ($url === '' || strlen($url) > 500) {
        api_error('URL tệp đính kèm #' . ($idx + 1) . ' không được trống và tối đa 500 ký tự.', 422);
    }

    $isValidHttpUrl = filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
    $isSafeRelative = preg_match('#^(/DO_AN/|uploads/)#i', $url) === 1 && !str_contains($url, '..');

    if (!$isValidHttpUrl && !$isSafeRelative) {
        api_error('URL tệp đính kèm #' . ($idx + 1) . ' phải là đường dẫn http(s):// hoặc nội bộ hợp lệ.', 422);
    }

    if (strlen($title) > 120) {
        api_error('Tên/mô tả tệp đính kèm #' . ($idx + 1) . ' tối đa 120 ký tự.', 422);
    }

    if ($mimeType !== '' && !in_array($mimeType, $allowedMimes, true)) {
        api_error('Định dạng tệp đính kèm #' . ($idx + 1) . ' không được hỗ trợ (chỉ JPG, PNG, WEBP, PDF).', 422);
    }

    if ($sizeBytes !== null && ($sizeBytes <= 0 || $sizeBytes > $maxFileSizeBytes)) {
        api_error('Dung lượng tệp đính kèm #' . ($idx + 1) . ' phải từ 1 byte đến tối đa 10 MB.', 422);
    }

    $normalizedAttachments[] = [
        'url' => $url,
        'title' => $title !== '' ? $title : ('Tệp đính kèm #' . ($idx + 1)),
        'mimeType' => $mimeType !== '' ? $mimeType : null,
        'sizeBytes' => $sizeBytes,
    ];
}

$attachmentsJson = !empty($normalizedAttachments)
    ? json_encode($normalizedAttachments, JSON_UNESCAPED_UNICODE)
    : null;

$maNhanVien = (string)$rbac['staff']['maNhanVien'];
$userId = (int)$_SESSION['id'];
$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $orderStmt = $conn->prepare("
        SELECT
            ct.maChiTietChiDinh,
            ct.maChiDinh,
            ct.maHoSo,
            ct.maDichVu,
            ct.nhomDichVu,
            ct.trangThai AS orderStatus,
            cd.maBenhNhan,
            hs.trangThai AS recordLegacyStatus,
            hs.trangThaiQuyTrinh AS recordWorkflowStatus,
            hs.isDeleted
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN hosobenhan hs ON ct.maHoSo = hs.maHoSo
        WHERE ct.maChiTietChiDinh = ?
        FOR UPDATE
    ");
    $orderStmt->bind_param('i', $maChiTietChiDinh);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();

    if (!$order || (int)$order['isDeleted'] === 1) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy chỉ định cận lâm sàng.', 404);
    }

    $requiredPerm = clinical_service_group_permission((string)$order['nhomDichVu']);
    if ($requiredPerm === null || !staff_has_permission($rbac['permissions'], $requiredPerm)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Bạn không có quyền nhập/trả kết quả cho nhóm dịch vụ này.', 403, ['code' => 'FORBIDDEN_PERMISSION']);
    }

    if ($order['orderStatus'] === CLINICAL_ORDER_STATUS_CANCELLED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ định đã bị hủy, không thể lưu hoặc trả kết quả.', 409);
    }

    if ($order['orderStatus'] === CLINICAL_ORDER_STATUS_APPROVED
        || ($order['recordLegacyStatus'] ?? '') === CLINICAL_RECORD_LEGACY_COMPLETED
        || ($order['recordWorkflowStatus'] ?? '') === CLINICAL_RECORD_STATUS_COMPLETED
    ) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Kết quả hoặc hồ sơ đã được bác sĩ duyệt/khóa, không thể chỉnh sửa.', 409);
    }

    $existingStmt = $conn->prepare("
        SELECT maKetQua, trangThai, phienBan
        FROM ket_qua_can_lam_sang
        WHERE maChiTietChiDinh = ?
        FOR UPDATE
    ");
    $existingStmt->bind_param('i', $maChiTietChiDinh);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing && $existing['trangThai'] === CLINICAL_RESULT_STATUS_APPROVED) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Kết quả này đã được bác sĩ duyệt, không thể chỉnh sửa.', 409);
    }

    if (in_array($order['orderStatus'], [CLINICAL_ORDER_STATUS_CREATED, CLINICAL_ORDER_STATUS_WAITING], true)) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Vui lòng nhận bệnh nhân ("Bắt đầu thực hiện") trước khi nhập hoặc trả kết quả.', 409);
    }

    $targetResultStatus = $action === 'submit'
        ? CLINICAL_RESULT_STATUS_PENDING_REVIEW
        : CLINICAL_RESULT_STATUS_DRAFT;

    $prevResultStatus = $existing ? (string)$existing['trangThai'] : null;
    if ($existing && !clinical_can_transition(clinical_result_transitions(), $prevResultStatus, $targetResultStatus)) {
        if (!($prevResultStatus === CLINICAL_RESULT_STATUS_PENDING_REVIEW && $action === 'submit')) {
            $conn->rollback();
            $transactionStarted = false;
            api_error('Không thể chuyển trạng thái kết quả từ "' . $prevResultStatus . '" sang "' . $targetResultStatus . '".', 409);
        }
    }

    $phienBan = $existing ? ((int)$existing['phienBan'] + ($action === 'submit' ? 1 : 0)) : 1;
    $ketLuanParam = $ketLuan !== '' ? $ketLuan : null;
    $nhanXetParam = $nhanXet !== '' ? $nhanXet : null;
    $deNghiParam = $deNghi !== '' ? $deNghi : null;
    $maChiDinh = (int)$order['maChiDinh'];
    $maHoSo = (string)$order['maHoSo'];
    $maBenhNhan = (string)$order['maBenhNhan'];
    $maDichVu = (int)$order['maDichVu'];

    if ($existing) {
        $maKetQua = (int)$existing['maKetQua'];
        $upRes = $conn->prepare("
            UPDATE ket_qua_can_lam_sang
            SET trangThai = ?,
                chiSoKetQua = ?,
                ketLuan = ?,
                nhanXet = ?,
                deNghi = ?,
                tepDinhKem = ?,
                maNhanVienThucHien = ?,
                thoiGianTraKetQua = " . ($action === 'submit' ? 'NOW()' : 'thoiGianTraKetQua') . ",
                lyDoYeuCauLamLai = NULL,
                phienBan = ?
            WHERE maKetQua = ?
        ");
        $upRes->bind_param(
            'sssssssii',
            $targetResultStatus,
            $chiSoJson,
            $ketLuanParam,
            $nhanXetParam,
            $deNghiParam,
            $attachmentsJson,
            $maNhanVien,
            $phienBan,
            $maKetQua
        );
        if (!$upRes->execute()) {
            throw new RuntimeException('Không thể cập nhật kết quả CLS: ' . $upRes->error);
        }
        $upRes->close();
    } else {
        $insRes = $conn->prepare("
            INSERT INTO ket_qua_can_lam_sang (
                maChiTietChiDinh, maChiDinh, maHoSo, maBenhNhan, maDichVu,
                trangThai, chiSoKetQua, ketLuan, nhanXet, deNghi, tepDinhKem,
                maNhanVienThucHien, thoiGianTraKetQua, phienBan
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, " . ($action === 'submit' ? 'NOW()' : 'NULL') . ", ?
            )
        ");
        $insRes->bind_param(
            'iississsssssi',
            $maChiTietChiDinh,
            $maChiDinh,
            $maHoSo,
            $maBenhNhan,
            $maDichVu,
            $targetResultStatus,
            $chiSoJson,
            $ketLuanParam,
            $nhanXetParam,
            $deNghiParam,
            $attachmentsJson,
            $maNhanVien,
            $phienBan
        );
        if (!$insRes->execute()) {
            throw new RuntimeException('Không thể lưu kết quả CLS: ' . $insRes->error);
        }
        $maKetQua = (int)$conn->insert_id;
        $insRes->close();
    }

    if ($action === 'submit') {
        $resultedStatus = CLINICAL_ORDER_STATUS_RESULTED;
        $upOrder = $conn->prepare("
            UPDATE chi_dinh_chi_tiet
            SET trangThai = ?, maNhanVienXuLy = ?
            WHERE maChiTietChiDinh = ?
        ");
        $upOrder->bind_param('ssi', $resultedStatus, $maNhanVien, $maChiTietChiDinh);
        $upOrder->execute();
        $upOrder->close();

        $queueCompleted = CLINICAL_QUEUE_STATUS_COMPLETED;
        $upQueue = $conn->prepare("
            UPDATE hangdoi_dichvu
            SET trangThai = ?,
                maNhanVienThucHien = ?,
                thoiGianHoanThanh = COALESCE(thoiGianHoanThanh, NOW())
            WHERE maChiTietChiDinh = ? AND trangThai <> 'Hủy'
        ");
        $upQueue->bind_param('ssi', $queueCompleted, $maNhanVien, $maChiTietChiDinh);
        $upQueue->execute();
        $upQueue->close();

        clinical_sync_parent_order_status($conn, $maChiDinh);
        clinical_recompute_record_workflow_status($conn, $maHoSo);
    }

    $historyAction = $existing
        ? ($action === 'submit' ? 'tra_ket_qua' : 'sua_nhap_ket_qua')
        : ($action === 'submit' ? 'tra_ket_qua' : 'tao_nhap_ket_qua');

    clinical_append_order_history(
        $conn,
        $maChiTietChiDinh,
        $historyAction,
        $prevResultStatus ?? $order['orderStatus'],
        $targetResultStatus,
        $userId,
        $maNhanVien,
        null,
        $action === 'submit' ? 'KTV trả kết quả cận lâm sàng chờ bác sĩ duyệt.' : 'KTV lưu nháp kết quả cận lâm sàng.',
        [
            'chiSoKetQua' => $chiSoKetQua,
            'ketLuan' => $ketLuan,
            'nhanXet' => $nhanXet,
            'deNghi' => $deNghi,
            'tepDinhKem' => $normalizedAttachments,
        ],
        $maKetQua,
        $phienBan
    );

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maKetQua' => $maKetQua,
        'maChiTietChiDinh' => $maChiTietChiDinh,
        'trangThaiKetQua' => $targetResultStatus,
        'trangThaiChiDinh' => $action === 'submit' ? CLINICAL_ORDER_STATUS_RESULTED : $order['orderStatus'],
        'phienBan' => $phienBan,
    ], $action === 'submit'
        ? 'Đã trả kết quả cận lâm sàng (trạng thái Chờ duyệt).'
        : 'Đã lưu nháp kết quả cận lâm sàng.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể lưu kết quả cận lâm sàng.');
}
