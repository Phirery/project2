<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/billing-helper.php';
require_once '../../includes/vnpay.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/medical-record-events.php';

api_require_method('POST');
$rbac = require_staff_permission('billing.collect');
$maThuNgan = (string)$rbac['staff']['maNhanVien'];
$nguoiThuUserId = (int)$rbac['staff']['id'];

$input = api_json_input(true);
$maLichKham = isset($input['maLichKham']) ? (int)$input['maLichKham'] : 0;
$itemKeysRaw = is_array($input['itemKeys'] ?? null) ? $input['itemKeys'] : [];
$phuongThuc = trim((string)($input['phuongThuc'] ?? 'TienMat'));
$clientRequestId = isset($input['clientRequestId']) ? trim((string)$input['clientRequestId']) : null;
$ghiChu = trim((string)($input['ghiChu'] ?? ''));

if ($clientRequestId === '') {
    $clientRequestId = null;
}

if ($maLichKham <= 0) {
    api_error('Thiếu mã lượt khám hợp lệ.', 400);
}
if (!in_array($phuongThuc, ['TienMat', 'VNPAY'], true)) {
    api_error('Phương thức thanh toán không hợp lệ (chỉ chấp nhận TienMat hoặc VNPAY).', 422);
}
if ($clientRequestId !== null && strlen($clientRequestId) > 64) {
    api_error('clientRequestId vượt quá 64 ký tự.', 400);
}

$requestedKeys = array_values(array_unique(array_filter(array_map(
    static fn($k): string => trim((string)$k),
    $itemKeysRaw
), static fn(string $k): bool => $k !== '')));

if (empty($requestedKeys)) {
    api_error('Vui lòng chọn ít nhất một khoản mục chưa thanh toán.', 422);
}

$createdInvoiceId = 0;
$isCashPaid = false;

try {
    $conn->begin_transaction();

    // 1. Idempotency check by clientRequestId
    if ($clientRequestId !== null) {
        $replayStmt = $conn->prepare("
            SELECT maHoaDon, maLichKham, soTien, trangThai, phuongThuc, vnp_TransactionNo, thoiGianThanhToan
            FROM hoadon
            WHERE clientRequestId = ?
            LIMIT 1
            FOR UPDATE
        ");
        $replayStmt->bind_param('s', $clientRequestId);
        $replayStmt->execute();
        $existingInv = $replayStmt->get_result()->fetch_assoc();
        $replayStmt->close();

        if ($existingInv) {
            if ((int)$existingInv['maLichKham'] !== $maLichKham) {
                $conn->rollback();
                api_error('Mã yêu cầu thanh toán đã được dùng cho lượt khám khác.', 409);
            }

            $replaySummary = billing_build_encounter_summary($conn, $maLichKham);
            $paymentUrl = null;
            if ($existingInv['trangThai'] === 'Chưa thanh toán' && $existingInv['phuongThuc'] === 'VNPAY') {
                $payParams = vnpay_build_payment_params([
                    'vnp_Amount' => (int)round(((float)$existingInv['soTien']) * 100),
                    'vnp_TxnRef' => (string)$existingInv['maHoaDon'],
                    'vnp_OrderInfo' => 'Thanh toan tong hop #' . $existingInv['maHoaDon'],
                ]);
                $paymentUrl = vnpay_build_payment_url($payParams);
            }

            $conn->commit();
            api_success([
                'idempotentReplay' => true,
                'maHoaDon' => (int)$existingInv['maHoaDon'],
                'soTien' => (float)$existingInv['soTien'],
                'trangThai' => $existingInv['trangThai'],
                'phuongThuc' => $existingInv['phuongThuc'],
                'paymentUrl' => $paymentUrl,
                'summary' => $replaySummary,
            ], 'Yêu cầu thanh toán này đã được ghi nhận trước đó (không thu trùng).', 200);
        }
    }

    // 2. Lock encounter & existing billing rows
    $lkLock = $conn->prepare("SELECT maLichKham, trangThai FROM lichkham WHERE maLichKham = ? LIMIT 1 FOR UPDATE");
    $lkLock->bind_param('i', $maLichKham);
    $lkLock->execute();
    $lkRow = $lkLock->get_result()->fetch_assoc();
    $lkLock->close();

    if (!$lkRow) {
        $conn->rollback();
        api_error('Không tìm thấy lượt khám.', 404);
    }
    if ($lkRow['trangThai'] === 'Hủy') {
        $conn->rollback();
        api_error('Lượt khám đã bị hủy, không thể thực hiện thu ngân.', 409);
    }

    $invLock = $conn->prepare("SELECT maHoaDon FROM hoadon WHERE maLichKham = ? FOR UPDATE");
    $invLock->bind_param('i', $maLichKham);
    $invLock->execute();
    $invLock->close();

    $hdctLock = $conn->prepare("SELECT id FROM hoadon_chitiet WHERE maLichKham = ? FOR UPDATE");
    $hdctLock->bind_param('i', $maLichKham);
    $hdctLock->execute();
    $hdctLock->close();

    // 3. Recompute authoritative server items
    $summary = billing_build_encounter_summary($conn, $maLichKham);
    $itemsByKey = [];
    foreach ($summary['items'] as $it) {
        $itemsByKey[$it['itemKey']] = $it;
    }

    $selectedItems = [];
    $totalAmount = 0.0;
    foreach ($requestedKeys as $k) {
        if (!isset($itemsByKey[$k])) {
            $conn->rollback();
            api_error("Khoản mục '$k' không hợp lệ, đã bị hủy hoặc thuốc chưa phát tại quầy dược.", 422);
        }
        $it = $itemsByKey[$k];
        if (!empty($it['isPaid'])) {
            $conn->rollback();
            api_error("Khoản mục '{$it['moTa']}' đã được thanh toán trước đó. Không thể thu trùng.", 409);
        }
        $selectedItems[] = $it;
        $totalAmount += (float)$it['thanhTien'];
    }

    if ($totalAmount <= 0) {
        $conn->rollback();
        api_error('Tổng số tiền thanh toán phải lớn hơn 0.', 422);
    }

    // 4. Create or settle invoice
    $consultKey = 'kham:' . $maLichKham;
    $reusableBookingInvId = (!empty($summary['unpaidBookingInvoiceId']) && in_array($consultKey, $requestedKeys, true))
        ? (int)$summary['unpaidBookingInvoiceId']
        : 0;

    if ($phuongThuc === 'TienMat') {
        if ($reusableBookingInvId > 0) {
            $upInv = $conn->prepare("
                UPDATE hoadon
                SET loaiHoaDon = 'TongHop',
                    soTien = ?,
                    trangThai = 'Đã thanh toán',
                    phuongThuc = 'TienMat',
                    maThuNgan = ?,
                    nguoiThuUserId = ?,
                    clientRequestId = ?,
                    ghiChu = ?,
                    thoiGianThanhToan = NOW()
                WHERE maHoaDon = ?
            ");
            $upInv->bind_param('dsissi', $totalAmount, $maThuNgan, $nguoiThuUserId, $clientRequestId, $ghiChu, $reusableBookingInvId);
            $upInv->execute();
            $upInv->close();
            $createdInvoiceId = $reusableBookingInvId;
        } else {
            $insInv = $conn->prepare("
                INSERT INTO hoadon (
                    maLichKham, loaiHoaDon, soTien, trangThai, phuongThuc,
                    maThuNgan, nguoiThuUserId, clientRequestId, ghiChu, thoiGianThanhToan
                )
                VALUES (?, 'TongHop', ?, 'Đã thanh toán', 'TienMat', ?, ?, ?, ?, NOW())
            ");
            $insInv->bind_param('idsiss', $maLichKham, $totalAmount, $maThuNgan, $nguoiThuUserId, $clientRequestId, $ghiChu);
            $insInv->execute();
            $createdInvoiceId = (int)$conn->insert_id;
            $insInv->close();
        }

        // Mark any stale pending VNPAY detail lines for these keys as Hủy before locking paidKey
        $cancelStale = $conn->prepare("
            UPDATE hoadon_chitiet
            SET trangThai = 'Hủy', paidKey = NULL
            WHERE maLichKham = ? AND itemKey = ? AND trangThai = 'Chờ thanh toán'
        ");
        $insDetail = $conn->prepare("
            INSERT INTO hoadon_chitiet (
                maHoaDon, maLichKham, nhomChiPhi, itemKey, paidKey,
                refId, moTa, soLuong, donGia, thanhTien, trangThai
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Đã thanh toán')
        ");

        foreach ($selectedItems as $it) {
            $itemKey = (string)$it['itemKey'];
            $cancelStale->bind_param('is', $maLichKham, $itemKey);
            $cancelStale->execute();

            $nhom = (string)$it['nhomChiPhi'];
            $refId = (string)$it['refId'];
            $moTa = (string)$it['moTa'];
            $soLuong = (int)$it['soLuong'];
            $donGia = (float)$it['donGia'];
            $thanhTien = (float)$it['thanhTien'];
            $insDetail->bind_param(
                'iisssssidd',
                $createdInvoiceId,
                $maLichKham,
                $nhom,
                $itemKey,
                $itemKey,
                $refId,
                $moTa,
                $soLuong,
                $donGia,
                $thanhTien
            );
            $insDetail->execute();
        }
        $cancelStale->close();
        $insDetail->close();
        $isCashPaid = true;
    } else {
        // VNPAY checkout for selected items
        if ($reusableBookingInvId > 0) {
            $upInv = $conn->prepare("
                UPDATE hoadon
                SET loaiHoaDon = 'TongHop',
                    soTien = ?,
                    phuongThuc = 'VNPAY',
                    maThuNgan = ?,
                    nguoiThuUserId = ?,
                    clientRequestId = ?,
                    ghiChu = ?
                WHERE maHoaDon = ?
            ");
            $upInv->bind_param('dsissi', $totalAmount, $maThuNgan, $nguoiThuUserId, $clientRequestId, $ghiChu, $reusableBookingInvId);
            $upInv->execute();
            $upInv->close();
            $createdInvoiceId = $reusableBookingInvId;
        } else {
            $insInv = $conn->prepare("
                INSERT INTO hoadon (
                    maLichKham, loaiHoaDon, soTien, trangThai, phuongThuc,
                    maThuNgan, nguoiThuUserId, clientRequestId, ghiChu
                )
                VALUES (?, 'TongHop', ?, 'Chưa thanh toán', 'VNPAY', ?, ?, ?, ?)
            ");
            $insInv->bind_param('idsiss', $maLichKham, $totalAmount, $maThuNgan, $nguoiThuUserId, $clientRequestId, $ghiChu);
            $insInv->execute();
            $createdInvoiceId = (int)$conn->insert_id;
            $insInv->close();
        }

        $insDetail = $conn->prepare("
            INSERT INTO hoadon_chitiet (
                maHoaDon, maLichKham, nhomChiPhi, itemKey, paidKey,
                refId, moTa, soLuong, donGia, thanhTien, trangThai
            )
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'Chờ thanh toán')
        ");
        foreach ($selectedItems as $it) {
            $nhom = (string)$it['nhomChiPhi'];
            $itemKey = (string)$it['itemKey'];
            $refId = (string)$it['refId'];
            $moTa = (string)$it['moTa'];
            $soLuong = (int)$it['soLuong'];
            $donGia = (float)$it['donGia'];
            $thanhTien = (float)$it['thanhTien'];
            $insDetail->bind_param(
                'iissssidd',
                $createdInvoiceId,
                $maLichKham,
                $nhom,
                $itemKey,
                $refId,
                $moTa,
                $soLuong,
                $donGia,
                $thanhTien
            );
            $insDetail->execute();
        }
        $insDetail->close();
    }

    $conn->commit();

    if ($isCashPaid && $createdInvoiceId > 0) {
        try {
            sendPaymentStatusEmail($conn, $createdInvoiceId, 'Đã thanh toán', '');
        } catch (Throwable $mailErr) {
            error_log('Cashier payment email error: ' . $mailErr->getMessage());
        }
        try {
            notifyInvoicePaid($conn, $createdInvoiceId, $totalAmount, 'TienMat');
        } catch (Throwable $ignored) {
        }
    }

    $paymentUrl = null;
    if ($phuongThuc === 'VNPAY') {
        $payParams = vnpay_build_payment_params([
            'vnp_Amount' => (int)round($totalAmount * 100),
            'vnp_TxnRef' => (string)$createdInvoiceId,
            'vnp_OrderInfo' => 'Thanh toan tong hop #' . $createdInvoiceId,
        ]);
        $paymentUrl = vnpay_build_payment_url($payParams);
    }

    $updatedSummary = billing_build_encounter_summary($conn, $maLichKham);

    api_success([
        'idempotentReplay' => false,
        'maHoaDon' => $createdInvoiceId,
        'soTien' => $totalAmount,
        'trangThai' => $phuongThuc === 'TienMat' ? 'Đã thanh toán' : 'Chưa thanh toán',
        'phuongThuc' => $phuongThuc,
        'paymentUrl' => $paymentUrl,
        'summary' => $updatedSummary,
    ], $phuongThuc === 'TienMat'
        ? "Đã thu tiền mặt thành công cho hóa đơn #$createdInvoiceId."
        : "Đã khởi tạo liên kết thanh toán VNPAY cho hóa đơn #$createdInvoiceId.", 201);
} catch (mysqli_sql_exception $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    if ((int)$e->getCode() === 1062) {
        api_error('Khoản mục hoặc mã giao dịch đã được thanh toán bởi thao tác khác (chống thu trùng).', 409);
    }
    api_server_error($e, 'Lỗi cơ sở dữ liệu khi xử lý thanh toán.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    api_server_error($e, 'Không thể xử lý thanh toán hóa đơn.');
}
