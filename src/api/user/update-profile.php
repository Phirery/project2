<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

$nguoiDungId = $_SESSION['id'];
$vaiTro = $_SESSION['vaiTro'];
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    $input = [];
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$values): void
{
    $params = [$types];
    foreach ($values as $idx => &$value) {
        $params[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $params);
}

try {
    $conn->begin_transaction();

    $hasChanges = false;

    // Update nguoidung fields that are actually sent
    if (array_key_exists('soDienThoai', $input)) {
        $soDienThoai = trim((string)$input['soDienThoai']);
        $stmt = $conn->prepare("UPDATE nguoidung SET soDienThoai = ? WHERE id = ?");
        $stmt->bind_param("si", $soDienThoai, $nguoiDungId);
        $stmt->execute();
        $stmt->close();
        $hasChanges = true;
    }

    // Update role-specific fields that are still editable on UI
    if ($vaiTro === 'benhnhan') {
        $setParts = [];
        $types = '';
        $values = [];

        if (array_key_exists('soTheBHYT', $input)) {
            $setParts[] = 'soTheBHYT = ?';
            $types .= 's';
            $values[] = trim((string)$input['soTheBHYT']);
        }

        if (array_key_exists('gioiTinh', $input)) {
            $newGender = strtolower(trim((string)$input['gioiTinh']));
            if (!in_array($newGender, ['nam', 'nu'], true)) {
                throw new Exception('Giới tính không hợp lệ, chỉ chấp nhận Nam hoặc Nữ.');
            }

            $genderStmt = $conn->prepare("SELECT gioiTinh FROM benhnhan WHERE nguoiDungId = ?");
            $genderStmt->bind_param("i", $nguoiDungId);
            $genderStmt->execute();
            $genderRow = $genderStmt->get_result()->fetch_assoc();
            $genderStmt->close();

            if (!$genderRow) {
                throw new Exception('Không tìm thấy hồ sơ bệnh nhân.');
            }

            $currentGender = strtolower(trim((string)$genderRow['gioiTinh']));
            if ($currentGender !== 'khac') {
                throw new Exception('Giới tính chỉ được chỉnh khi tài khoản đang để "Khác".');
            }

            $setParts[] = 'gioiTinh = ?';
            $types .= 's';
            $values[] = $newGender;
        }

        if (!empty($setParts)) {
            $sql = "UPDATE benhnhan SET " . implode(', ', $setParts) . " WHERE nguoiDungId = ?";
            $types .= 'i';
            $values[] = $nguoiDungId;

            $stmt = $conn->prepare($sql);
            bindDynamicParams($stmt, $types, $values);
            $stmt->execute();
            $stmt->close();
            $hasChanges = true;
        }
    } elseif ($vaiTro === 'bacsi') {
        if (array_key_exists('moTa', $input)) {
            $moTa = trim((string)$input['moTa']);
            $stmt = $conn->prepare("UPDATE bacsi SET moTa = ? WHERE nguoiDungId = ?");
            $stmt->bind_param("si", $moTa, $nguoiDungId);
            $stmt->execute();
            $stmt->close();
            $hasChanges = true;
        }
    }

    if ($hasChanges) {
        $touchStmt = $conn->prepare("UPDATE nguoidung SET ngayCapNhatTaiKhoan = NOW() WHERE id = ?");
        $touchStmt->bind_param("i", $nguoiDungId);
        $touchStmt->execute();
        $touchStmt->close();
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => $hasChanges ? 'Cập nhật thành công!' : 'Không có thông tin nào cần cập nhật.'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
