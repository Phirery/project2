<?php
/**
 * API Helper chuẩn dùng cho các endpoint viết mới trong src/api/<role>/<action>.php.
 *
 * Mục tiêu:
 * - Giữ nguyên cấu trúc JSON quen thuộc của dự án ({ success, message, data }).
 * - Chặn sai HTTP method (405) và trả đúng HTTP status code (200/201/400/401/403/404/405/500).
 * - Không làm lộ chi tiết lỗi DB/SQL ra client khi chạy production (APP_ENV === 'host').
 *
 * Cách dùng mẫu trong file API mới:
 *   require_once '../../config/cors.php';
 *   require_once '../../config/db.php';
 *   require_once '../../config/session.php';
 *   require_once '../../includes/api-helper.php';
 *
 *   api_require_method('POST');
 *   require_role('quantri');
 *   $input = api_json_input(true);
 *   api_validate_required($input, ['tenKhoa' => 'Tên khoa']);
 */

function api_close_db_if_open(): void
{
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        try {
            @$GLOBALS['conn']->close();
        } catch (Throwable $ignored) {
            // Bỏ qua nếu kết nối đã được đóng trước đó
        }
    }
}

/**
 * Chặn request nếu HTTP method không nằm trong danh sách cho phép.
 *
 * @param string|array $allowedMethods Ví dụ: 'POST' hoặc ['GET', 'POST']
 */
function api_require_method($allowedMethods): void
{
    $allowed = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];
    $allowed = array_map(static function ($m) {
        return strtoupper(trim((string)$m));
    }, $allowed);

    $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($current === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (!in_array($current, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error('Phương thức HTTP không được hỗ trợ cho endpoint này.', 405);
    }
}

/**
 * Đọc và parse JSON body từ request.
 * Trả 400 nếu body có nội dung nhưng JSON sai cú pháp.
 */
function api_json_input(bool $required = false): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        if ($required) {
            api_error('Thiếu dữ liệu gửi lên.', 400);
        }
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        api_error('Dữ liệu JSON không hợp lệ.', 400);
    }

    return $decoded;
}

/**
 * Kiểm tra các trường bắt buộc trong input.
 * $fields có thể là ['maBacSi', 'ngayKham'] hoặc ['maBacSi' => 'Mã bác sĩ', ...].
 */
function api_validate_required(array $input, array $fields): void
{
    $missing = [];
    foreach ($fields as $key => $label) {
        $fieldKey = is_int($key) ? $label : $key;
        $fieldLabel = (string)$label;

        if (!array_key_exists($fieldKey, $input)) {
            $missing[] = $fieldLabel;
            continue;
        }

        $value = $input[$fieldKey];
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $missing[] = $fieldLabel;
        }
    }

    if (!empty($missing)) {
        api_error('Thiếu thông tin bắt buộc: ' . implode(', ', $missing), 400);
    }
}

/**
 * Trả phản hồi thành công chuẩn JSON và kết thúc request.
 */
function api_success($data = null, string $message = '', int $status = 200, array $extra = []): void
{
    http_response_code($status);

    $payload = ['success' => true];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    if (!empty($extra)) {
        $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    api_close_db_if_open();
    exit;
}

/**
 * Trả phản hồi lỗi nghiệp vụ / validation chuẩn JSON và kết thúc request.
 */
function api_error(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);

    $payload = [
        'success' => false,
        'message' => $message,
    ];
    if (!empty($extra)) {
        $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    api_close_db_if_open();
    exit;
}

/**
 * Xử lý ngoại lệ hệ thống (500): luôn ghi error_log,
 * chỉ trả chi tiết debug khi chạy local (không lộ SQL/DB ở môi trường host).
 */
function api_server_error(Throwable $e, string $publicMessage = 'Lỗi hệ thống, vui lòng thử lại sau.'): void
{
    $endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';
    error_log(sprintf('[API 500] %s: %s in %s:%d', $endpoint, $e->getMessage(), $e->getFile(), $e->getLine()));

    $extra = [];
    if (defined('APP_ENV') && APP_ENV !== 'host') {
        $extra['debug'] = $e->getMessage();
    }

    api_error($publicMessage, 500, $extra);
}
