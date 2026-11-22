<?php
/**
 * Helper kiểm tra phân quyền (RBAC) phía server cho vai trò `nhanvien`.
 * - Không mở rộng ENUM `nguoidung.vaiTro`: chuyên môn nhân viên được cấp qua nhóm quyền.
 * - Luôn truy vấn trực tiếp quyền đang hoạt động từ DB để thu hồi/cấp quyền có hiệu lực ngay.
 */

require_once __DIR__ . '/../config/session.php';

/**
 * Danh mục module giao diện cổng nhân viên và permission tương ứng.
 * Thứ tự mảng là thứ tự hiển thị trên sidebar nhân viên.
 */
function staff_module_catalog(): array
{
    return [
        [
            'key' => 'dashboard',
            'pageKey' => 'dashboard-nhanvien',
            'label' => 'Dashboard',
            'icon' => 'fas fa-chart-line',
            'href' => 'dashboard-nhanvien.html',
            'requiredPermission' => 'staff.dashboard.view',
            'group' => 'Tổng quan',
            'description' => 'Theo dõi công việc trong ngày và truy cập nhanh các khu vực được cấp quyền.',
        ],
        [
            'key' => 'reception',
            'pageKey' => 'check-in',
            'label' => 'Tiếp nhận bệnh nhân',
            'icon' => 'fas fa-door-open',
            'href' => 'check-in.html',
            'requiredPermission' => 'reception.checkin',
            'group' => 'Tiếp đón & Sàng lọc',
            'description' => 'Tra cứu, tạo hồ sơ tiếp nhận trực tiếp, quét QR và check-in hàng đợi khám.',
        ],
        [
            'key' => 'nursing',
            'pageKey' => 'dieu-duong',
            'label' => 'Điều dưỡng sàng lọc',
            'icon' => 'fas fa-user-nurse',
            'href' => 'dieu-duong.html',
            'requiredPermission' => 'nursing.screen',
            'group' => 'Tiếp đón & Sàng lọc',
            'description' => 'Đo sinh hiệu, ghi nhận dị ứng/cảnh báo lâm sàng và chuyển hàng đợi bác sĩ.',
        ],
        [
            'key' => 'cls-lab',
            'pageKey' => 'can-lam-sang?nhom=xet-nghiem',
            'label' => 'Xét nghiệm',
            'icon' => 'fas fa-vials',
            'href' => 'can-lam-sang.html?nhom=xet-nghiem',
            'requiredPermission' => 'cls.xet_nghiem.perform',
            'group' => 'Cận lâm sàng',
            'description' => 'Nhận mẫu, cập nhật trạng thái thực hiện và nhập/trả kết quả xét nghiệm.',
        ],
        [
            'key' => 'cls-imaging',
            'pageKey' => 'can-lam-sang?nhom=chan-doan-hinh-anh',
            'label' => 'Chẩn đoán hình ảnh',
            'icon' => 'fas fa-x-ray',
            'href' => 'can-lam-sang.html?nhom=chan-doan-hinh-anh',
            'requiredPermission' => 'cls.cdha.perform',
            'group' => 'Cận lâm sàng',
            'description' => 'Thực hiện X-quang, siêu âm, ECG, đính kèm hình ảnh và nhập kết luận sơ bộ.',
        ],
        [
            'key' => 'cls-endoscopy',
            'pageKey' => 'can-lam-sang?nhom=noi-soi',
            'label' => 'Nội soi',
            'icon' => 'fas fa-microscope',
            'href' => 'can-lam-sang.html?nhom=noi-soi',
            'requiredPermission' => 'cls.noi_soi.perform',
            'group' => 'Cận lâm sàng',
            'description' => 'Kiểm tra checklist chuẩn bị, thực hiện thủ thuật và trả biên bản nội soi.',
        ],
        [
            'key' => 'pharmacy',
            'pageKey' => 'phat-thuoc',
            'label' => 'Phát thuốc',
            'icon' => 'fas fa-pills',
            'href' => 'phat-thuoc.html',
            'requiredPermission' => 'pharmacy.dispense',
            'group' => 'Dược & Thanh toán',
            'description' => 'Nhận đơn chờ phát, đối chiếu tồn kho, phát thuốc đủ/một phần và trừ tồn nguyên tử.',
        ],
        [
            'key' => 'billing',
            'pageKey' => 'thu-ngan',
            'label' => 'Thu ngân',
            'icon' => 'fas fa-file-invoice-dollar',
            'href' => 'thu-ngan.html',
            'requiredPermission' => 'billing.collect',
            'group' => 'Dược & Thanh toán',
            'description' => 'Tổng hợp phí khám, dịch vụ CLS, thuốc thực phát và xác nhận thanh toán hóa đơn.',
        ],
    ];
}

function staff_get_current_staff(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            nv.maNhanVien,
            nv.tenNhanVien,
            nv.loaiNhanVien,
            nv.gioiTinh,
            nv.ngayVaoLam,
            nd.id,
            nd.tenDangNhap,
            nd.soDienThoai,
            nd.email,
            nd.avatar,
            nd.trangThai,
            nd.isDeleted
        FROM nhanvien nv
        JOIN nguoidung nd ON nv.nguoiDungId = nd.id
        WHERE nv.nguoiDungId = ?
          AND nd.vaiTro = 'nhanvien'
          AND nd.isDeleted = 0
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn thông tin nhân viên');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function staff_get_rbac_context(mysqli $conn, int $userId): array
{
    $staff = staff_get_current_staff($conn, $userId);
    if (!$staff) {
        return [
            'staff' => null,
            'roles' => [],
            'permissions' => [],
            'isDemo' => false,
            'modules' => [],
            'defaultRoute' => 'dashboard-nhanvien.html',
        ];
    }

    $maNhanVien = (string)$staff['maNhanVien'];

    // Lấy danh sách nhóm quyền đang kích hoạt của nhân viên
    $roleStmt = $conn->prepare("
        SELECT nq.maNhomQuyen, nq.tenNhomQuyen, nq.moTa, nq.isDemo
        FROM nhanvien_gan_nhom_quyen g
        JOIN nhanvien_nhom_quyen nq ON g.maNhomQuyen = nq.maNhomQuyen
        WHERE g.maNhanVien = ?
          AND g.isActive = 1
          AND nq.isActive = 1
        ORDER BY nq.isDemo ASC, nq.tenNhomQuyen ASC
    ");
    if (!$roleStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn nhóm quyền nhân viên');
    }
    $roleStmt->bind_param('s', $maNhanVien);
    $roleStmt->execute();
    $rolesRaw = $roleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $roleStmt->close();

    $roles = [];
    $isDemo = false;
    foreach ($rolesRaw as $r) {
        $demoFlag = (int)($r['isDemo'] ?? 0) === 1 || $r['maNhomQuyen'] === 'nhanvien_tong_hop_demo';
        if ($demoFlag) {
            $isDemo = true;
        }
        $roles[] = [
            'maNhomQuyen' => $r['maNhomQuyen'],
            'tenNhomQuyen' => $r['tenNhomQuyen'],
            'moTa' => $r['moTa'] ?? '',
            'isDemo' => $demoFlag,
        ];
    }

    // Lấy danh sách permission hợp lệ từ nhóm quyền đang hoạt động
    $permStmt = $conn->prepare("
        SELECT DISTINCT q.maQuyen
        FROM nhanvien_gan_nhom_quyen g
        JOIN nhanvien_nhom_quyen nq ON g.maNhomQuyen = nq.maNhomQuyen
        JOIN nhanvien_nhom_quyen_chitiet ct ON nq.maNhomQuyen = ct.maNhomQuyen
        JOIN nhanvien_quyen q ON ct.maQuyen = q.maQuyen
        WHERE g.maNhanVien = ?
          AND g.isActive = 1
          AND nq.isActive = 1
          AND q.isActive = 1
        ORDER BY q.maQuyen ASC
    ");
    if (!$permStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn danh sách quyền nhân viên');
    }
    $permStmt->bind_param('s', $maNhanVien);
    $permStmt->execute();
    $permRows = $permStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $permStmt->close();

    $permissions = array_values(array_map(static function ($row) {
        return (string)$row['maQuyen'];
    }, $permRows));

    $modules = [];
    foreach (staff_module_catalog() as $module) {
        if (staff_has_permission($permissions, $module['requiredPermission'])) {
            $modules[] = $module;
        }
    }

    $defaultRoute = 'dashboard-nhanvien.html';
    if (!staff_has_permission($permissions, 'staff.dashboard.view') && !empty($modules)) {
        $defaultRoute = $modules[0]['href'];
    }

    return [
        'staff' => [
            'id' => (int)$staff['id'],
            'maNhanVien' => $staff['maNhanVien'],
            'tenNhanVien' => $staff['tenNhanVien'] ?: $staff['tenDangNhap'],
            'loaiNhanVien' => $staff['loaiNhanVien'],
            'tenDangNhap' => $staff['tenDangNhap'],
            'soDienThoai' => $staff['soDienThoai'],
            'email' => $staff['email'],
            'avatar' => $staff['avatar'],
        ],
        'roles' => $roles,
        'permissions' => $permissions,
        'isDemo' => $isDemo,
        'modules' => $modules,
        'defaultRoute' => $defaultRoute,
    ];
}

/**
 * Kiểm tra danh sách quyền hiện có có thỏa mãn quyền yêu cầu hay không.
 *
 * @param string[] $userPermissions
 * @param string|string[] $requiredPermissions
 */
function staff_has_permission(array $userPermissions, $requiredPermissions, bool $requireAll = false): bool
{
    $required = is_array($requiredPermissions) ? $requiredPermissions : [$requiredPermissions];
    $required = array_values(array_filter(array_map(static function ($item) {
        return trim((string)$item);
    }, $required), static function ($item) {
        return $item !== '';
    }));

    if (empty($required)) {
        return true;
    }

    $lookup = array_flip($userPermissions);
    if ($requireAll) {
        foreach ($required as $perm) {
            if (!isset($lookup[$perm])) {
                return false;
            }
        }
        return true;
    }

    foreach ($required as $perm) {
        if (isset($lookup[$perm])) {
            return true;
        }
    }
    return false;
}

/**
 * Middleware bắt buộc vai trò `nhanvien` và kiểm tra permission ở phía server.
 * Trả HTTP 403 JSON nếu thiếu permission hoặc chưa được gắn hồ sơ nhân viên.
 *
 * @param string|string[] $requiredPermissions
 */
function require_staff_permission($requiredPermissions, bool $requireAll = false): array
{
    require_role('nhanvien');

    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof mysqli) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Không có kết nối cơ sở dữ liệu để kiểm tra phân quyền.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $context = staff_get_rbac_context($conn, (int)$_SESSION['id']);
    if (empty($context['staff'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'STAFF_PROFILE_MISSING',
            'message' => 'Tài khoản chưa có hồ sơ nhân viên hợp lệ.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!staff_has_permission($context['permissions'], $requiredPermissions, $requireAll)) {
        $requiredList = is_array($requiredPermissions) ? array_values($requiredPermissions) : [$requiredPermissions];
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'FORBIDDEN_PERMISSION',
            'message' => 'Bạn không có quyền thực hiện chức năng này.',
            'requiredPermissions' => $requiredList,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $context;
}
