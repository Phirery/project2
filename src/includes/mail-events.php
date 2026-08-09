<?php
require_once __DIR__ . '/send-mail.php';
require_once __DIR__ . '/../config/app-env.php';

function getMailNotificationConfig(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/../config/mail-notifications.php';
    if (file_exists($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded)) {
            $config = $loaded;
            return $config;
        }
    }

    $config = [
        'enabled' => true,
        'site_name' => 'Eden Health',
        'site_url' => APP_BASE_URL,
        'events' => [],
    ];
    return $config;
}

function isMailEventEnabled(string $eventCode): bool {
    $cfg = getMailNotificationConfig();
    if (!($cfg['enabled'] ?? true)) {
        return false;
    }

    $events = $cfg['events'] ?? [];
    if (array_key_exists($eventCode, $events)) {
        return (bool)$events[$eventCode];
    }

    return true;
}

function mailSiteName(): string {
    $cfg = getMailNotificationConfig();
    return $cfg['site_name'] ?? 'Eden Health';
}

function mailSiteUrl(): string {
    $cfg = getMailNotificationConfig();
    return rtrim(($cfg['site_url'] ?? APP_BASE_URL), '/');
}

function ensureMailNotificationLogTable(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS mail_notification_log (
            id INT NOT NULL AUTO_INCREMENT,
            event_code VARCHAR(64) NOT NULL,
            event_key VARCHAR(191) NOT NULL,
            recipient_email VARCHAR(150) NOT NULL,
            status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
            error_message TEXT DEFAULT NULL,
            payload LONGTEXT DEFAULT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mail_event_recipient (event_code, event_key, recipient_email),
            KEY idx_mail_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    $conn->query($sql);
    $done = true;
}

function hasMailBeenSent(mysqli $conn, string $eventCode, string $eventKey, string $recipientEmail): bool {
    ensureMailNotificationLogTable($conn);

    $stmt = $conn->prepare(
        "SELECT id FROM mail_notification_log WHERE event_code = ? AND event_key = ? AND recipient_email = ? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $eventCode, $eventKey, $recipientEmail);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

function logMailNotification(
    mysqli $conn,
    string $eventCode,
    string $eventKey,
    string $recipientEmail,
    string $status,
    ?string $errorMessage,
    array $payload = []
): void {
    ensureMailNotificationLogTable($conn);

    $status = in_array($status, ['sent', 'failed', 'skipped'], true) ? $status : 'sent';
    $payloadJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $conn->prepare(
        "INSERT INTO mail_notification_log (event_code, event_key, recipient_email, status, error_message, payload)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            error_message = VALUES(error_message),
            payload = VALUES(payload),
            sent_at = CURRENT_TIMESTAMP"
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ssssss', $eventCode, $eventKey, $recipientEmail, $status, $errorMessage, $payloadJson);
    $stmt->execute();
    $stmt->close();
}

function sendTransactionalMail(
    mysqli $conn,
    string $eventCode,
    string $eventKey,
    string $recipientEmail,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): array {
    $recipientEmail = trim($recipientEmail);
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'reason' => 'invalid_email'];
    }

    if (!isMailEventEnabled($eventCode)) {
        logMailNotification($conn, $eventCode, $eventKey, $recipientEmail, 'skipped', 'event_disabled');
        return ['success' => false, 'reason' => 'event_disabled'];
    }

    if (hasMailBeenSent($conn, $eventCode, $eventKey, $recipientEmail)) {
        return ['success' => false, 'reason' => 'duplicate'];
    }

    $ok = sendEmail($recipientEmail, $subject, $htmlBody, $textBody);

    if ($ok) {
        logMailNotification($conn, $eventCode, $eventKey, $recipientEmail, 'sent', null, ['subject' => $subject]);
        return ['success' => true];
    }

    $mailError = getLastMailError();
    $errorMessage = $mailError ?: 'send_failed';
    $payload = ['subject' => $subject];
    if ($mailError) {
        $payload['mail_error'] = $mailError;
    }

    logMailNotification($conn, $eventCode, $eventKey, $recipientEmail, 'failed', $errorMessage, $payload);

    $response = ['success' => false, 'reason' => 'send_failed'];
    if (isMailDebugEnabled() && $mailError) {
        $response['mail_error'] = $mailError;
    }
    return $response;
}

function formatVNDate(?string $date): string {
    if (!$date) {
        return 'N/A';
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return 'N/A';
    }

    return date('d/m/Y', $ts);
}

function formatVNDateTime(?string $dateTime): string {
    if (!$dateTime) {
        return 'N/A';
    }

    $ts = strtotime($dateTime);
    if ($ts === false) {
        return 'N/A';
    }

    return date('d/m/Y H:i', $ts);
}

function formatVNCurrency($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' VND';
}

function buildEmailLayout(string $title, string $contentHtml): string {
    $siteName = htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8');

    return "
    <!DOCTYPE html>
    <html lang='vi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
    </head>
    <body style='margin:0;padding:0;background:#f5f7fb;font-family:Segoe UI,Arial,sans-serif;'>
        <table width='100%' cellspacing='0' cellpadding='0' style='background:#f5f7fb;padding:24px 0;'>
            <tr>
                <td align='center'>
                    <table width='640' cellspacing='0' cellpadding='0' style='max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6ebf2;'>
                        <tr>
                            <td style='background:#2f7dd7;color:#ffffff;padding:20px 24px;font-size:20px;font-weight:700;'>{$siteName}</td>
                        </tr>
                        <tr>
                            <td style='padding:24px;color:#243447;line-height:1.6;font-size:14px;'>
                                {$contentHtml}
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:16px 24px;background:#f8fafc;color:#667085;font-size:12px;border-top:1px solid #e6ebf2;'>
                                Email giao dịch tự động từ {$siteName}. Vui lòng không trả lời email này.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
}

function buildAdminBroadcastEmailContent(string $recipientName, string $headline, string $content): string {
    $safeRecipient = htmlspecialchars($recipientName !== '' ? $recipientName : 'bạn', ENT_QUOTES, 'UTF-8');
    $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    $siteName = htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8');

    return "
        <h2 style='margin:0 0 12px;'>{$safeHeadline}</h2>
        <p>Xin chào <strong>{$safeRecipient}</strong>,</p>
        <div style='margin:18px 0;padding:18px;border:1px solid #e6ebf2;border-radius:12px;background:#f8fafc;line-height:1.7;white-space:pre-line;'>
            {$safeContent}
        </div>
        <p>Thông báo này được gửi từ <strong>{$siteName}</strong>.</p>
    ";
}

function sendAdminBroadcastMail(
    mysqli $conn,
    string $eventKey,
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $headline,
    string $content
): array {
    $subject = trim($subject) !== '' ? trim($subject) : trim($headline);
    $html = buildEmailLayout($subject, buildAdminBroadcastEmailContent($recipientName, $headline, $content));
    $text = trim($headline . "\n\n" . $content);

    return sendTransactionalMail(
        $conn,
        'admin_custom_broadcast',
        $eventKey,
        $recipientEmail,
        $subject,
        $html,
        $text
    );
}

function getAppointmentMailContext(mysqli $conn, int $maLichKham): ?array {
    $sql = "
        SELECT
            lk.maLichKham,
            lk.ngayKham,
            lk.maSuat,
            lk.maCa,
            lk.maGoi,
            lk.ghiChu,
            lk.trangThai,
            bn.maBenhNhan,
            bn.tenBenhNhan,
            nd_bn.email AS emailBenhNhan,
            bs.maBacSi,
            bs.tenBacSi,
            nd_bs.email AS emailBacSi,
            ca.tenCa,
            TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
            gk.tenGoi,
            gk.gia
        FROM lichkham lk
        LEFT JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd_bn ON bn.nguoiDungId = nd_bn.id
        LEFT JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN nguoidung nd_bs ON bs.nguoiDungId = nd_bs.id
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
        WHERE lk.maLichKham = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $maLichKham);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function appointmentTimeRange(array $ctx): string {
    if (!empty($ctx['gioBatDau']) && !empty($ctx['gioKetThuc'])) {
        return $ctx['gioBatDau'] . ' - ' . $ctx['gioKetThuc'];
    }

    return (string)($ctx['tenCa'] ?? 'N/A');
}

function sendAppointmentBookedEmails(mysqli $conn, int $maLichKham): array {
    $ctx = getAppointmentMailContext($conn, $maLichKham);
    if (!$ctx) {
        return ['success' => false, 'message' => 'appointment_not_found'];
    }

    $results = [];
    $dateLabel = formatVNDate($ctx['ngayKham']);
    $timeLabel = appointmentTimeRange($ctx);
    $package = $ctx['tenGoi'] ?? 'Gói khám';
    $price = isset($ctx['gia']) ? formatVNCurrency($ctx['gia']) : 'N/A';

    if (!empty($ctx['emailBenhNhan'])) {
        $subject = 'Xác nhận đặt lịch khám #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Xác nhận đặt lịch',
            "
            <h2 style='margin:0 0 12px;'>Đặt lịch thành công</h2>
            <p>Xin chào <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Bạn đã đặt lịch khám thành công tại <strong>" . htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8') . "</strong>.</p>
            <ul>
                <li>Mã lịch khám: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bác sĩ: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngày khám: <strong>{$dateLabel}</strong></li>
                <li>Khung giờ: <strong>{$timeLabel}</strong></li>
                <li>Gói khám: <strong>" . htmlspecialchars((string)$package, ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Chi phí dự kiến: <strong>{$price}</strong></li>
            </ul>
            <p>Nếu cần thay đổi, vui lòng quản lý lịch khám trong tài khoản cá nhân.</p>
            "
        );

        $eventKey = $ctx['maLichKham'] . ':booked:' . $ctx['ngayKham'] . ':' . $ctx['maSuat'];
        $results['patient'] = sendTransactionalMail(
            $conn,
            'appointment_booked_patient',
            $eventKey,
            (string)$ctx['emailBenhNhan'],
            $subject,
            $html
        );
    }

    if (!empty($ctx['emailBacSi'])) {
        $subject = 'Thông báo có lịch khám mới #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Lịch khám mới',
            "
            <h2 style='margin:0 0 12px;'>Bạn có lịch khám mới</h2>
            <p>Bác sĩ <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Hệ thống vừa ghi nhận lịch khám mới.</p>
            <ul>
                <li>Mã lịch khám: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bệnh nhân: <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngày khám: <strong>{$dateLabel}</strong></li>
                <li>Khung giờ: <strong>{$timeLabel}</strong></li>
                <li>Ghi chú: <strong>" . htmlspecialchars((string)($ctx['ghiChu'] ?? 'Không có'), ENT_QUOTES, 'UTF-8') . "</strong></li>
            </ul>
            "
        );

        $eventKey = $ctx['maLichKham'] . ':booked:' . $ctx['ngayKham'] . ':' . $ctx['maSuat'];
        $results['doctor'] = sendTransactionalMail(
            $conn,
            'appointment_booked_doctor',
            $eventKey,
            (string)$ctx['emailBacSi'],
            $subject,
            $html
        );
    }

    return ['success' => true, 'results' => $results];
}

function cancellationActorLabel(string $actor): string {
    $actor = strtolower(trim($actor));
    if ($actor === 'benhnhan') {
        return 'Bệnh nhân';
    }
    if ($actor === 'bacsi') {
        return 'Bác sĩ';
    }
    if ($actor === 'quantri') {
        return 'Quản trị viên';
    }
    if ($actor === 'hethong') {
        return 'Hệ thống';
    }
    return 'Đơn vị y tế';
}

function sendAppointmentCancelledEmails(mysqli $conn, int $maLichKham, string $cancelledBy, string $reason = ''): array {
    $ctx = getAppointmentMailContext($conn, $maLichKham);
    if (!$ctx) {
        return ['success' => false, 'message' => 'appointment_not_found'];
    }

    $actorLabel = cancellationActorLabel($cancelledBy);
    $reasonText = trim($reason) !== '' ? trim($reason) : 'Không có lý do cụ thể';
    $dateLabel = formatVNDate($ctx['ngayKham']);
    $timeLabel = appointmentTimeRange($ctx);
    $eventKey = $ctx['maLichKham'] . ':cancel:' . $ctx['ngayKham'] . ':' . md5($cancelledBy . ':' . $reasonText);

    $results = [];

    if (!empty($ctx['emailBenhNhan'])) {
        $subject = 'Thông báo hủy lịch khám #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Hủy lịch khám',
            "
            <h2 style='margin:0 0 12px;'>Lịch khám đã được hủy</h2>
            <p>Xin chào <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lịch khám của bạn đã được hủy bởi <strong>{$actorLabel}</strong>.</p>
            <ul>
                <li>Mã lịch khám: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bác sĩ: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngày khám: <strong>{$dateLabel}</strong></li>
                <li>Khung giờ: <strong>{$timeLabel}</strong></li>
                <li>Lý do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
            </ul>
            <p>Vui lòng đặt lịch mới nếu bạn vẫn có nhu cầu khám.</p>
            "
        );

        $results['patient'] = sendTransactionalMail(
            $conn,
            'appointment_cancelled_patient',
            $eventKey,
            (string)$ctx['emailBenhNhan'],
            $subject,
            $html
        );
    }

    if (!empty($ctx['emailBacSi'])) {
        $subject = 'Thông báo hủy lịch khám bệnh nhân #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Hủy lịch khám',
            "
            <h2 style='margin:0 0 12px;'>Lịch khám đã bị hủy</h2>
            <p>Bác sĩ <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lịch khám bệnh nhân đã được hủy bởi <strong>{$actorLabel}</strong>.</p>
            <ul>
                <li>Mã lịch khám: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bệnh nhân: <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngày khám: <strong>{$dateLabel}</strong></li>
                <li>Khung giờ: <strong>{$timeLabel}</strong></li>
                <li>Lý do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
            </ul>
            "
        );

        $results['doctor'] = sendTransactionalMail(
            $conn,
            'appointment_cancelled_doctor',
            $eventKey,
            (string)$ctx['emailBacSi'],
            $subject,
            $html
        );
    }

    return ['success' => true, 'results' => $results];
}

function sendAppointmentReminderEmail(mysqli $conn, int $maLichKham, string $reminderType): array {
    $ctx = getAppointmentMailContext($conn, $maLichKham);
    if (!$ctx || empty($ctx['emailBenhNhan'])) {
        return ['success' => false, 'message' => 'appointment_or_patient_email_missing'];
    }

    $normalizedType = $reminderType === '2h' ? '2h' : '24h';
    $eventCode = $normalizedType === '2h' ? 'appointment_reminder_2h' : 'appointment_reminder_24h';

    $dateLabel = formatVNDate($ctx['ngayKham']);
    $timeLabel = appointmentTimeRange($ctx);

    $titleLead = $normalizedType === '2h' ? 'Nhắc lịch khám trước 2 giờ' : 'Nhắc lịch khám trước 24 giờ';
    $subject = $titleLead . ' #' . $ctx['maLichKham'];

    $html = buildEmailLayout(
        'Nhắc lịch khám',
        "
        <h2 style='margin:0 0 12px;'>{$titleLead}</h2>
        <p>Xin chào <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Bạn có lịch khám sắp tới, vui lòng đến đúng giờ.</p>
        <ul>
            <li>Mã lịch khám: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
            <li>Bác sĩ: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Ngày khám: <strong>{$dateLabel}</strong></li>
            <li>Khung giờ: <strong>{$timeLabel}</strong></li>
            <li>Gói khám: <strong>" . htmlspecialchars((string)($ctx['tenGoi'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        "
    );

    $eventKey = $ctx['maLichKham'] . ':reminder:' . $normalizedType . ':' . $ctx['ngayKham'] . ':' . $ctx['maSuat'];

    return sendTransactionalMail(
        $conn,
        $eventCode,
        $eventKey,
        (string)$ctx['emailBenhNhan'],
        $subject,
        $html
    );
}

function sendAppointmentRescheduledEmails(mysqli $conn, int $maLichKham, array $oldContext, string $updatedBy = 'quantri'): array {
    $newContext = getAppointmentMailContext($conn, $maLichKham);
    if (!$newContext) {
        return ['success' => false, 'message' => 'appointment_not_found'];
    }

    $oldDoctor = (string)($oldContext['tenBacSi'] ?? 'N/A');
    $newDoctor = (string)($newContext['tenBacSi'] ?? 'N/A');

    $oldDate = formatVNDate($oldContext['ngayKham'] ?? null);
    $newDate = formatVNDate($newContext['ngayKham'] ?? null);

    $oldSlot = appointmentTimeRange($oldContext);
    $newSlot = appointmentTimeRange($newContext);

    $eventKey = $maLichKham . ':reschedule:' . md5(json_encode([$oldContext, $newContext], JSON_UNESCAPED_UNICODE));
    $actorLabel = cancellationActorLabel($updatedBy);

    $results = [];

    if (!empty($newContext['emailBenhNhan'])) {
        $subject = 'Thông báo cập nhật lịch khám #' . $maLichKham;
        $html = buildEmailLayout(
            'Cập nhật lịch khám',
            "
            <h2 style='margin:0 0 12px;'>Lịch khám đã được cập nhật</h2>
            <p>Xin chào <strong>" . htmlspecialchars((string)$newContext['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lịch khám của bạn đã được điều chỉnh bởi <strong>{$actorLabel}</strong>.</p>
            <table width='100%' cellpadding='6' cellspacing='0' style='border-collapse:collapse;border:1px solid #e6ebf2;'>
                <tr><td style='border:1px solid #e6ebf2;'><strong>Nội dung</strong></td><td style='border:1px solid #e6ebf2;'><strong>Trước</strong></td><td style='border:1px solid #e6ebf2;'><strong>Sau</strong></td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Ngày khám</td><td style='border:1px solid #e6ebf2;'>{$oldDate}</td><td style='border:1px solid #e6ebf2;'>{$newDate}</td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Khung giờ</td><td style='border:1px solid #e6ebf2;'>{$oldSlot}</td><td style='border:1px solid #e6ebf2;'>{$newSlot}</td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Bác sĩ</td><td style='border:1px solid #e6ebf2;'>" . htmlspecialchars($oldDoctor, ENT_QUOTES, 'UTF-8') . "</td><td style='border:1px solid #e6ebf2;'>" . htmlspecialchars($newDoctor, ENT_QUOTES, 'UTF-8') . "</td></tr>
            </table>
            "
        );

        $results['patient'] = sendTransactionalMail(
            $conn,
            'appointment_rescheduled_patient',
            $eventKey,
            (string)$newContext['emailBenhNhan'],
            $subject,
            $html
        );
    }

    if (!empty($newContext['emailBacSi'])) {
        $subject = 'Thông báo cập nhật lịch khám bác sĩ #' . $maLichKham;
        $html = buildEmailLayout(
            'Cập nhật lịch khám',
            "
            <h2 style='margin:0 0 12px;'>Lịch khám đã được cập nhật</h2>
            <p>Bác sĩ <strong>" . htmlspecialchars((string)$newContext['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lịch khám #{$maLichKham} vừa được điều chỉnh.</p>
            <ul>
                <li>Bệnh nhân: <strong>" . htmlspecialchars((string)$newContext['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngày cũ: <strong>{$oldDate}</strong> - {$oldSlot}</li>
                <li>Ngày mới: <strong>{$newDate}</strong> - {$newSlot}</li>
            </ul>
            "
        );

        $results['doctor'] = sendTransactionalMail(
            $conn,
            'appointment_rescheduled_doctor',
            $eventKey,
            (string)$newContext['emailBacSi'],
            $subject,
            $html
        );
    }

    return ['success' => true, 'results' => $results];
}

function sendContactReceivedEmail(
    mysqli $conn,
    int $maLienHe,
    string $email,
    string $hoTen,
    string $chuDe
): array {
    $subject = 'Đã tiếp nhận liên hệ #' . $maLienHe;
    $html = buildEmailLayout(
        'Tiếp nhận liên hệ',
        "
        <h2 style='margin:0 0 12px;'>Hệ thống đã tiếp nhận yêu cầu của bạn</h2>
        <p>Xin chào <strong>" . htmlspecialchars($hoTen, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Chúng tôi đã nhận được liên hệ của bạn và sẽ phản hồi sớm nhất.</p>
        <ul>
            <li>Mã liên hệ: <strong>#{$maLienHe}</strong></li>
            <li>Chủ đề: <strong>" . htmlspecialchars($chuDe, ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Thời gian tiếp nhận: <strong>" . date('d/m/Y H:i') . "</strong></li>
        </ul>
        "
    );

    $eventKey = $maLienHe . ':contact:received';
    return sendTransactionalMail($conn, 'contact_received', $eventKey, $email, $subject, $html);
}

function sendContactProcessedEmail(
    mysqli $conn,
    int $maLienHe,
    ?string $responseMessage = null,
    bool $useStoredNote = true
): array {
    $stmt = $conn->prepare(
        "SELECT hoTen, email, chuDe, ghiChu, thoiGianXuLy FROM lienhe WHERE maLienHe = ? LIMIT 1"
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'prepare_failed'];
    }

    $stmt->bind_param('i', $maLienHe);
    $stmt->execute();
    $contact = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contact || empty($contact['email'])) {
        return ['success' => false, 'message' => 'contact_not_found'];
    }

    $responseMessage = trim((string)$responseMessage);
    $finalResponse = $responseMessage;
    if ($finalResponse === '' && $useStoredNote) {
        $finalResponse = trim((string)($contact['ghiChu'] ?? ''));
    }
    if ($finalResponse === '') {
        $finalResponse = 'Đã tiếp nhận và xử lý';
    }

    $subject = 'Liên hệ #' . $maLienHe . ' đã được xử lý';
    $html = buildEmailLayout(
        'Liên hệ đã xử lý',
        "
        <h2 style='margin:0 0 12px;'>Yêu cầu liên hệ đã được xử lý</h2>
        <p>Xin chào <strong>" . htmlspecialchars((string)$contact['hoTen'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Yêu cầu liên hệ của bạn đã được bộ phận hỗ trợ xử lý.</p>
        <ul>
            <li>Mã liên hệ: <strong>#{$maLienHe}</strong></li>
            <li>Chủ đề: <strong>" . htmlspecialchars((string)$contact['chuDe'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Thời gian xử lý: <strong>" . formatVNDateTime((string)$contact['thoiGianXuLy']) . "</strong></li>
            <li>Phản hồi: <strong>" . htmlspecialchars($finalResponse, ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        "
    );

    $eventKey = $maLienHe . ':contact:processed:' . (string)$contact['thoiGianXuLy'];
    return sendTransactionalMail($conn, 'contact_processed', $eventKey, (string)$contact['email'], $subject, $html);
}

function getInvoiceMailContext(mysqli $conn, int $maHoaDon): ?array {
    $sql = "
        SELECT
            hd.maHoaDon,
            hd.maLichKham,
            hd.soTien,
            hd.trangThai,
            hd.phuongThuc,
            hd.vnp_TransactionNo,
            hd.ngayTao,
            lk.ngayKham,
            bn.tenBenhNhan,
            nd_bn.email AS emailBenhNhan,
            bs.tenBacSi,
            gk.tenGoi
        FROM hoadon hd
        LEFT JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        LEFT JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd_bn ON bn.nguoiDungId = nd_bn.id
        LEFT JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
        WHERE hd.maHoaDon = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $maHoaDon);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ctx ?: null;
}

function sendPaymentStatusEmail(mysqli $conn, int $maHoaDon, string $status, string $reason = ''): array {
    $ctx = getInvoiceMailContext($conn, $maHoaDon);
    if (!$ctx || empty($ctx['emailBenhNhan'])) {
        return ['success' => false, 'message' => 'invoice_or_email_missing'];
    }

    $isSuccess = $status === 'Đã thanh toán';
    $eventCode = $isSuccess ? 'payment_success' : 'payment_failed';

    $subject = $isSuccess
        ? 'Thanh toán thành công hóa đơn #' . $maHoaDon
        : 'Thanh toán thất bại hóa đơn #' . $maHoaDon;

    $title = $isSuccess ? 'Thanh toán thành công' : 'Thanh toán chưa thành công';

    $reasonBlock = '';
    if (!$isSuccess && trim($reason) !== '') {
        $reasonBlock = '<p><strong>Lý do:</strong> ' . htmlspecialchars(trim($reason), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $html = buildEmailLayout(
        $title,
        "
        <h2 style='margin:0 0 12px;'>{$title}</h2>
        <p>Xin chào <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <ul>
            <li>Mã hóa đơn: <strong>#{$maHoaDon}</strong></li>
            <li>Mã lịch khám: <strong>#" . (int)($ctx['maLichKham'] ?? 0) . "</strong></li>
            <li>Số tiền: <strong>" . formatVNCurrency((float)($ctx['soTien'] ?? 0)) . "</strong></li>
            <li>Phương thức: <strong>" . htmlspecialchars((string)($ctx['phuongThuc'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Trạng thái: <strong>" . htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        {$reasonBlock}
        "
    );

    $eventKey = $maHoaDon . ':payment:' . ($isSuccess ? 'success' : 'failed') . ':' . (string)($ctx['vnp_TransactionNo'] ?? 'none') . ':' . (string)$status;

    return sendTransactionalMail(
        $conn,
        $eventCode,
        $eventKey,
        (string)$ctx['emailBenhNhan'],
        $subject,
        $html
    );
}

function sendMedicalRecordCompletedEmail(mysqli $conn, string $maHoSo): array {
    $sql = "
        SELECT
            hs.maHoSo,
            hs.maLichKham,
            hs.chanDoan,
            hs.dieuTri,
            hs.ngayHoanThanh,
            bn.tenBenhNhan,
            nd_bn.email AS emailBenhNhan,
            bs.tenBacSi,
            lk.ngayKham,
            (
                SELECT dt.loiDanBacSi
                FROM donthuoc dt
                WHERE dt.maLichKham = hs.maLichKham
                ORDER BY dt.ngayKeDon DESC, dt.maDonThuoc DESC
                LIMIT 1
            ) AS loiDanBacSi,
            (
                SELECT COUNT(*)
                FROM donthuoc dt
                WHERE dt.maLichKham = hs.maLichKham
            ) AS prescriptionCount
        FROM hosobenhan hs
        LEFT JOIN benhnhan bn ON hs.maBenhNhan = bn.maBenhNhan
        LEFT JOIN nguoidung nd_bn ON bn.nguoiDungId = nd_bn.id
        LEFT JOIN bacsi bs ON hs.maBacSi = bs.maBacSi
        LEFT JOIN lichkham lk ON hs.maLichKham = lk.maLichKham
        WHERE hs.maHoSo = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'prepare_failed'];
    }

    $stmt->bind_param('s', $maHoSo);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ctx || empty($ctx['emailBenhNhan'])) {
        return ['success' => false, 'message' => 'record_or_email_missing'];
    }

    $hasPrescription = ((int)($ctx['prescriptionCount'] ?? 0)) > 0;
    $diagnosisBlock = trim((string)($ctx['chanDoan'] ?? '')) !== ''
        ? '<p><strong>Chẩn đoán:</strong> ' . nl2br(htmlspecialchars((string)$ctx['chanDoan'], ENT_QUOTES, 'UTF-8')) . '</p>'
        : '';
    $treatmentBlock = trim((string)($ctx['dieuTri'] ?? '')) !== ''
        ? '<p><strong>Hướng điều trị:</strong> ' . nl2br(htmlspecialchars((string)$ctx['dieuTri'], ENT_QUOTES, 'UTF-8')) . '</p>'
        : '';
    $adviceBlock = trim((string)($ctx['loiDanBacSi'] ?? '')) !== ''
        ? '<p><strong>Lời dặn bác sĩ:</strong> ' . nl2br(htmlspecialchars((string)$ctx['loiDanBacSi'], ENT_QUOTES, 'UTF-8')) . '</p>'
        : '';

    $subject = 'Hồ sơ khám bệnh đã cập nhật #' . $maHoSo;
    $extra = $hasPrescription
        ? '<p>Đơn thuốc đã được tạo cho lịch khám này. Vui lòng đăng nhập để xem chi tiết.</p>'
        : '<p>Hiện chưa có đơn thuốc đính kèm cho lịch khám này.</p>';

    $html = buildEmailLayout(
        'Hồ sơ khám bệnh đã sẵn sàng',
        "
        <h2 style='margin:0 0 12px;'>Hồ sơ khám bệnh đã được hoàn tất</h2>
        <p>Xin chào <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <ul>
            <li>Mã hồ sơ: <strong>" . htmlspecialchars($maHoSo, ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Mã lịch khám: <strong>#" . (int)($ctx['maLichKham'] ?? 0) . "</strong></li>
            <li>Bác sĩ: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Ngày khám: <strong>" . formatVNDate((string)$ctx['ngayKham']) . "</strong></li>
            <li>Ngày cập nhật hồ sơ: <strong>" . formatVNDateTime((string)$ctx['ngayHoanThanh']) . "</strong></li>
        </ul>
        {$diagnosisBlock}
        {$treatmentBlock}
        {$adviceBlock}
        {$extra}
        "
    );

    $eventKey = $maHoSo . ':record_ready';

    return sendTransactionalMail(
        $conn,
        'medical_record_ready',
        $eventKey,
        (string)$ctx['emailBenhNhan'],
        $subject,
        $html
    );
}

function roleLabelForAccountMail(string $role): string {
    $role = strtolower(trim($role));
    if ($role === 'benhnhan') {
        return 'Bệnh nhân';
    }
    if ($role === 'bacsi') {
        return 'Bác sĩ';
    }
    if ($role === 'quantri') {
        return 'Quản trị viên';
    }
    return 'Người dùng';
}

function getAccountMailContext(mysqli $conn, int $userId): ?array {
    $sql = "
        SELECT
            nd.id,
            nd.tenDangNhap,
            nd.email,
            nd.vaiTro,
            CASE
                WHEN nd.vaiTro = 'benhnhan' THEN bn.tenBenhNhan
                WHEN nd.vaiTro = 'bacsi' THEN bs.tenBacSi
                ELSE qtv.maQuanTriVien
            END AS hoTen
        FROM nguoidung nd
        LEFT JOIN benhnhan bn ON bn.nguoiDungId = nd.id
        LEFT JOIN bacsi bs ON bs.nguoiDungId = nd.id
        LEFT JOIN quantrivien qtv ON qtv.nguoiDungId = nd.id
        WHERE nd.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ctx ?: null;
}

function sendAccountStatusChangedEmail(mysqli $conn, int $userId, string $newStatus, string $reason = ''): array {
    $ctx = getAccountMailContext($conn, $userId);
    if (!$ctx) {
        return ['success' => false, 'message' => 'account_not_found'];
    }

    $recipientEmail = trim((string)($ctx['email'] ?? ''));
    if ($recipientEmail === '') {
        return ['success' => false, 'message' => 'email_missing'];
    }

    $isLocked = trim($newStatus) === 'Khóa';
    $eventCode = $isLocked ? 'account_locked' : 'account_unlocked';
    $subject = $isLocked
        ? 'Thông báo khóa tài khoản'
        : 'Thông báo mở khóa tài khoản';

    $statusLabel = $isLocked ? 'Đã khóa' : 'Hoạt động';
    $defaultReason = $isLocked
        ? 'Vi phạm chính sách sử dụng của hệ thống.'
        : 'Tài khoản đã được mở khóa và có thể sử dụng lại.';
    $reasonText = trim($reason) !== '' ? trim($reason) : $defaultReason;

    $html = buildEmailLayout(
        $isLocked ? 'Tài khoản đã bị khóa' : 'Tài khoản đã được mở khóa',
        "
        <h2 style='margin:0 0 12px;'>" . ($isLocked ? 'Tài khoản của bạn đã bị khóa' : 'Tài khoản của bạn đã được mở khóa') . "</h2>
        <p>Xin chào <strong>" . htmlspecialchars((string)($ctx['hoTen'] ?? $ctx['tenDangNhap']), ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Hệ thống " . htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8') . " vừa cập nhật trạng thái tài khoản của bạn.</p>
        <ul>
            <li>Tên đăng nhập: <strong>" . htmlspecialchars((string)$ctx['tenDangNhap'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Vai trò: <strong>" . htmlspecialchars(roleLabelForAccountMail((string)$ctx['vaiTro']), ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Trạng thái mới: <strong>{$statusLabel}</strong></li>
            <li>Lý do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        <p>Nếu cần hỗ trợ, vui lòng liên hệ bộ phận quản trị hệ thống.</p>
        "
    );

    $statusKey = $isLocked ? 'locked' : 'active';
    $eventKey = $userId . ':account_status:' . $statusKey . ':' . date('YmdHis');

    return sendTransactionalMail(
        $conn,
        $eventCode,
        $eventKey,
        $recipientEmail,
        $subject,
        $html
    );
}

function extractAppointmentSnapshotFromDbRow(array $row): array {
    $slotDisplay = 'N/A';
    if (!empty($row['gioBatDau']) && !empty($row['gioKetThuc'])) {
        $slotDisplay = $row['gioBatDau'] . ' - ' . $row['gioKetThuc'];
    } elseif (!empty($row['tenCa'])) {
        $slotDisplay = $row['tenCa'];
    }

    return [
        'maBacSi' => $row['maBacSi'] ?? null,
        'tenBacSi' => $row['tenBacSi'] ?? null,
        'ngayKham' => $row['ngayKham'] ?? null,
        'maCa' => $row['maCa'] ?? null,
        'maSuat' => $row['maSuat'] ?? null,
        'maGoi' => $row['maGoi'] ?? null,
        'slotDisplay' => $slotDisplay,
    ];
}

function sendSchedulePresetChangedEmails(mysqli $conn, int $oldDuration, int $newDuration, string $effectiveFrom): array {
    $stmt = $conn->prepare(
        "SELECT DISTINCT nd.email, COALESCE(bs.tenBacSi, nd.tenDangNhap) AS tenBacSi
         FROM bacsi bs
         JOIN nguoidung nd ON bs.nguoiDungId = nd.id
         WHERE nd.isDeleted = 0
           AND nd.email IS NOT NULL
           AND nd.email <> ''"
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'cannot_prepare'];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $sent = 0;
    $failed = 0;
    $details = [];

    $subject = 'Cập nhật lịch biểu khám từ ' . formatVNDate($effectiveFrom);
    $eventKey = 'schedule_preset:' . $effectiveFrom . ':' . $newDuration;

    while ($row = $result->fetch_assoc()) {
        $recipientEmail = trim((string)($row['email'] ?? ''));
        if ($recipientEmail === '') {
            continue;
        }

        $doctorName = (string)($row['tenBacSi'] ?? 'Bác sĩ');
        $html = buildEmailLayout(
            'Cập nhật lịch biểu khám',
            "
            <h2 style='margin:0 0 12px;'>Lịch biểu khám đã được cập nhật</h2>
            <p>Xin chào <strong>" . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Quản trị viên vừa thay đổi preset lịch biểu khám của hệ thống.</p>
            <ul>
                <li>Preset cũ: <strong>{$oldDuration} phút</strong></li>
                <li>Preset mới: <strong>{$newDuration} phút</strong></li>
                <li>Hiệu lực từ: <strong>" . htmlspecialchars(formatVNDate($effectiveFrom), ENT_QUOTES, 'UTF-8') . "</strong></li>
            </ul>
            <p>Các lịch đã tạo trước thời điểm hiệu lực vẫn giữ nguyên cấu hình cũ.</p>
            "
        );

        $mailResult = sendTransactionalMail(
            $conn,
            'schedule_preset_changed_doctor',
            $eventKey,
            $recipientEmail,
            $subject,
            $html
        );

        if (!empty($mailResult['success'])) {
            $sent++;
        } else {
            $failed++;
        }

        $details[] = [
            'email' => $recipientEmail,
            'success' => !empty($mailResult['success'])
        ];
    }

    $stmt->close();

    return [
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'details' => $details
    ];
}

function sendSchedulePresetChangedNotifications(mysqli $conn, int $oldDuration, int $newDuration, string $effectiveFrom): array {
    $stmt = $conn->prepare(
        "SELECT DISTINCT bs.maBacSi, COALESCE(bs.tenBacSi, nd.tenDangNhap) AS tenBacSi
         FROM bacsi bs
         JOIN nguoidung nd ON bs.nguoiDungId = nd.id
         WHERE nd.isDeleted = 0"
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'cannot_prepare'];
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $title = 'Lịch biểu khám đã được cập nhật';
    $message = sprintf(
        'Preset lịch khám đã thay đổi từ %d phút sang %d phút. Hiệu lực từ %s. Các lịch trước thời điểm này vẫn giữ nguyên cấu hình cũ.',
        $oldDuration,
        $newDuration,
        formatVNDate($effectiveFrom)
    );

    $insertStmt = $conn->prepare(
        "INSERT INTO thongbaolichkham (maBacSi, maLichKham, loai, tieuDe, noiDung)
         VALUES (?, NULL, 'Cập nhật lịch biểu', ?, ?)"
    );
    if (!$insertStmt) {
        $stmt->close();
        return ['success' => false, 'message' => 'cannot_prepare_insert'];
    }

    $sent = 0;
    $failed = 0;
    $details = [];

    while ($row = $result->fetch_assoc()) {
        $maBacSi = trim((string)($row['maBacSi'] ?? ''));
        if ($maBacSi === '') {
            continue;
        }

        $insertStmt->bind_param('sss', $maBacSi, $title, $message);
        $ok = $insertStmt->execute();
        if ($ok) {
            $sent++;
        } else {
            $failed++;
        }

        $details[] = [
            'maBacSi' => $maBacSi,
            'success' => $ok
        ];
    }

    $insertStmt->close();
    $stmt->close();

    return [
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'details' => $details
    ];
}
?>
