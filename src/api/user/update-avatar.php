<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/cloudinary-upload.php'; // helper cURL

// Chỉ bác sĩ được đổi avatar
$vaiTro = $_SESSION['vaiTro'];
$nguoiDungId = $_SESSION['id'];

// ── Kiểm tra file upload ──────────────────────────────────
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File vượt quá giới hạn upload_max_filesize trong php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File vượt quá giới hạn MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần',
        UPLOAD_ERR_NO_FILE    => 'Không có file nào được chọn',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file lên server',
    ];
    $errCode = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg  = $uploadErrors[$errCode] ?? 'Lỗi upload không xác định';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

// ── Validate loại file ────────────────────────────────────
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$tmpPath      = $_FILES['avatar']['tmp_name'];

if (!is_uploaded_file($tmpPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'File upload không hợp lệ hoặc đã bị mất'
    ]);
    exit;
}

$detectedMime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detectedMime = (string)finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
    }
}
if ($detectedMime === '' && function_exists('mime_content_type')) {
    $detectedMime = (string)mime_content_type($tmpPath);
}

if (!in_array($detectedMime, $allowedMimes, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP hoặc GIF (MIME: ' . ($detectedMime ?: 'unknown') . ')'
    ]);
    exit;
}

// ── Validate kích thước (tối đa 5MB) ─────────────────────
$maxBytes = 5 * 1024 * 1024;
if ($_FILES['avatar']['size'] > $maxBytes) {
    echo json_encode([
        'success' => false,
        'message' => 'File ảnh không được vượt quá 5MB'
    ]);
    exit;
}

// ── Upload lên Cloudinary ─────────────────────────────────
try {
    $uploadResult = cloudinaryUpload($tmpPath, [
        'public_id' => 'bacsi_' . $nguoiDungId, // ghi đè file cũ cùng user
    ]);

    if (!$uploadResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Upload thất bại: ' . $uploadResult['message']
        ]);
        exit;
    }

    $avatarUrl = $uploadResult['secure_url'];

    // ── Lưu URL vào database ──────────────────────────────
    $stmt = $conn->prepare("UPDATE nguoidung SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $avatarUrl, $nguoiDungId);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'message'   => 'Cập nhật ảnh đại diện thành công!',
        'avatarUrl' => $avatarUrl
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
