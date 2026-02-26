<?php
$appEnv = getenv('APP_ENV') ?: 'local';
if (!in_array($appEnv, ['local', 'host'], true)) {
    $appEnv = 'local';
}

$baseUrlByEnv = [
    'local' => 'http://localhost/DO_AN/src',
    'host' => 'https://domainex.id.vn',
];

$baseUrl = getenv('APP_BASE_URL');
if (!$baseUrl) {
    $baseUrl = $baseUrlByEnv[$appEnv];
}

if (!defined('APP_ENV')) {
    define('APP_ENV', $appEnv);
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim($baseUrl, '/'));
}