<?php
function stringStartsWith(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function stringEndsWith(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, -strlen($needle)) === $needle;
}

function getConfigValue(string $key): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string)$value;
    }

    if (!empty($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }

    if (!empty($_ENV[$key])) {
        return (string)$_ENV[$key];
    }

    return null;
}

function loadDotEnvIfExists(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || stringStartsWith($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if ($name === '') {
            continue;
        }

        if (
            (stringStartsWith($value, '"') && stringEndsWith($value, '"')) ||
            (stringStartsWith($value, "'") && stringEndsWith($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }

        if (!isset($_ENV[$name])) {
            $_ENV[$name] = $value;
        }
        if (!isset($_SERVER[$name])) {
            $_SERVER[$name] = $value;
        }
    }
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalHost = strpos($host, 'localhost') !== false || $host === 'localhost:5500';

// Chỉ dùng một file .env cho cả local/host (mỗi môi trường tự có bản .env riêng)
loadDotEnvIfExists(dirname(__DIR__) . '/.env');

$appEnv = getConfigValue('APP_ENV');
if ($appEnv === null || $appEnv === '') {
    $appEnv = $isLocalHost ? 'local' : 'host';
}

$baseUrlByEnv = [
    'local' => 'http://localhost/DO_AN/src',
    'host'  => 'https://domainex.id.vn',
];

$baseUrl = getConfigValue('APP_BASE_URL');
if ($baseUrl === null || $baseUrl === '') {
    $baseUrl = $baseUrlByEnv[$appEnv] ?? $baseUrlByEnv['host'];
}

if (!defined('APP_ENV')) define('APP_ENV', $appEnv);
if (!defined('APP_BASE_URL')) define('APP_BASE_URL', rtrim($baseUrl, '/'));
