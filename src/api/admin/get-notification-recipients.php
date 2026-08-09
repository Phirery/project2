<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/admin-broadcast.php';

require_role('quantri');

try {
    $rolesRaw = trim((string)($_GET['roles'] ?? ''));
    $roles = $rolesRaw === '' ? ['patient', 'doctor'] : array_map('trim', explode(',', $rolesRaw));
    $roles = broadcastNormalizeRoles($roles);

    if (empty($roles)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'total' => 0,
            'roles' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 20);
    if ($limit < 1) {
        $limit = 20;
    }
    if ($limit > 100) {
        $limit = 100;
    }

    $data = broadcastFetchRecipientsForRoles($conn, $roles, $search, $limit);

    $total = 0;
    foreach ($roles as $role) {
        $total += broadcastCountRecipientsForRole($conn, $role, $search);
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total,
        'roles' => $roles
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
