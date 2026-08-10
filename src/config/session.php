<?php
require_once __DIR__ . '/app-env.php';

if (session_status() === PHP_SESSION_NONE) {

    // Host thì bật Secure (HTTPS), local thì không
    $isSecure = (APP_ENV === 'host');

    session_set_cookie_params([
        'lifetime' => 0,            // Hết hạn khi đóng browser
        'path'     => '/',          // Dùng toàn site
        'domain'   => '',           // Không set domain (an toàn nhất)
        'secure'   => $isSecure,    // Host = true, Local = false
        'httponly' => true,         // JS không đọc được cookie
        'samesite' => 'Lax',        // Phù hợp cho web cùng domain
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

    // Kiểm tra trạng thái tài khoản trực tiếp từ DB nếu có kết nối
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("SELECT vaiTro, trangThai, isDeleted FROM nguoidung WHERE id = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $dbUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$dbUser || (int)$dbUser['isDeleted'] === 1) {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();

                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tài khoản không còn hợp lệ. Vui lòng đăng nhập lại.'
                ]);
                exit;
            }

            if ($dbUser['trangThai'] === 'Khóa') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tài khoản đã bị khóa. Không thể thực hiện hành động này.'
                ]);
                exit;
            }

            // Đồng bộ vai trò theo DB để tránh session cũ
            $userRole = $dbUser['vaiTro'];
            $_SESSION['vaiTro'] = $userRole;
        }
    }

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