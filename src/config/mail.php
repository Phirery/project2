<?php
function mailEnv(string $key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function mailEnvBool(string $key, bool $default): bool {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function mailEnvInt(string $key, int $default): int {
    $value = getenv($key);
    if ($value === false || $value === '' || !is_numeric($value)) {
        return $default;
    }
    return (int)$value;
}

return [
    // Brevo-only mode (SMTP removed)
    'transport' => 'brevo_api',
    'timeout' => mailEnvInt('MAIL_TIMEOUT', 20),
    'from_name' => mailEnv('MAIL_FROM_NAME', 'Eden Health - Phòng khám'),
    'from_email' => mailEnv('MAIL_FROM_EMAIL', ''),
    'brevo_api_key' => mailEnv('BREVO_API_KEY', ''),
    'debug' => mailEnvBool('MAIL_DEBUG', false),
];
