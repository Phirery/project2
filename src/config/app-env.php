<?php
$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || $host === 'localhost:5500') {
    $appEnv = 'local';
} else {
    $appEnv = 'host';
}

$baseUrlByEnv = [
    'local' => 'http://localhost/DO_AN/src',
    'host'  => 'https://domainex.id.vn',
];

$baseUrl = $baseUrlByEnv[$appEnv];

if (!defined('APP_ENV')) define('APP_ENV', $appEnv);
if (!defined('APP_BASE_URL')) define('APP_BASE_URL', rtrim($baseUrl, '/'));