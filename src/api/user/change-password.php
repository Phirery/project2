<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

$nguoiDungId = $_SESSION['id'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Get current password
    $stmt = $conn->prepare("SELECT matKhau FROM nguoidung WHERE id = ?");
    $stmt->bind_param("i", $nguoiDungId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result) {
        throw new Exception('Không tìm thấy người dùng!');
    }
    
    // Verify current password
    if (!password_verify($input['currentPassword'], $result['matKhau'])) {
        throw new Exception('Mật khẩu hiện tại không đúng!');
    }
    
    // Update password
    $newPasswordHash = password_hash($input['newPassword'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE nguoidung SET matKhau = ?, ngayCapNhatMatKhau = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newPasswordHash, $nguoiDungId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>