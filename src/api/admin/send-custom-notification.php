<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/admin-broadcast.php';

require_role('quantri');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$title = trim((string)($input['title'] ?? ''));
$content = trim((string)($input['content'] ?? ''));
$scope = strtolower(trim((string)($input['scope'] ?? 'all')));
$rawRoles = $input['roles'] ?? [];
if (!is_array($rawRoles)) {
    $rawRoles = [$rawRoles];
}
$roles = broadcastNormalizeRoles($rawRoles);

$rawChannels = $input['channels'] ?? [];
if (!is_array($rawChannels)) {
    $rawChannels = [$rawChannels];
}
$channels = broadcastNormalizeChannels($rawChannels);

$selectedRecipients = is_array($input['recipients'] ?? null) ? $input['recipients'] : [];

try {
    if ($title === '' || $content === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng nhập tiêu đề và nội dung thông báo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
    if ($titleLength > 255) {
        echo json_encode([
            'success' => false,
            'message' => 'Tiêu đề không được vượt quá 255 ký tự.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$channels['web'] && !$channels['mail']) {
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng chọn ít nhất một kênh gửi: trong web hoặc mail.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($roles)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng chọn ít nhất một nhóm người nhận.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($scope === 'selected' && empty($selectedRecipients)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng chọn ít nhất một người nhận.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $recipients = [];
    if ($scope === 'selected') {
        $recipients = broadcastFetchSelectedRecipients($conn, $selectedRecipients);
    } else {
        $recipients = broadcastFetchRecipientsForRoles($conn, $roles, '', 5000);
    }

    if (empty($recipients)) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy người nhận phù hợp.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sentRecipients = [];
    $results = [
        'web' => [
            'sent' => 0,
            'failed' => 0,
        ],
        'mail' => [
            'sent' => 0,
            'failed' => 0,
        ],
    ];

    foreach ($recipients as $recipient) {
        $recipientEmail = trim((string)($recipient['recipientEmail'] ?? ''));
        $recipientName = trim((string)($recipient['recipientName'] ?? ''));
        $recipientRole = strtolower(trim((string)($recipient['recipientRole'] ?? '')));
        $recipientId = trim((string)($recipient['recipientId'] ?? ''));
        $dedupeKey = strtolower($recipientEmail !== '' ? $recipientEmail : ($recipientRole . ':' . $recipientId));

        if ($dedupeKey === '' || isset($sentRecipients[$dedupeKey])) {
            continue;
        }
        $sentRecipients[$dedupeKey] = true;

        if ($channels['web']) {
            $webResult = broadcastInsertWebNotification($conn, $recipient, $title, $content);
            if (!empty($webResult['success'])) {
                $results['web']['sent']++;
            } else {
                $results['web']['failed']++;
                $results['web']['last_error'] = $webResult['reason'] ?? 'insert_failed';
            }
        }

        if ($channels['mail']) {
            $eventKey = md5($dedupeKey . '|' . $title . '|' . $content);
            $mailResult = broadcastSendMail($conn, $recipient, $eventKey, $title, $title, $content);
            if (!empty($mailResult['success'])) {
                $results['mail']['sent']++;
            } else {
                $results['mail']['failed']++;
                $results['mail']['last_error'] = $mailResult['reason'] ?? ($mailResult['message'] ?? 'send_failed');
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đã xử lý gửi thông báo.',
        'data' => [
            'title' => $title,
            'scope' => $scope,
            'roles' => $roles,
            'channels' => $channels,
            'targets' => count($sentRecipients),
            'results' => $results,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể gửi thông báo: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
