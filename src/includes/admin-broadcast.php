<?php
require_once __DIR__ . '/mail-events.php';

function broadcastBindParamsDynamic(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '' || empty($params)) {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function broadcastRoleConfig(string $role): ?array {
    $role = strtolower(trim($role));

    if ($role === 'patient') {
        return [
            'role' => 'patient',
            'role_label' => 'Bệnh nhân',
            'table' => 'benhnhan',
            'alias' => 'bn',
            'id_column' => 'maBenhNhan',
            'name_column' => 'tenBenhNhan',
        ];
    }

    if ($role === 'doctor') {
        return [
            'role' => 'doctor',
            'role_label' => 'Bác sĩ',
            'table' => 'bacsi',
            'alias' => 'bs',
            'id_column' => 'maBacSi',
            'name_column' => 'tenBacSi',
        ];
    }

    return null;
}

function broadcastNormalizeRoles(array $roles): array {
    $allowed = [];

    foreach ($roles as $role) {
        $normalized = strtolower(trim((string)$role));
        if (in_array($normalized, ['patient', 'doctor'], true) && !in_array($normalized, $allowed, true)) {
            $allowed[] = $normalized;
        }
    }

    return $allowed;
}

function broadcastNormalizeChannels(array $channels): array {
    $normalized = [
        'web' => false,
        'mail' => false,
    ];

    foreach ($channels as $channel) {
        $value = strtolower(trim((string)$channel));
        if (array_key_exists($value, $normalized)) {
            $normalized[$value] = true;
        }
    }

    return $normalized;
}

function ensureDoctorBroadcastNotificationType(mysqli $conn): bool {
    static $done = false;
    if ($done) {
        return true;
    }

    $stmt = $conn->prepare(
        "SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'thongbaolichkham'
           AND COLUMN_NAME = 'loai'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $columnType = (string)($row['COLUMN_TYPE'] ?? '');
    if (stripos($columnType, "'Hệ thống'") === false) {
        $alterSql = "ALTER TABLE thongbaolichkham
            MODIFY loai ENUM('Đặt lịch', 'Hủy lịch', 'Hệ thống') NOT NULL DEFAULT 'Đặt lịch'";
        if (!$conn->query($alterSql)) {
            return false;
        }
    }

    $done = true;
    return true;
}

function broadcastCountRecipientsForRole(mysqli $conn, string $role, string $search = ''): int {
    $cfg = broadcastRoleConfig($role);
    if (!$cfg) {
        return 0;
    }

    $search = trim($search);
    $sql = "
        SELECT COUNT(*) AS total
        FROM {$cfg['table']} {$cfg['alias']}
        JOIN nguoidung nd ON {$cfg['alias']}.nguoiDungId = nd.id
        WHERE nd.isDeleted = 0
    ";
    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= " AND (
            {$cfg['alias']}.{$cfg['id_column']} LIKE ?
            OR {$cfg['alias']}.{$cfg['name_column']} LIKE ?
            OR nd.tenDangNhap LIKE ?
            OR nd.email LIKE ?
        )";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
        $types = 'ssss';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        broadcastBindParamsDynamic($stmt, $types, $params);
    }

    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return $total;
}

function broadcastFetchRecipientsForRole(mysqli $conn, string $role, string $search = '', int $limit = 20): array {
    $cfg = broadcastRoleConfig($role);
    if (!$cfg) {
        return [];
    }

    $limit = max(1, min(5000, $limit));
    $search = trim($search);
    $extraSelect = $cfg['role'] === 'doctor'
        ? "COALESCE(ck.tenChuyenKhoa, '') AS extraLabel"
        : "'' AS extraLabel";
    $extraJoin = $cfg['role'] === 'doctor'
        ? "LEFT JOIN chuyenkhoa ck ON {$cfg['alias']}.maChuyenKhoa = ck.maChuyenKhoa"
        : "";

    $sql = "
        SELECT
            {$cfg['alias']}.{$cfg['id_column']} AS recipientId,
            COALESCE(NULLIF({$cfg['alias']}.{$cfg['name_column']}, ''), nd.tenDangNhap) AS recipientName,
            nd.email AS recipientEmail,
            '{$cfg['role']}' AS recipientRole,
            '{$cfg['role_label']}' AS roleLabel,
            {$extraSelect}
        FROM {$cfg['table']} {$cfg['alias']}
        JOIN nguoidung nd ON {$cfg['alias']}.nguoiDungId = nd.id
        {$extraJoin}
        WHERE nd.isDeleted = 0
    ";
    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= " AND (
            {$cfg['alias']}.{$cfg['id_column']} LIKE ?
            OR {$cfg['alias']}.{$cfg['name_column']} LIKE ?
            OR nd.tenDangNhap LIKE ?
            OR nd.email LIKE ?
        )";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
        $types = 'ssss';
    }

    $sql .= " ORDER BY recipientName ASC, recipientId ASC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    broadcastBindParamsDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

function broadcastFetchRecipients(array $groups): array {
    $items = [];
    foreach ($groups as $group) {
        foreach ($group as $item) {
            $items[] = $item;
        }
    }

    usort($items, static function (array $left, array $right): int {
        $nameCompare = strcasecmp((string)($left['recipientName'] ?? ''), (string)($right['recipientName'] ?? ''));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        $roleCompare = strcmp((string)($left['recipientRole'] ?? ''), (string)($right['recipientRole'] ?? ''));
        if ($roleCompare !== 0) {
            return $roleCompare;
        }

        return strcmp((string)($left['recipientId'] ?? ''), (string)($right['recipientId'] ?? ''));
    });

    $deduped = [];
    $seen = [];
    foreach ($items as $item) {
        $email = strtolower(trim((string)($item['recipientEmail'] ?? '')));
        $key = $email !== '' ? $email : (($item['recipientRole'] ?? '') . ':' . ($item['recipientId'] ?? ''));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $item;
    }

    return $deduped;
}

function broadcastFetchRecipientsForRoles(mysqli $conn, array $roles, string $search = '', int $limit = 20): array {
    $roles = broadcastNormalizeRoles($roles);
    if (empty($roles)) {
        return [];
    }

    $perRoleLimit = max(1, min(100, $limit));
    $groups = [];

    foreach ($roles as $role) {
        $groups[] = broadcastFetchRecipientsForRole($conn, $role, $search, $perRoleLimit);
    }

    $items = broadcastFetchRecipients($groups);
    return array_slice($items, 0, $limit);
}

function broadcastFetchSelectedRecipients(mysqli $conn, array $selectedRecipients): array {
    $groups = [
        'patient' => [],
        'doctor' => [],
    ];

    foreach ($selectedRecipients as $recipient) {
        $role = strtolower(trim((string)($recipient['role'] ?? '')));
        $id = trim((string)($recipient['id'] ?? ''));
        if (!in_array($role, ['patient', 'doctor'], true) || $id === '') {
            continue;
        }
        $groups[$role][] = $id;
    }

    $results = [];
    foreach ($groups as $role => $ids) {
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            continue;
        }

        $cfg = broadcastRoleConfig($role);
        if (!$cfg) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $extraSelect = $cfg['role'] === 'doctor'
            ? "COALESCE(ck.tenChuyenKhoa, '') AS extraLabel"
            : "'' AS extraLabel";
        $extraJoin = $cfg['role'] === 'doctor'
            ? "LEFT JOIN chuyenkhoa ck ON {$cfg['alias']}.maChuyenKhoa = ck.maChuyenKhoa"
            : "";

        $sql = "
            SELECT
                {$cfg['alias']}.{$cfg['id_column']} AS recipientId,
                COALESCE(NULLIF({$cfg['alias']}.{$cfg['name_column']}, ''), nd.tenDangNhap) AS recipientName,
                nd.email AS recipientEmail,
                '{$cfg['role']}' AS recipientRole,
                '{$cfg['role_label']}' AS roleLabel,
                {$extraSelect}
            FROM {$cfg['table']} {$cfg['alias']}
            JOIN nguoidung nd ON {$cfg['alias']}.nguoiDungId = nd.id
            {$extraJoin}
            WHERE nd.isDeleted = 0
              AND {$cfg['alias']}.{$cfg['id_column']} IN ({$placeholders})
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            continue;
        }

        $types = str_repeat('s', count($ids));
        broadcastBindParamsDynamic($stmt, $types, $ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }

    return broadcastFetchRecipients([$results]);
}

function broadcastPrepareWebContent(string $content): string {
    return nl2br(htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8'));
}

function broadcastPrepareWebTitle(string $title): string {
    return htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
}

function broadcastInsertWebNotification(mysqli $conn, array $recipient, string $title, string $content): array {
    $role = strtolower(trim((string)($recipient['recipientRole'] ?? '')));
    $recipientId = trim((string)($recipient['recipientId'] ?? ''));
    $safeTitle = broadcastPrepareWebTitle($title);
    $safeContent = broadcastPrepareWebContent($content);

    if ($role === 'doctor') {
        if (!ensureDoctorBroadcastNotificationType($conn)) {
            return ['success' => false, 'reason' => 'schema_update_failed'];
        }

        $stmt = $conn->prepare(
            "INSERT INTO thongbaolichkham (maBacSi, maLichKham, loai, tieuDe, noiDung, thoiGian, daXem)
             VALUES (?, NULL, 'Hệ thống', ?, ?, NOW(), 0)"
        );
        if (!$stmt) {
            return ['success' => false, 'reason' => 'prepare_failed'];
        }

        $stmt->bind_param('sss', $recipientId, $safeTitle, $safeContent);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        return [
            'success' => (bool)$ok,
            'reason' => $ok ? null : ($error ?: 'insert_failed')
        ];
    }

    if ($role === 'patient') {
        $stmt = $conn->prepare(
            "INSERT INTO thongbaobenhnhan (maBenhNhan, loai, tieuDe, noiDung, thoiGian, daXem)
             VALUES (?, 'Hệ thống', ?, ?, NOW(), 0)"
        );
        if (!$stmt) {
            return ['success' => false, 'reason' => 'prepare_failed'];
        }

        $stmt->bind_param('sss', $recipientId, $safeTitle, $safeContent);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        return [
            'success' => (bool)$ok,
            'reason' => $ok ? null : ($error ?: 'insert_failed')
        ];
    }

    return ['success' => false, 'reason' => 'invalid_role'];
}

function broadcastSendMail(mysqli $conn, array $recipient, string $eventKey, string $subject, string $headline, string $content): array {
    $recipientEmail = trim((string)($recipient['recipientEmail'] ?? ''));
    $recipientName = trim((string)($recipient['recipientName'] ?? ''));

    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'reason' => 'invalid_email'];
    }

    return sendAdminBroadcastMail(
        $conn,
        $eventKey,
        $recipientEmail,
        $recipientName,
        $subject,
        $headline,
        $content
    );
}
