<?php
require_once __DIR__ . '/app-env.php';

function dbEnv(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (string)$value;
}

$localDb = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'datlichkham',
];

$hostDb = [
    'host' => dbEnv('HOST_DB_HOST', 'localhost'),
    'user' => dbEnv('HOST_DB_USER', ''),
    'pass' => dbEnv('HOST_DB_PASS', ''),
    'name' => dbEnv('HOST_DB_NAME', ''),
];

$dbConfig = APP_ENV === 'host' ? $hostDb : $localDb;

define('DB_HOST', dbEnv('DB_HOST', $dbConfig['host']));
define('DB_USER', dbEnv('DB_USER', $dbConfig['user']));
define('DB_PASS', dbEnv('DB_PASS', $dbConfig['pass']));
define('DB_NAME', dbEnv('DB_NAME', $dbConfig['name']));
define('DB_CHARSET', 'utf8mb4');
date_default_timezone_set('Asia/Ho_Chi_Minh');
