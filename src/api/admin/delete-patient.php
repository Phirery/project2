<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('quantri');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $maBenhNhan = trim((string)($data['maBenhNhan'] ?? ''));
    $deleteReason = trim((string)($data['deleteReason'] ?? ($data['lyDo'] ?? '')));
    $deleteReason = $deleteReason !== '' ? $deleteReason : 'Soft delete by admin';
    $deletedBy = (int)$_SESSION['id'];

    if (empty($maBenhNhan)) {
        throw new Exception('Mã bệnh nhân là bắt buộc!');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT bn.nguoiDungId, nd.vaiTro, nd.isDeleted
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        WHERE bn.maBenhNhan = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $maBenhNhan);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        throw new Exception('Không tìm thấy bệnh nhân!');
    }

    if ((int)$patient['isDeleted'] === 1) {
        throw new Exception('Tài khoản bệnh nhân đã được xóa trước đó!');
    }

    if ($patient['vaiTro'] !== 'benhnhan') {
        throw new Exception('Tài khoản liên kết không phải bệnh nhân!');
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM lichkham
        WHERE maBenhNhan = ?
          AND trangThai IN ('Chờ', 'Đã đặt')
    ");
    $stmt->bind_param("s", $maBenhNhan);
    $stmt->execute();
    $activeAppointments = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    if ($activeAppointments > 0) {
        throw new Exception('Không thể xóa mềm bệnh nhân vì còn lịch khám đang hoạt động. Vui lòng xử lý lịch trước.');
    }

    $nguoiDungId = (int)$patient['nguoiDungId'];
    $suffix = strtolower(bin2hex(random_bytes(3)));
    $anonUsername = substr("deleted_u{$nguoiDungId}_{$suffix}", 0, 50);
    $anonEmail = substr("deleted_u{$nguoiDungId}_{$suffix}@deleted.local", 0, 100);
    $anonPhone = substr("DEL{$nguoiDungId}{$suffix}", 0, 16);
    $newPasswordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $anonPatientName = "Bệnh nhân đã xóa #{$maBenhNhan}";

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
        throw new Exception('Không thể cập nhật trạng thái xóa tài khoản!');
    }
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE benhnhan
        SET tenBenhNhan = ?,
            ngaySinh = NULL,
            gioiTinh = NULL,
            soTheBHYT = NULL
        WHERE maBenhNhan = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $anonPatientName, $maBenhNhan);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa mềm bệnh nhân thành công!'
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
