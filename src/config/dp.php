<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/db-config.php';
try {
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME
    );
    $conn->set_charset(DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Lỗi kết nối database",
    ]);
    exit;
}