<?php
require_once __DIR__ . '/app-env.php';

function corsEnv(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (string)$value;
}

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = [
    'http://localhost',
    'http://localhost:5500',
    'http://127.0.0.1',
    'http://127.0.0.1:5500',
    'https://domainex.id.vn',
    'https://www.domainex.id.vn',
];

$extraOriginsRaw = corsEnv('CORS_ALLOWED_ORIGINS', '');
if ($extraOriginsRaw !== '') {
    $extraOrigins = array_filter(array_map('trim', explode(',', $extraOriginsRaw)));
    foreach ($extraOrigins as $originItem) {
        if (!in_array($originItem, $allowedOrigins, true)) {
            $allowedOrigins[] = $originItem;
        }
    }
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
} elseif ($origin === '' && APP_ENV === 'host') {
    // Fallback for non-browser clients when hosting
    header('Access-Control-Allow-Origin: ' . corsEnv('CORS_DEFAULT_ORIGIN', 'https://domainex.id.vn'));
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
date_default_timezone_set('Asia/Ho_Chi_Minh');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
