<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'None',
    ]);

    session_start();
}

function require_role($roles) {
    
    // Kiểm tra xem đã đăng nhập chưa
    if (!isset($_SESSION['id']) || !isset($_SESSION['vaiTro'])) {
        http_response_code(401); // 401
        echo json_encode([
            'success' => false,
            'message' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.'
        ]);
        exit;
    }

    // Lấy vai trò của người dùng từ session
    $userRole = $_SESSION['vaiTro'];

    // Kiểm tra vai trò
    $isAllowed = false;
    
    if (is_array($roles)) {
        // Nếu $roles là một mảng
        if (in_array($userRole, $roles)) {
            $isAllowed = true;
        }
    } else {
        // Nếu $roles là một chuỗi
        if ($userRole === $roles) {
            $isAllowed = true;
        }
    }

    // Nếu không được phép, trả về lỗi
    if (!$isAllowed) {
        http_response_code(403); // 403
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có quyền thực hiện hành động này.'
        ]);
        exit;
    }
}