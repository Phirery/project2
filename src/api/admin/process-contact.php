<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';
require_once '../../includes/mail-events.php';

// Kiểm tra quyền admin
require_role('quantri');

function toBool($value, bool $default = true): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    if (is_numeric($value)) {
        return ((int)$value) !== 0;
    }

    return $default;
}

// Lấy dữ liệu JSON từ request
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

// Validate dữ liệu
if (!isset($data['maLienHe'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin mã liên hệ'
    ]);
    exit;
}

$maLienHe = (int)$data['maLienHe'];
if ($maLienHe <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Mã liên hệ không hợp lệ'
    ]);
    exit;
}

$ghiChuRaw = trim((string)($data['ghiChu'] ?? ''));
$ghiChu = $ghiChuRaw !== '' ? $ghiChuRaw : null;

$sendEmail = array_key_exists('sendEmail', $data)
    ? toBool($data['sendEmail'], true)
    : true;

$emailModeInput = strtolower(trim((string)($data['emailMode'] ?? 'default')));
$emailMode = $emailModeInput === 'custom' ? 'custom' : 'default';
$emailMessage = trim((string)($data['emailMessage'] ?? ''));

if ($sendEmail && $emailMode === 'custom' && $emailMessage === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng nhập nội dung phản hồi email'
    ]);
    exit;
}

$nguoiXuLy = (int)$_SESSION['id'];

try {
    // Kiểm tra liên hệ tồn tại
    $checkStmt = $conn->prepare("SELECT maLienHe, trangThai FROM lienhe WHERE maLienHe = ? LIMIT 1");
    if (!$checkStmt) {
        throw new Exception($conn->error);
    }
    $checkStmt->bind_param('i', $maLienHe);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if (!$checkResult || $checkResult->num_rows === 0) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy liên hệ'
        ]);
        exit;
    }

    $contact = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Kiểm tra trạng thái hiện tại
    if ($contact['trangThai'] === 'Đã xử lý') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Liên hệ này đã được xử lý trước đó'
        ]);
        exit;
    }

    // Cập nhật trạng thái
    if ($ghiChu !== null) {
        $updateStmt = $conn->prepare(
            "UPDATE lienhe
             SET trangThai = 'Đã xử lý', nguoiXuLy = ?, thoiGianXuLy = NOW(), ghiChu = ?
             WHERE maLienHe = ?"
        );
        if (!$updateStmt) {
            throw new Exception($conn->error);
        }
        $updateStmt->bind_param('isi', $nguoiXuLy, $ghiChu, $maLienHe);
    } else {
        $updateStmt = $conn->prepare(
            "UPDATE lienhe
             SET trangThai = 'Đã xử lý', nguoiXuLy = ?, thoiGianXuLy = NOW()
             WHERE maLienHe = ?"
        );
        if (!$updateStmt) {
            throw new Exception($conn->error);
        }
        $updateStmt->bind_param('ii', $nguoiXuLy, $maLienHe);
    }

    if ($updateStmt->execute()) {
        $mailStatus = [
            'attempted' => false,
            'success' => null,
            'reason' => null,
            'mode' => $sendEmail ? $emailMode : 'disabled',
        ];

        if ($sendEmail) {
            $mailStatus['attempted'] = true;
            $customResponse = $emailMode === 'custom' ? $emailMessage : null;
            $mailResult = sendContactProcessedEmail($conn, $maLienHe, $customResponse, false);
            $mailStatus['success'] = (bool)($mailResult['success'] ?? false);
            $mailStatus['reason'] = $mailResult['reason'] ?? ($mailResult['message'] ?? null);
        }

        $message = 'Xử lý liên hệ thành công';
        if ($mailStatus['attempted']) {
            $message .= $mailStatus['success']
                ? ' và đã gửi email xác nhận.'
                : ' nhưng chưa gửi được email xác nhận.';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'mailStatus' => $mailStatus
        ]);
    } else {
        throw new Exception($conn->error);
    }
    $updateStmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi xử lý liên hệ: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
