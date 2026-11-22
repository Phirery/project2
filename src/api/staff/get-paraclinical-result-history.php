<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/clinical-orders.php';

api_require_method('GET');
$rbac = require_staff_permission([
    'cls.xet_nghiem.perform',
    'cls.cdha.perform',
    'cls.noi_soi.perform',
]);

$maChiTietChiDinh = isset($_GET['maChiTietChiDinh']) ? (int)$_GET['maChiTietChiDinh'] : 0;
if ($maChiTietChiDinh <= 0) {
    api_error('Thiếu mã chi tiết chỉ định.', 400);
}

try {
    $checkStmt = $conn->prepare("SELECT nhomDichVu FROM chi_dinh_chi_tiet WHERE maChiTietChiDinh = ? LIMIT 1");
    $checkStmt->bind_param('i', $maChiTietChiDinh);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$row) {
        api_error('Không tìm thấy chỉ định cận lâm sàng.', 404);
    }

    $requiredPerm = clinical_service_group_permission((string)$row['nhomDichVu']);
    if ($requiredPerm === null || !staff_has_permission($rbac['permissions'], $requiredPerm)) {
        api_error('Bạn không có quyền xem lịch sử chỉ định của nhóm dịch vụ này.', 403, ['code' => 'FORBIDDEN_PERMISSION']);
    }

    $histStmt = $conn->prepare("
        SELECT
            ls.id,
            ls.maKetQua,
            ls.phienBan,
            ls.hanhDong,
            ls.trangThaiCu,
            ls.trangThaiMoi,
            ls.ghiChu,
            ls.actorMaNhanVien,
            nv.tenNhanVien AS actorTenNhanVien,
            ls.actorMaBacSi,
            bs.tenBacSi AS actorTenBacSi,
            ls.createdAt
        FROM ket_qua_lich_su ls
        LEFT JOIN nhanvien nv ON ls.actorMaNhanVien = nv.maNhanVien
        LEFT JOIN bacsi bs ON ls.actorMaBacSi = bs.maBacSi
        WHERE ls.maChiTietChiDinh = ?
        ORDER BY ls.createdAt DESC, ls.id DESC
    ");
    $histStmt->bind_param('i', $maChiTietChiDinh);
    $histStmt->execute();
    $history = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $histStmt->close();

    api_success(['history' => $history], 'Lấy lịch sử kết quả thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải lịch sử kết quả cận lâm sàng.');
}
