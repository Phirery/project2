<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $maBacSi = trim((string)($data['maBacSi'] ?? ''));
    $deleteReason = trim((string)($data['deleteReason'] ?? ($data['lyDo'] ?? '')));
    $deleteReason = $deleteReason !== '' ? $deleteReason : 'Soft delete by admin';
    $deletedBy = (int)$_SESSION['id'];

    if ($maBacSi === '') {
        throw new Exception('Mã bác sĩ là bắt buộc!');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT bs.nguoiDungId, nd.vaiTro, nd.isDeleted
        FROM bacsi bs
        JOIN nguoidung nd ON bs.nguoiDungId = nd.id
        WHERE bs.maBacSi = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $maBacSi);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doctor) {
        throw new Exception('Không tìm thấy bác sĩ!');
    }

    if ((int)$doctor['isDeleted'] === 1) {
        throw new Exception('Tài khoản bác sĩ đã được xóa trước đó!');
    }

    if ($doctor['vaiTro'] !== 'bacsi') {
        throw new Exception('Tài khoản liên kết không phải bác sĩ!');
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM lichkham
        WHERE maBacSi = ?
          AND trangThai IN ('Chờ', 'Đã đặt')
    ");
    $stmt->bind_param("s", $maBacSi);
    $stmt->execute();
    $activeAppointments = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    if ($activeAppointments > 0) {
        throw new Exception('Không thể xóa mềm bác sĩ vì còn lịch khám đang hoạt động. Vui lòng xử lý lịch trước.');
    }

    $nguoiDungId = (int)$doctor['nguoiDungId'];
    $suffix = strtolower(bin2hex(random_bytes(3)));
    $anonUsername = substr("deleted_u{$nguoiDungId}_{$suffix}", 0, 50);
    $anonEmail = substr("deleted_u{$nguoiDungId}_{$suffix}@deleted.local", 0, 100);
    $anonPhone = substr("DEL{$nguoiDungId}{$suffix}", 0, 16);
    $newPasswordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $anonDoctorName = "Bác sĩ đã xóa #{$maBacSi}";

    $stmt = $conn->prepare("
        UPDATE nguoidung
        SET tenDangNhap = ?,
            soDienThoai = ?,
            email = ?,
            matKhau = ?,
            trangThai = 'Khóa',
            isDeleted = 1,
            deletedAt = NOW(),
            deletedBy = ?,
            deleteReason = ?,
            ngayCapNhatTaiKhoan = NOW()
        WHERE id = ? AND isDeleted = 0
        LIMIT 1
    ");
    $stmt->bind_param(
        "ssssisi",
        $anonUsername,
        $anonPhone,
        $anonEmail,
        $newPasswordHash,
        $deletedBy,
        $deleteReason,
        $nguoiDungId
    );
    $stmt->execute();
    if ($stmt->affected_rows <= 0) {
        $stmt->close();
        throw new Exception('Không thể cập nhật trạng thái xóa tài khoản bác sĩ!');
    }
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE bacsi
        SET tenBacSi = ?,
            moTa = NULL
        WHERE maBacSi = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $anonDoctorName, $maBacSi);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa mềm bác sĩ thành công!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
