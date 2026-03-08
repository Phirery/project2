<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$maLichKham = (int)($data['maLichKham'] ?? 0);
$lyDo = trim((string)($data['lyDo'] ?? ($data['deleteReason'] ?? '')));
$lyDo = $lyDo !== '' ? $lyDo : 'Xóa mềm lịch khám bởi quản trị viên';

if ($maLichKham <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Mã lịch khám không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT trangThai, ghiChu FROM lichkham WHERE maLichKham = ? LIMIT 1");
    $stmt->bind_param("i", $maLichKham);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        throw new Exception('Không tìm thấy lịch khám để xóa mềm');
    }

    if ($appointment['trangThai'] === 'Hủy') {
        throw new Exception('Lịch khám đã ở trạng thái hủy');
    }

    if ($appointment['trangThai'] === 'Hoàn thành') {
        throw new Exception('Không thể xóa mềm lịch khám đã hoàn thành');
    }

    $currentNote = trim((string)($appointment['ghiChu'] ?? ''));
    $reasonLine = '[Lý do hủy]: ' . $lyDo;
    $finalNote = $currentNote === '' ? $reasonLine : $currentNote;
    if ($currentNote !== '' && strpos($currentNote, $reasonLine) === false) {
        $finalNote .= "\n" . $reasonLine;
    }

    $stmt = $conn->prepare("
        UPDATE lichkham
        SET trangThai = 'Hủy',
            nguoiHuy = 'quantri',
            ghiChu = ?
        WHERE maLichKham = ?
        LIMIT 1
    ");
    $stmt->bind_param("si", $finalNote, $maLichKham);
    $stmt->execute();
    if ($stmt->affected_rows <= 0) {
        $stmt->close();
        throw new Exception('Không thể cập nhật trạng thái lịch khám');
    }
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa mềm lịch khám (chuyển sang trạng thái Hủy)'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
