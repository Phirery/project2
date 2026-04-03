<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/medicine-stock.php';

require_role('bacsi');

$input = json_decode(file_get_contents('php://input'), true);
$maHoSo = $input['maHoSo'] ?? '';
$deleteReason = trim((string)($input['deleteReason'] ?? ($input['lyDo'] ?? '')));
$deleteReason = $deleteReason !== '' ? $deleteReason : 'Soft delete by doctor';

if (!$maHoSo) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã hồ sơ']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT maLichKham, trangThai
        FROM hosobenhan
        WHERE maHoSo = ? AND maBacSi = ? AND isDeleted = 0
        LIMIT 1
    ");
    $stmt->bind_param("ss", $maHoSo, $maBacSi);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Thu hồi thất bại hoặc không có quyền']);
        exit;
    }

    if (!empty($record['maLichKham']) && ($record['trangThai'] ?? '') === 'Đã hoàn thành') {
        $existingPrescriptionItems = getPrescriptionItemsByAppointment($conn, (int)$record['maLichKham']);
        applyPrescriptionStockDelta(
            $conn,
            (int)$record['maLichKham'],
            $maHoSo,
            $existingPrescriptionItems,
            [],
            true,
            false
        );
    }

    $stmt = $conn->prepare("
        UPDATE hosobenhan
        SET isDeleted = 1,
            deletedAt = NOW(),
            deletedBy = ?,
            deleteReason = ?
        WHERE maHoSo = ?
          AND maBacSi = ?
          AND isDeleted = 0
        LIMIT 1
    ");
    $doctorUserId = (int)$_SESSION['id'];
    $stmt->bind_param("isss", $doctorUserId, $deleteReason, $maHoSo, $maBacSi);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã thu hồi hồ sơ thành công']);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Thu hồi thất bại hoặc không có quyền']);
    }
    $stmt->close();
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
