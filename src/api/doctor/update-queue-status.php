<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('bacsi');

/**
 * Chuyển trạng thái 1 dòng hàng đợi. Các chiều chuyển hợp lệ:
 *   Đang chờ  -> Đang khám   (Gọi khám)
 *   Đang chờ  -> Bỏ lỡ       (Bỏ lỡ trước khi gọi)
 *   Đang khám -> Bỏ lỡ       (Bỏ lỡ sau khi đã gọi nhưng bệnh nhân không có mặt)
 *   Bỏ lỡ     -> Đang khám   (Gọi lại)
 * Không cho chuyển sang "Hoàn thành" ở đây - việc đó chỉ diễn ra qua
 * update-record.php khi bác sĩ hoàn tất hồ sơ bệnh án.
 * Không giới hạn số người "Đang khám" cùng lúc.
 */

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$maHangDoi = isset($input['maHangDoi']) ? (int)$input['maHangDoi'] : 0;
$action = trim((string)($input['action'] ?? ''));

$allowedTransitions = [
    'goikham' => ['from' => 'Đang chờ', 'to' => 'Đang khám'],
    'bolo'    => ['from' => ['Đang chờ', 'Đang khám'], 'to' => 'Bỏ lỡ'],
    'goilai'  => ['from' => 'Bỏ lỡ', 'to' => 'Đang khám']
];

if ($maHangDoi <= 0 || !isset($allowedTransitions[$action])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param('i', $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $transition = $allowedTransitions[$action];
    $fromStates = is_array($transition['from']) ? $transition['from'] : [$transition['from']];
    $toState = $transition['to'];

    $conn->begin_transaction();

    $lockStmt = $conn->prepare("
        SELECT maHangDoi, trangThai FROM hangdoikham
        WHERE maHangDoi = ? AND maBacSi = ? FOR UPDATE
    ");
    $lockStmt->bind_param('is', $maHangDoi, $maBacSi);
    $lockStmt->execute();
    $row = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$row) {
        throw new Exception('Không tìm thấy hàng đợi hoặc không có quyền');
    }

    if (!in_array($row['trangThai'], $fromStates, true)) {
        throw new Exception('Trạng thái hiện tại không cho phép thao tác này (đang là "' . $row['trangThai'] . '")');
    }

    $extraSet = '';
    if ($action === 'goikham') {
        $extraSet = ", thoiGianGoiKham = NOW()";
    } elseif ($action === 'goilai') {
        $extraSet = ", thoiGianGoiKham = NOW()";
    }

    $updateStmt = $conn->prepare("UPDATE hangdoikham SET trangThai = ? $extraSet WHERE maHangDoi = ?");
    $updateStmt->bind_param('si', $toState, $maHangDoi);
    if (!$updateStmt->execute()) {
        throw new Exception('Không thể cập nhật trạng thái: ' . $updateStmt->error);
    }
    $updateStmt->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công', 'trangThai' => $toState], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>