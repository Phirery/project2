<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$id = intval($data['id'] ?? 0);
$lock = !empty($data['lock']);
$notifyEmail = !isset($data['notifyEmail']) ? true : (bool)$data['notifyEmail'];
$reason = trim((string)($data['reason'] ?? ''));

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu ID tài khoản'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$newStatus = $lock ? 'Khóa' : 'Hoạt Động';
$defaultReason = $lock
    ? 'Vi phạm chính sách sử dụng của hệ thống.'
    : 'Tài khoản đã được mở khóa và có thể đăng nhập lại.';
$reasonToUse = $reason !== '' ? $reason : $defaultReason;

$stmt = $conn->prepare("SELECT id, vaiTro, trangThai, isDeleted FROM nguoidung WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể kiểm tra tài khoản'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Tài khoản không tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)($user['isDeleted'] ?? 0) === 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể thao tác với tài khoản đã xóa mềm'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtolower((string)$user['vaiTro']) === 'quantri' && $newStatus === 'Khóa') {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể khóa tài khoản quản trị viên'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)$user['trangThai'] === $newStatus) {
    echo json_encode([
        'success' => true,
        'message' => 'Trạng thái tài khoản không thay đổi',
        'mail' => [
            'attempted' => false,
            'sent' => false,
            'reason' => 'status_unchanged'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->begin_transaction();

try {
    $stmtUpdate = $conn->prepare("UPDATE nguoidung SET trangThai = ? WHERE id = ?");
    if (!$stmtUpdate) {
        throw new Exception('Không thể cập nhật trạng thái tài khoản');
    }

    $stmtUpdate->bind_param("si", $newStatus, $id);
    if (!$stmtUpdate->execute()) {
        $stmtUpdate->close();
        throw new Exception('Không thể cập nhật trạng thái tài khoản');
    }
    $stmtUpdate->close();

    $conn->commit();

    $mailResult = [
        'attempted' => false,
        'sent' => false,
        'reason' => 'notify_disabled'
    ];

    if ($notifyEmail) {
        $mail = sendAccountStatusChangedEmail($conn, $id, $newStatus, $reasonToUse);
        $mailResult = [
            'attempted' => true,
            'sent' => !empty($mail['success']),
            'reason' => $mail['reason'] ?? ($mail['message'] ?? null)
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => ($newStatus === 'Khóa' ? 'Khóa' : 'Mở khóa') . ' tài khoản thành công',
        'mail' => $mailResult
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
