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
        $subject = 'Xac nhan dat lich kham #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Xac nhan dat lich',
            "
            <h2 style='margin:0 0 12px;'>Dat lich thanh cong</h2>
            <p>Xin chao <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Ban da dat lich kham thanh cong tai <strong>" . htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8') . "</strong>.</p>
            <ul>
                <li>Ma lich kham: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bac si: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngay kham: <strong>{$dateLabel}</strong></li>
                <li>Khung gio: <strong>{$timeLabel}</strong></li>
                <li>Goi kham: <strong>" . htmlspecialchars((string)$package, ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Chi phi du kien: <strong>{$price}</strong></li>
            </ul>
            <p>Neu can thay doi, vui long quan ly lich kham trong tai khoan ca nhan.</p>
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
        $subject = 'Thong bao co lich kham moi #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Lich kham moi',
            "
            <h2 style='margin:0 0 12px;'>Ban co lich kham moi</h2>
            <p>Bac si <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>He thong vua ghi nhan lich kham moi.</p>
            <ul>
                <li>Ma lich kham: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Benh nhan: <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngay kham: <strong>{$dateLabel}</strong></li>
                <li>Khung gio: <strong>{$timeLabel}</strong></li>
                <li>Ghi chu: <strong>" . htmlspecialchars((string)($ctx['ghiChu'] ?? 'Khong co'), ENT_QUOTES, 'UTF-8') . "</strong></li>
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
        return 'Benh nhan';
    }
    if ($actor === 'bacsi') {
        return 'Bac si';
    }
    if ($actor === 'quantri') {
        return 'Quan tri vien';
    }
    if ($actor === 'hethong') {
        return 'He thong';
    }
    return 'Don vi y te';
}

function sendAppointmentCancelledEmails(mysqli $conn, int $maLichKham, string $cancelledBy, string $reason = ''): array {
    $ctx = getAppointmentMailContext($conn, $maLichKham);
    if (!$ctx) {
        return ['success' => false, 'message' => 'appointment_not_found'];
    }

    $actorLabel = cancellationActorLabel($cancelledBy);
    $reasonText = trim($reason) !== '' ? trim($reason) : 'Khong co ly do cu the';
    $dateLabel = formatVNDate($ctx['ngayKham']);
    $timeLabel = appointmentTimeRange($ctx);
    $eventKey = $ctx['maLichKham'] . ':cancel:' . $ctx['ngayKham'] . ':' . md5($cancelledBy . ':' . $reasonText);

    $results = [];

    if (!empty($ctx['emailBenhNhan'])) {
        $subject = 'Thong bao huy lich kham #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Huy lich kham',
            "
            <h2 style='margin:0 0 12px;'>Lich kham da duoc huy</h2>
            <p>Xin chao <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lich kham cua ban da duoc huy boi <strong>{$actorLabel}</strong>.</p>
            <ul>
                <li>Ma lich kham: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Bac si: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngay kham: <strong>{$dateLabel}</strong></li>
                <li>Khung gio: <strong>{$timeLabel}</strong></li>
                <li>Ly do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
            </ul>
            <p>Vui long dat lich moi neu ban van co nhu cau kham.</p>
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
        $subject = 'Thong bao huy lich kham benh nhan #' . $ctx['maLichKham'];
        $html = buildEmailLayout(
            'Huy lich kham',
            "
            <h2 style='margin:0 0 12px;'>Lich kham da bi huy</h2>
            <p>Bac si <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lich kham benh nhan da duoc huy boi <strong>{$actorLabel}</strong>.</p>
            <ul>
                <li>Ma lich kham: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
                <li>Benh nhan: <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngay kham: <strong>{$dateLabel}</strong></li>
                <li>Khung gio: <strong>{$timeLabel}</strong></li>
                <li>Ly do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
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

    $titleLead = $normalizedType === '2h' ? 'Nhac lich kham truoc 2 gio' : 'Nhac lich kham truoc 24 gio';
    $subject = $titleLead . ' #' . $ctx['maLichKham'];

    $html = buildEmailLayout(
        'Nhac lich kham',
        "
        <h2 style='margin:0 0 12px;'>{$titleLead}</h2>
        <p>Xin chao <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Ban co lich kham sap toi, vui long den dung gio.</p>
        <ul>
            <li>Ma lich kham: <strong>#" . (int)$ctx['maLichKham'] . "</strong></li>
            <li>Bac si: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Ngay kham: <strong>{$dateLabel}</strong></li>
            <li>Khung gio: <strong>{$timeLabel}</strong></li>
            <li>Goi kham: <strong>" . htmlspecialchars((string)($ctx['tenGoi'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . "</strong></li>
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

    $oldSlot = (string)($oldContext['slotDisplay'] ?? 'N/A');
    $newSlot = appointmentTimeRange($newContext);

    $eventKey = $maLichKham . ':reschedule:' . md5(json_encode([$oldContext, $newContext], JSON_UNESCAPED_UNICODE));
    $actorLabel = cancellationActorLabel($updatedBy);

    $results = [];

    if (!empty($newContext['emailBenhNhan'])) {
        $subject = 'Thong bao cap nhat lich kham #' . $maLichKham;
        $html = buildEmailLayout(
            'Cap nhat lich kham',
            "
            <h2 style='margin:0 0 12px;'>Lich kham da duoc cap nhat</h2>
            <p>Xin chao <strong>" . htmlspecialchars((string)$newContext['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lich kham cua ban da duoc dieu chinh boi <strong>{$actorLabel}</strong>.</p>
            <table width='100%' cellpadding='6' cellspacing='0' style='border-collapse:collapse;border:1px solid #e6ebf2;'>
                <tr><td style='border:1px solid #e6ebf2;'><strong>Noi dung</strong></td><td style='border:1px solid #e6ebf2;'><strong>Truoc</strong></td><td style='border:1px solid #e6ebf2;'><strong>Sau</strong></td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Ngay kham</td><td style='border:1px solid #e6ebf2;'>{$oldDate}</td><td style='border:1px solid #e6ebf2;'>{$newDate}</td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Khung gio</td><td style='border:1px solid #e6ebf2;'>{$oldSlot}</td><td style='border:1px solid #e6ebf2;'>{$newSlot}</td></tr>
                <tr><td style='border:1px solid #e6ebf2;'>Bac si</td><td style='border:1px solid #e6ebf2;'>" . htmlspecialchars($oldDoctor, ENT_QUOTES, 'UTF-8') . "</td><td style='border:1px solid #e6ebf2;'>" . htmlspecialchars($newDoctor, ENT_QUOTES, 'UTF-8') . "</td></tr>
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
        $subject = 'Thong bao cap nhat lich kham bac si #' . $maLichKham;
        $html = buildEmailLayout(
            'Cap nhat lich kham',
            "
            <h2 style='margin:0 0 12px;'>Lich kham da duoc cap nhat</h2>
            <p>Bac si <strong>" . htmlspecialchars((string)$newContext['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Lich kham #{$maLichKham} vua duoc dieu chinh.</p>
            <ul>
                <li>Benh nhan: <strong>" . htmlspecialchars((string)$newContext['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong></li>
                <li>Ngay cu: <strong>{$oldDate}</strong> - {$oldSlot}</li>
                <li>Ngay moi: <strong>{$newDate}</strong> - {$newSlot}</li>
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
    $subject = 'Da tiep nhan lien he #' . $maLienHe;
    $html = buildEmailLayout(
        'Tiep nhan lien he',
        "
        <h2 style='margin:0 0 12px;'>He thong da tiep nhan yeu cau cua ban</h2>
        <p>Xin chao <strong>" . htmlspecialchars($hoTen, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Chung toi da nhan duoc lien he cua ban va se phan hoi som nhat.</p>
        <ul>
            <li>Ma lien he: <strong>#{$maLienHe}</strong></li>
            <li>Chu de: <strong>" . htmlspecialchars($chuDe, ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Thoi gian tiep nhan: <strong>" . date('d/m/Y H:i') . "</strong></li>
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
        $finalResponse = 'Da tiep nhan va xu ly';
    }

    $subject = 'Lien he #' . $maLienHe . ' da duoc xu ly';
    $html = buildEmailLayout(
        'Lien he da xu ly',
        "
        <h2 style='margin:0 0 12px;'>Yeu cau lien he da duoc xu ly</h2>
        <p>Xin chao <strong>" . htmlspecialchars((string)$contact['hoTen'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Yeu cau lien he cua ban da duoc bo phan ho tro xu ly.</p>
        <ul>
            <li>Ma lien he: <strong>#{$maLienHe}</strong></li>
            <li>Chu de: <strong>" . htmlspecialchars((string)$contact['chuDe'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Thoi gian xu ly: <strong>" . formatVNDateTime((string)$contact['thoiGianXuLy']) . "</strong></li>
            <li>Phan hoi: <strong>" . htmlspecialchars($finalResponse, ENT_QUOTES, 'UTF-8') . "</strong></li>
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
        ? 'Thanh toan thanh cong hoa don #' . $maHoaDon
        : 'Thanh toan that bai hoa don #' . $maHoaDon;

    $title = $isSuccess ? 'Thanh toan thanh cong' : 'Thanh toan chua thanh cong';

    $reasonBlock = '';
    if (!$isSuccess && trim($reason) !== '') {
        $reasonBlock = '<p><strong>Ly do:</strong> ' . htmlspecialchars(trim($reason), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $html = buildEmailLayout(
        $title,
        "
        <h2 style='margin:0 0 12px;'>{$title}</h2>
        <p>Xin chao <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <ul>
            <li>Ma hoa don: <strong>#{$maHoaDon}</strong></li>
            <li>Ma lich kham: <strong>#" . (int)($ctx['maLichKham'] ?? 0) . "</strong></li>
            <li>So tien: <strong>" . formatVNCurrency((float)($ctx['soTien'] ?? 0)) . "</strong></li>
            <li>Phuong thuc: <strong>" . htmlspecialchars((string)($ctx['phuongThuc'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Trang thai: <strong>" . htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') . "</strong></li>
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

    $subject = 'Ho so kham benh da cap nhat #' . $maHoSo;
    $extra = $hasPrescription
        ? '<p>Don thuoc da duoc tao cho lich kham nay. Vui long dang nhap de xem chi tiet.</p>'
        : '<p>Hien chua co don thuoc dinh kem cho lich kham nay.</p>';

    $html = buildEmailLayout(
        'Ho so kham benh da san sang',
        "
        <h2 style='margin:0 0 12px;'>Ho so kham benh da duoc hoan tat</h2>
        <p>Xin chao <strong>" . htmlspecialchars((string)$ctx['tenBenhNhan'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <ul>
            <li>Ma ho so: <strong>" . htmlspecialchars($maHoSo, ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Ma lich kham: <strong>#" . (int)($ctx['maLichKham'] ?? 0) . "</strong></li>
            <li>Bac si: <strong>" . htmlspecialchars((string)$ctx['tenBacSi'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Ngay kham: <strong>" . formatVNDate((string)$ctx['ngayKham']) . "</strong></li>
            <li>Ngay cap nhat ho so: <strong>" . formatVNDateTime((string)$ctx['ngayHoanThanh']) . "</strong></li>
        </ul>
        {$extra}
        "
    );

    $eventKey = $maHoSo . ':record_ready:' . (string)$ctx['ngayHoanThanh'];

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
        return 'Benh nhan';
    }
    if ($role === 'bacsi') {
        return 'Bac si';
    }
    if ($role === 'quantri') {
        return 'Quan tri vien';
    }
    return 'Nguoi dung';
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
        ? 'Thong bao khoa tai khoan'
        : 'Thong bao mo khoa tai khoan';

    $statusLabel = $isLocked ? 'Da khoa' : 'Hoat dong';
    $defaultReason = $isLocked
        ? 'Vi pham chinh sach su dung cua he thong.'
        : 'Tai khoan da duoc mo khoa va co the su dung lai.';
    $reasonText = trim($reason) !== '' ? trim($reason) : $defaultReason;

    $html = buildEmailLayout(
        $isLocked ? 'Tai khoan da bi khoa' : 'Tai khoan da duoc mo khoa',
        "
        <h2 style='margin:0 0 12px;'>" . ($isLocked ? 'Tai khoan cua ban da bi khoa' : 'Tai khoan cua ban da duoc mo khoa') . "</h2>
        <p>Xin chao <strong>" . htmlspecialchars((string)($ctx['hoTen'] ?? $ctx['tenDangNhap']), ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>He thong " . htmlspecialchars(mailSiteName(), ENT_QUOTES, 'UTF-8') . " vua cap nhat trang thai tai khoan cua ban.</p>
        <ul>
            <li>Ten dang nhap: <strong>" . htmlspecialchars((string)$ctx['tenDangNhap'], ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Vai tro: <strong>" . htmlspecialchars(roleLabelForAccountMail((string)$ctx['vaiTro']), ENT_QUOTES, 'UTF-8') . "</strong></li>
            <li>Trang thai moi: <strong>{$statusLabel}</strong></li>
            <li>Ly do: <strong>" . htmlspecialchars($reasonText, ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        <p>Neu can ho tro, vui long lien he bo phan quan tri he thong.</p>
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
?>
