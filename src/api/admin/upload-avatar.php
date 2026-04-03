<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/cloudinary-upload.php';

require_role('quantri');

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File vượt quá giới hạn upload_max_filesize trong php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File vượt quá giới hạn MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File chỉ được upload một phần',
        UPLOAD_ERR_NO_FILE => 'Không có file nào được chọn',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file lên server'
    ];
    $errCode = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg = $uploadErrors[$errCode] ?? 'Lỗi upload không xác định';
    echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$tmpPath = $_FILES['avatar']['tmp_name'];

if (!is_uploaded_file($tmpPath)) {
    echo json_encode(['success' => false, 'message' => 'File upload không hợp lệ hoặc đã bị mất'], JSON_UNESCAPED_UNICODE);
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxBytes = 5 * 1024 * 1024;
if ($_FILES['avatar']['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File ảnh không được vượt quá 5MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $publicId = 'doctor_admin_' . date('YmdHis') . '_' . random_int(1000, 9999);
    $uploadResult = cloudinaryUpload($tmpPath, [
        'folder' => 'eden_health/avatars/doctors',
        'public_id' => $publicId
    ]);

    if (!$uploadResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Upload thất bại: ' . ($uploadResult['message'] ?? 'Lỗi không xác định')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Upload avatar thành công!',
        'avatarUrl' => $uploadResult['secure_url'] ?? null
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
