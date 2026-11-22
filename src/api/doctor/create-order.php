<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/clinical-orders.php';

api_require_method('POST');
require_role('bacsi');

$input = api_json_input(true);
$maHoSo = trim((string)($input['maHoSo'] ?? ''));
$headerPriority = trim((string)($input['doUuTien'] ?? 'Bình thường'));
$ghiChuLamSang = trim((string)($input['ghiChuLamSang'] ?? ''));
$servicesInput = is_array($input['services'] ?? null) ? $input['services'] : [];

if ($maHoSo === '') {
    api_error('Thiếu mã hồ sơ bệnh án.', 400);
}
if (empty($servicesInput)) {
    api_error('Vui lòng chọn ít nhất một dịch vụ cận lâm sàng để chỉ định.', 400);
}

$allowedPriorities = ['Bình thường', 'Ưu tiên', 'Khẩn'];
if (!in_array($headerPriority, $allowedPriorities, true)) {
    $headerPriority = 'Bình thường';
}

$normalizedServices = [];
$seenServiceIds = [];
foreach ($servicesInput as $item) {
    if (is_numeric($item)) {
        $maDichVu = (int)$item;
        $batBuoc = 1;
        $doUuTien = $headerPriority;
        $ghiChu = '';
    } elseif (is_array($item)) {
        $maDichVu = (int)($item['maDichVu'] ?? 0);
        $batBuoc = array_key_exists('batBuoc', $item) ? ((bool)$item['batBuoc'] ? 1 : 0) : 1;
        $doUuTien = trim((string)($item['doUuTien'] ?? $headerPriority));
        if (!in_array($doUuTien, $allowedPriorities, true)) {
            $doUuTien = $headerPriority;
        }
        $ghiChu = trim((string)($item['ghiChu'] ?? ''));
    } else {
        continue;
    }

    if ($maDichVu <= 0 || isset($seenServiceIds[$maDichVu])) {
        continue;
    }
    $seenServiceIds[$maDichVu] = true;
    $normalizedServices[] = [
        'maDichVu' => $maDichVu,
        'batBuoc' => $batBuoc,
        'doUuTien' => $doUuTien,
        'ghiChu' => $ghiChu,
    ];
}

if (empty($normalizedServices)) {
    api_error('Danh sách dịch vụ chỉ định không hợp lệ.', 400);
}

$transactionStarted = false;

try {
    $docStmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ? LIMIT 1");
    $docStmt->bind_param('i', $_SESSION['id']);
    $docStmt->execute();
    $maBacSi = $docStmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $docStmt->close();

    if (!$maBacSi) {
        api_error('Không tìm thấy thông tin bác sĩ.', 403);
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $recStmt = $conn->prepare("
        SELECT maHoSo, maBenhNhan, maBacSi, maLichKham, trangThai, trangThaiQuyTrinh
        FROM hosobenhan
        WHERE maHoSo = ? AND isDeleted = 0
        FOR UPDATE
    ");
    $recStmt->bind_param('s', $maHoSo);
    $recStmt->execute();
    $record = $recStmt->get_result()->fetch_assoc();
    $recStmt->close();

    if (!$record) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không tìm thấy hồ sơ bệnh án.', 404);
    }

    if ((string)$record['maBacSi'] !== (string)$maBacSi) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Chỉ bác sĩ phụ trách hồ sơ này mới được phép tạo chỉ định.', 403);
    }

    if (
        ($record['trangThai'] ?? '') === CLINICAL_RECORD_LEGACY_COMPLETED
        || in_array($record['trangThaiQuyTrinh'] ?? '', [CLINICAL_RECORD_STATUS_COMPLETED, CLINICAL_RECORD_STATUS_CANCELLED], true)
    ) {
        $conn->rollback();
        $transactionStarted = false;
        api_error('Không thể tạo chỉ định mới cho hồ sơ đã hoàn tất hoặc đã hủy.', 409);
    }

    $serviceIds = array_column($normalizedServices, 'maDichVu');
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $types = str_repeat('i', count($serviceIds));
    $svcStmt = $conn->prepare("
        SELECT maDichVu, maCode, tenDichVu, nhomDichVu, tenNhom, gia, isActive
        FROM dichvu_can_lam_sang
        WHERE maDichVu IN ($placeholders)
    ");
    $svcStmt->bind_param($types, ...$serviceIds);
    $svcStmt->execute();
    $svcRows = $svcStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $svcStmt->close();

    $serviceMap = [];
    foreach ($svcRows as $row) {
        $serviceMap[(int)$row['maDichVu']] = $row;
    }

    foreach ($normalizedServices as $item) {
        $svc = $serviceMap[$item['maDichVu']] ?? null;
        if (!$svc || (int)$svc['isActive'] !== 1) {
            $conn->rollback();
            $transactionStarted = false;
            api_error('Một hoặc nhiều dịch vụ cận lâm sàng không tồn tại hoặc đã ngừng áp dụng.', 422);
        }
    }

    $maPhieuChiDinh = 'CD' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $maLichKham = $record['maLichKham'] !== null ? (int)$record['maLichKham'] : null;
    $maBenhNhan = (string)$record['maBenhNhan'];
    $userId = (int)$_SESSION['id'];
    $initialStatus = CLINICAL_ORDER_STATUS_WAITING;
    $ghiChuHeaderParam = $ghiChuLamSang !== '' ? $ghiChuLamSang : null;

    $insHeader = $conn->prepare("
        INSERT INTO chi_dinh_can_lam_sang (
            maPhieuChiDinh, maHoSo, maLichKham, maBenhNhan, maBacSiChiDinh,
            doUuTien, ghiChuLamSang, trangThai, createdBy, updatedBy
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insHeader->bind_param(
        'ssisssssii',
        $maPhieuChiDinh,
        $maHoSo,
        $maLichKham,
        $maBenhNhan,
        $maBacSi,
        $headerPriority,
        $ghiChuHeaderParam,
        $initialStatus,
        $userId,
        $userId
    );
    if (!$insHeader->execute()) {
        throw new RuntimeException('Không thể tạo phiếu chỉ định CLS: ' . $insHeader->error);
    }
    $maChiDinh = (int)$conn->insert_id;
    $insHeader->close();

    $today = date('Y-m-d');
    $createdItems = [];

    foreach ($normalizedServices as $item) {
        $svc = $serviceMap[$item['maDichVu']];
        $nhomDichVu = (string)$svc['nhomDichVu'];
        $donGia = (float)$svc['gia'];
        $ghiChuItemParam = $item['ghiChu'] !== '' ? $item['ghiChu'] : null;

        $insDetail = $conn->prepare("
            INSERT INTO chi_dinh_chi_tiet (
                maChiDinh, maHoSo, maDichVu, nhomDichVu, donGia, batBuoc, doUuTien, ghiChu, trangThai
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insDetail->bind_param(
            'isisdisss',
            $maChiDinh,
            $maHoSo,
            $item['maDichVu'],
            $nhomDichVu,
            $donGia,
            $item['batBuoc'],
            $item['doUuTien'],
            $ghiChuItemParam,
            $initialStatus
        );
        if (!$insDetail->execute()) {
            throw new RuntimeException('Không thể lưu chi tiết chỉ định CLS: ' . $insDetail->error);
        }
        $maChiTietChiDinh = (int)$conn->insert_id;
        $insDetail->close();

        $soThuTu = clinical_next_service_queue_number($conn, $nhomDichVu, $today);
        $queueStatus = CLINICAL_QUEUE_STATUS_WAITING;
        $queueNoteParam = $ghiChuItemParam ?? $ghiChuHeaderParam;

        $insQueue = $conn->prepare("
            INSERT INTO hangdoi_dichvu (
                maChiTietChiDinh, maChiDinh, maHoSo, maBenhNhan, maDichVu,
                nhomDichVu, ngay, soThuTu, doUuTien, trangThai, ghiChu
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insQueue->bind_param(
            'iissississs',
            $maChiTietChiDinh,
            $maChiDinh,
            $maHoSo,
            $maBenhNhan,
            $item['maDichVu'],
            $nhomDichVu,
            $today,
            $soThuTu,
            $item['doUuTien'],
            $queueStatus,
            $queueNoteParam
        );
        if (!$insQueue->execute()) {
            throw new RuntimeException('Không thể đẩy chỉ định vào hàng đợi dịch vụ: ' . $insQueue->error);
        }
        $maHangDoiDichVu = (int)$conn->insert_id;
        $insQueue->close();

        clinical_append_order_history(
            $conn,
            $maChiTietChiDinh,
            'tao_chi_dinh',
            CLINICAL_ORDER_STATUS_CREATED,
            CLINICAL_ORDER_STATUS_WAITING,
            $userId,
            null,
            $maBacSi,
            $ghiChuHeaderParam ?: 'Bác sĩ tạo chỉ định và chuyển hàng đợi bộ phận.',
            [
                'maDichVu' => $item['maDichVu'],
                'tenDichVu' => $svc['tenDichVu'],
                'nhomDichVu' => $nhomDichVu,
                'donGia' => $donGia,
                'batBuoc' => (bool)$item['batBuoc'],
                'doUuTien' => $item['doUuTien'],
                'soThuTuHangDoi' => $soThuTu,
            ]
        );

        $createdItems[] = [
            'maChiTietChiDinh' => $maChiTietChiDinh,
            'maDichVu' => $item['maDichVu'],
            'tenDichVu' => $svc['tenDichVu'],
            'nhomDichVu' => $nhomDichVu,
            'tenNhom' => $svc['tenNhom'],
            'donGia' => $donGia,
            'batBuoc' => (bool)$item['batBuoc'],
            'doUuTien' => $item['doUuTien'],
            'trangThai' => $initialStatus,
            'maHangDoiDichVu' => $maHangDoiDichVu,
            'soThuTu' => $soThuTu,
        ];
    }

    $workflowStatus = clinical_recompute_record_workflow_status($conn, $maHoSo);
    $upBy = $conn->prepare("UPDATE hosobenhan SET updatedBy = ? WHERE maHoSo = ?");
    $upBy->bind_param('is', $userId, $maHoSo);
    $upBy->execute();
    $upBy->close();

    $conn->commit();
    $transactionStarted = false;

    api_success([
        'maChiDinh' => $maChiDinh,
        'maPhieuChiDinh' => $maPhieuChiDinh,
        'maHoSo' => $maHoSo,
        'trangThaiQuyTrinh' => $workflowStatus,
        'items' => $createdItems,
    ], 'Đã tạo ' . count($createdItems) . ' chỉ định cận lâm sàng và chuyển sang hàng đợi bộ phận.', 201);
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    api_server_error($e, 'Không thể tạo chỉ định cận lâm sàng.');
}
