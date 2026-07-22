<?php
require_once __DIR__ . '/../config/app-env.php';

function vnpay_get_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'tmnCode' => getConfigValue('VNPAY_TMNCODE') ?: '8690W4GN',
        'hashSecret' => getConfigValue('VNPAY_HASH_SECRET') ?: '37S7MUFH9FXW7HXGDLB2NUCVD0M7O2IL',
        'paymentUrl' => getConfigValue('VNPAY_PAYMENT_URL') ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'apiBaseUrl' => rtrim(getConfigValue('APP_API_BASE_URL') ?: '', '/'),
        'frontendBaseUrl' => rtrim(getConfigValue('APP_BASE_URL') ?: APP_BASE_URL, '/'),
        'returnPath' => '/api/payment/vnpay-return.php',
        'ipnPath' => '/api/payment/vnpay-ipn.php',
        'version' => getConfigValue('VNPAY_VERSION') ?: '2.1.0',
        'locale' => getConfigValue('VNPAY_LOCALE') ?: 'vn',
        'currency' => getConfigValue('VNPAY_CURRENCY') ?: 'VND',
        'orderType' => getConfigValue('VNPAY_ORDER_TYPE') ?: 'other',
        'holdMinutes' => max(1, (int)(getConfigValue('VNPAY_HOLD_MINUTES') ?: 15)),
    ];

    if ($config['apiBaseUrl'] === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($host !== '' && $scriptName !== '') {
            $projectRoot = dirname($scriptName, 3);
            if ($projectRoot === '\\' || $projectRoot === '/') {
                $projectRoot = '';
            }
            $config['apiBaseUrl'] = rtrim($scheme . '://' . $host . $projectRoot, '/');
        }
    }

    if ($config['apiBaseUrl'] === '') {
        $config['apiBaseUrl'] = rtrim(APP_BASE_URL, '/');
    }

    return $config;
}

function vnpay_get_payment_return_url(): string
{
    $config = vnpay_get_config();
    return $config['apiBaseUrl'] . $config['returnPath'];
}

function vnpay_get_payment_ipn_url(): string
{
    $config = vnpay_get_config();
    return $config['apiBaseUrl'] . $config['ipnPath'];
}

function vnpay_get_hold_minutes(): int
{
    return vnpay_get_config()['holdMinutes'];
}

function vnpay_get_client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $value = trim((string)$_SERVER[$key]);
        if ($value === '') {
            continue;
        }

        if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
            $parts = array_map('trim', explode(',', $value));
            $value = $parts[0] ?? '';
        }

        if ($value !== '') {
            return $value;
        }
    }

    return '127.0.0.1';
}

function vnpay_normalize_params(array $params): array
{
    $normalized = [];
    foreach ($params as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if ($key === 'vnp_SecureHash' || $key === 'vnp_SecureHashType') {
            continue;
        }

        if ($value === null) {
            continue;
        }

        $value = is_string($value) ? trim($value) : (string)$value;
        if ($value === '') {
            continue;
        }

        if (strncmp($key, 'vnp_', 4) !== 0) {
            continue;
        }

        $normalized[$key] = $value;
    }

    ksort($normalized, SORT_STRING);
    return $normalized;
}

function vnpay_build_hash_data(array $params): string
{
    $pairs = [];
    foreach (vnpay_normalize_params($params) as $key => $value) {
        $pairs[] = urlencode($key) . '=' . urlencode((string)$value);
    }

    return implode('&', $pairs);
}

function vnpay_create_secure_hash(string $hashData, string $hashSecret): string
{
    return hash_hmac('sha512', $hashData, $hashSecret);
}

function vnpay_build_payment_url(array $params, ?string $hashSecret = null, ?string $paymentBaseUrl = null): string
{
    $config = vnpay_get_config();
    $hashSecret = $hashSecret ?: $config['hashSecret'];
    $paymentBaseUrl = $paymentBaseUrl ?: $config['paymentUrl'];

    $hashData = vnpay_build_hash_data($params);
    $params['vnp_SecureHashType'] = 'HMACSHA512';
    $params['vnp_SecureHash'] = vnpay_create_secure_hash($hashData, $hashSecret);

    ksort($params, SORT_STRING);
    return $paymentBaseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function vnpay_response_message(string $responseCode): string
{
    $messages = [
        '00' => 'Giao dịch thành công',
        '07' => 'Trừ tiền thành công nhưng giao dịch bị nghi ngờ',
        '09' => 'Thẻ/Tài khoản chưa đăng ký Internet Banking',
        '10' => 'Xác thực thông tin thẻ/tài khoản không đúng quá 3 lần',
        '11' => 'Đã hết hạn chờ thanh toán',
        '12' => 'Thẻ/Tài khoản bị khóa',
        '24' => 'Khách hàng hủy giao dịch',
        '51' => 'Tài khoản không đủ số dư',
        '65' => 'Tài khoản vượt quá hạn mức giao dịch trong ngày',
        '99' => 'Lỗi khác từ hệ thống',
    ];

    return $messages[$responseCode] ?? 'Lỗi thanh toán không xác định';
}

function vnpay_sanitize_order_info(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Thanh toan dich vu y te';
    }

    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($value === false) {
        $value = trim($value);
    }

    $value = preg_replace('/[^A-Za-z0-9 _-]+/', ' ', (string)$value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    $value = trim((string)$value);

    if ($value === '') {
        return 'Thanh toan dich vu y te';
    }

    return mb_substr($value, 0, 255, 'UTF-8');
}

function vnpay_extract_order_id(array $request): int
{
    if (isset($request['vnp_TxnRef'])) {
        return (int)$request['vnp_TxnRef'];
    }

    if (isset($request['maHoaDon'])) {
        return (int)$request['maHoaDon'];
    }

    return 0;
}

function vnpay_verify_signature(array $request, string $hashSecret): array
{
    $receivedHash = trim((string)($request['vnp_SecureHash'] ?? ''));
    $hashData = vnpay_build_hash_data($request);
    $calculatedHash = vnpay_create_secure_hash($hashData, $hashSecret);

    return [
        'valid' => $receivedHash !== '' && hash_equals(strtolower($calculatedHash), strtolower($receivedHash)),
        'receivedHash' => $receivedHash,
        'calculatedHash' => $calculatedHash,
        'hashData' => $hashData,
    ];
}

function vnpay_process_callback(mysqli $conn, array $request): array
{
    $config = vnpay_get_config();
    $verification = vnpay_verify_signature($request, $config['hashSecret']);
    if (!$verification['valid']) {
        return [
            'ok' => false,
            'rspCode' => '97',
            'message' => 'Invalid Checksum',
            'paymentSuccess' => false,
            'paymentCode' => trim((string)($request['vnp_ResponseCode'] ?? '')),
            'paymentMessage' => 'Chữ ký không hợp lệ',
        ];
    }

    $maHoaDon = vnpay_extract_order_id($request);
    if ($maHoaDon <= 0) {
        return [
            'ok' => false,
            'rspCode' => '01',
            'message' => 'Order Not Found',
            'paymentSuccess' => false,
            'paymentCode' => trim((string)($request['vnp_ResponseCode'] ?? '')),
            'paymentMessage' => 'Thiếu mã hóa đơn',
        ];
    }

    $responseCode = trim((string)($request['vnp_ResponseCode'] ?? ''));
    $transactionStatus = trim((string)($request['vnp_TransactionStatus'] ?? ''));
    $transactionNo = trim((string)($request['vnp_TransactionNo'] ?? ''));
    $amount = (int)($request['vnp_Amount'] ?? 0);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT
                hd.maHoaDon,
                hd.maLichKham,
                hd.soTien,
                hd.trangThai AS hoaDonTrangThai,
                hd.phuongThuc,
                hd.vnp_TransactionNo,
                lk.trangThai AS lichTrangThai,
                lk.ghiChu
             FROM hoadon hd
             JOIN lichkham lk ON lk.maLichKham = hd.maLichKham
             WHERE hd.maHoaDon = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param('i', $maHoaDon);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$invoice) {
            $conn->rollback();
            return [
                'ok' => false,
                'rspCode' => '01',
                'message' => 'Order Not Found',
                'paymentSuccess' => false,
                'paymentCode' => $responseCode,
                'paymentMessage' => 'Không tìm thấy hóa đơn',
            ];
        }

        $expectedAmount = (int)round(((float)$invoice['soTien']) * 100);
        if ($amount !== $expectedAmount) {
            $conn->rollback();
            return [
                'ok' => false,
                'rspCode' => '04',
                'message' => 'Invalid Amount',
                'paymentSuccess' => false,
                'paymentCode' => $responseCode,
                'paymentMessage' => 'Sai số tiền thanh toán',
                'invoice' => $invoice,
            ];
        }

        $isPaymentSuccess = ($responseCode === '00' && $transactionStatus === '00');
        $alreadyConfirmed = ($invoice['hoaDonTrangThai'] === 'Đã thanh toán' && $invoice['lichTrangThai'] === 'Đã đặt');

        if ($alreadyConfirmed) {
            $conn->commit();
            return [
                'ok' => true,
                'rspCode' => '02',
                'message' => 'Order already confirmed',
                'paymentSuccess' => true,
                'paymentCode' => $responseCode,
                'paymentMessage' => vnpay_response_message($responseCode),
                'invoice' => $invoice,
                'isDuplicate' => true,
            ];
        }

        if ($isPaymentSuccess) {
            $updateInvoice = $conn->prepare(
                "UPDATE hoadon
                 SET trangThai = 'Đã thanh toán',
                     phuongThuc = 'VNPAY',
                     vnp_TransactionNo = ?
                 WHERE maHoaDon = ?"
            );
            $updateInvoice->bind_param('si', $transactionNo, $maHoaDon);
            $updateInvoice->execute();
            $updateInvoice->close();

            $updateAppointment = $conn->prepare(
                "UPDATE lichkham
                 SET trangThai = 'Đã đặt'
                 WHERE maLichKham = ?
                   AND trangThai = 'Chờ'"
            );
            $maLichKham = (int)$invoice['maLichKham'];
            $updateAppointment->bind_param('i', $maLichKham);
            $updateAppointment->execute();
            $updateAppointment->close();

            $conn->commit();

            return [
                'ok' => true,
                'rspCode' => '00',
                'message' => 'Confirm Success',
                'paymentSuccess' => true,
                'paymentCode' => $responseCode,
                'paymentMessage' => vnpay_response_message($responseCode),
                'invoice' => array_merge($invoice, [
                    'vnp_TransactionNo' => $transactionNo,
                    'hoaDonTrangThai' => 'Đã thanh toán',
                    'lichTrangThai' => 'Đã đặt',
                ]),
            ];
        }

        $holdNote = 'Thanh toán VNPay không thành công';
        $responseText = vnpay_response_message($responseCode);
        if ($responseText !== '') {
            $holdNote .= ' (' . $responseText . ')';
        }

        if ($invoice['lichTrangThai'] === 'Chờ') {
            $updateAppointment = $conn->prepare(
                "UPDATE lichkham
                 SET trangThai = 'Hủy',
                     nguoiHuy = 'hethong',
                     ghiChu = CASE
                        WHEN ghiChu IS NULL OR TRIM(ghiChu) = '' THEN CONCAT('[Lý do hủy]: ', ?)
                        WHEN ghiChu LIKE '%[Lý do hủy]%' THEN ghiChu
                        ELSE CONCAT(ghiChu, '\n[Lý do hủy]: ', ?)
                     END
                 WHERE maLichKham = ?"
            );
            $maLichKham = (int)$invoice['maLichKham'];
            $updateAppointment->bind_param('ssi', $holdNote, $holdNote, $maLichKham);
            $updateAppointment->execute();
            $updateAppointment->close();
        }

        $updateInvoice = $conn->prepare(
            "UPDATE hoadon
             SET phuongThuc = COALESCE(phuongThuc, 'VNPAY'),
                 vnp_TransactionNo = CASE
                    WHEN ? <> '' THEN ?
                    ELSE vnp_TransactionNo
                 END
             WHERE maHoaDon = ?"
        );
        $updateInvoice->bind_param('ssi', $transactionNo, $transactionNo, $maHoaDon);
        $updateInvoice->execute();
        $updateInvoice->close();

        $conn->commit();

        return [
            'ok' => true,
            'rspCode' => '00',
            'message' => 'Confirm Success',
            'paymentSuccess' => false,
            'paymentCode' => $responseCode,
            'paymentMessage' => $responseText,
            'invoice' => array_merge($invoice, [
                'hoaDonTrangThai' => $invoice['hoaDonTrangThai'],
                'lichTrangThai' => $invoice['lichTrangThai'] === 'Chờ' ? 'Hủy' : $invoice['lichTrangThai'],
                'vnp_TransactionNo' => $transactionNo !== '' ? $transactionNo : $invoice['vnp_TransactionNo'],
            ]),
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return [
            'ok' => false,
            'rspCode' => '99',
            'message' => 'Unknown error',
            'paymentSuccess' => false,
            'paymentCode' => $responseCode,
            'paymentMessage' => 'Lỗi hệ thống: ' . $e->getMessage(),
        ];
    }
}

function vnpay_build_payment_params(array $overrides = []): array
{
    $config = vnpay_get_config();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $expire = $now->modify('+' . $config['holdMinutes'] . ' minutes');

    $params = array_merge([
        'vnp_Version' => $config['version'],
        'vnp_Command' => 'pay',
        'vnp_TmnCode' => $config['tmnCode'],
        'vnp_Amount' => 0,
        'vnp_CurrCode' => $config['currency'],
        'vnp_TxnRef' => '',
        'vnp_OrderInfo' => '',
        'vnp_OrderType' => $config['orderType'],
        'vnp_Locale' => $config['locale'],
        'vnp_ReturnUrl' => vnpay_get_payment_return_url(),
        'vnp_IpAddr' => vnpay_get_client_ip(),
        'vnp_CreateDate' => $now->format('YmdHis'),
        'vnp_ExpireDate' => $expire->format('YmdHis'),
    ], $overrides);

    $params['vnp_Amount'] = (int)$params['vnp_Amount'];
    $params['vnp_OrderInfo'] = vnpay_sanitize_order_info((string)($params['vnp_OrderInfo'] ?? ''));

    return $params;
}
