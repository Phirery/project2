<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

/**
 * Danh sách lịch khám "Đã đặt" hôm nay nhưng CHƯA check-in (mọi bác sĩ),
 * sắp theo giờ hẹn. Dùng cho bảng ở tab Check-in để nhân viên check-in nhanh
 * mà không cần tìm kiếm nếu đã thấy tên trong danh sách.
 */

$today = date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT lk.maLichKham, lk.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh,
               nd.soDienThoai, lk.maBacSi, bs.tenBacSi,
               TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
               TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
               lk.nguon
        FROM lichkham lk
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN hangdoikham hd ON hd.maLichKham = lk.maLichKham
        WHERE lk.ngayKham = ? AND lk.trangThai = 'Đã đặt' AND hd.maHangDoi IS NULL
        ORDER BY sk.gioBatDau ASC
    ");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $data = array_map(function ($r) {
        return [
            'maLichKham' => (int)$r['maLichKham'],
            'maBenhNhan' => $r['maBenhNhan'],
            'tenBenhNhan' => $r['tenBenhNhan'],
            'ngaySinh' => $r['ngaySinh'],
            'gioiTinh' => $r['gioiTinh'],
            'soDienThoai' => $r['soDienThoai'],
            'maBacSi' => $r['maBacSi'],
            'tenBacSi' => $r['tenBacSi'],
            'gioBatDau' => $r['gioBatDau'],
            'gioKetThuc' => $r['gioKetThuc'],
            'nguon' => $r['nguon']
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>