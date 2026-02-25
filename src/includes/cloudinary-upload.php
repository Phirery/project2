<?php

function cloudinaryUpload(string $filePath, array $options = []): array {
    $configPath = __DIR__ . '/../config/cloudinary.php';
    if (!file_exists($configPath)) {
        return ['success' => false, 'message' => 'Không tìm thấy cấu hình Cloudinary'];
    }

    $config = require $configPath;
    $cloudName = trim((string)($config['cloud_name'] ?? ''));
    $apiKey = trim((string)($config['api_key'] ?? ''));
    $apiSecret = trim((string)($config['api_secret'] ?? ''));

    if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
        return ['success' => false, 'message' => 'Thiếu thông tin cloud_name/api_key/api_secret'];
    }

    if (!is_file($filePath) || !is_readable($filePath)) {
        return ['success' => false, 'message' => 'File upload tạm không hợp lệ'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'PHP chưa bật extension cURL'];
    }

    $timestamp = time();
    $folder = trim((string)($options['folder'] ?? $config['folder'] ?? 'eden_health/avatars'));
    $publicId = isset($options['public_id']) ? trim((string)$options['public_id']) : '';
    $transformation = trim((string)($options['transformation'] ?? 'c_fill,g_face,h_300,w_300,q_auto,f_auto'));

    $paramsToSign = [
        'folder' => $folder,
        'invalidate' => 'true',
        'overwrite' => 'true',
        'timestamp' => (string)$timestamp,
        'transformation' => $transformation,
    ];
    if ($publicId !== '') {
        $paramsToSign['public_id'] = $publicId;
    }

    ksort($paramsToSign);
    $parts = [];
    foreach ($paramsToSign as $key => $value) {
        $parts[] = $key . '=' . $value;
    }
    $signatureBase = implode('&', $parts);
    $signature = sha1($signatureBase . $apiSecret);

    $postFields = [
        'file' => curl_file_create($filePath),
        'api_key' => $apiKey,
        'signature' => $signature,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'overwrite' => 'true',
        'invalidate' => 'true',
        'transformation' => $transformation,
    ];
    if ($publicId !== '') {
        $postFields['public_id'] = $publicId;
    }

    $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => 'Lỗi cURL: ' . $curlErr];
    }

    $result = json_decode((string)$response, true);
    if (!is_array($result)) {
        return ['success' => false, 'message' => "Cloudinary trả về dữ liệu không hợp lệ (HTTP {$httpCode})"];
    }

    if ($httpCode === 200 && isset($result['secure_url'])) {
        return [
            'success' => true,
            'secure_url' => $result['secure_url'],
            'public_id' => $result['public_id'] ?? null,
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
        ];
    }

    $errMsg = $result['error']['message'] ?? "HTTP {$httpCode}: Upload thất bại";
    return ['success' => false, 'message' => $errMsg];
}
