<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

/**
 * Tra cứu bệnh nhân theo mã BN / SĐT (khớp chính xác) hoặc theo tên / mã thẻ
 * BHYT (khớp gần đúng - LIKE). Trả về DANH SÁCH kết quả (có thể nhiều người
 * trùng tên) kèm lịch khám "Đã đặt" hôm nay của từng người, để trang check-in
 * cho nhân viên chọn đúng người trước khi xử lý Case A/B/C.
 */

$q = trim($_GET['q'] ?? '');
$LIMIT = 20;

if ($q === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập từ khóa tìm kiếm'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $like = '%' . $q . '%';

    $stmt = $conn->prepare("
        SELECT bn.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh, bn.soTheBHYT,
               nd.id AS nguoiDungId, nd.soDienThoai, nd.email, nd.taiKhoanTamThoi, nd.isDeleted
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        WHERE nd.isDeleted = 0
          AND (
                bn.maBenhNhan = ?
             OR nd.soDienThoai = ?
             OR bn.tenBenhNhan LIKE ?
             OR bn.soTheBHYT LIKE ?
          )
        ORDER BY bn.tenBenhNhan ASC
        LIMIT $LIMIT
    ");
    $stmt->bind_param('ssss', $q, $q, $like, $like);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($patients)) {
        echo json_encode(['success' => true, 'patients' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $today = date('Y-m-d');

    $apptStmt = $conn->prepare("
        SELECT lk.maLichKham, lk.maBacSi, bs.tenBacSi, lk.maCa, c.tenCa,
               TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
               TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
               lk.trangThai,
               hd.maHangDoi, hd.soThuTu, hd.trangThai AS trangThaiHangDoi
        FROM lichkham lk
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        JOIN calamviec c ON lk.maCa = c.maCa
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN hangdoikham hd ON hd.maLichKham = lk.maLichKham
        WHERE lk.maBenhNhan = ? AND lk.ngayKham = ? AND lk.trangThai = 'Đã đặt'
        ORDER BY sk.gioBatDau ASC
    ");

    $result = [];
    foreach ($patients as $patient) {
        $apptStmt->bind_param('ss', $patient['maBenhNhan'], $today);
        $apptStmt->execute();
        $appointments = $apptStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $result[] = [
            'maBenhNhan' => $patient['maBenhNhan'],
            'tenBenhNhan' => $patient['tenBenhNhan'],
            'ngaySinh' => $patient['ngaySinh'],
            'gioiTinh' => $patient['gioiTinh'],
            'soTheBHYT' => $patient['soTheBHYT'],
            'soDienThoai' => $patient['soDienThoai'],
            'email' => $patient['email'],
            'taiKhoanTamThoi' => (bool)$patient['taiKhoanTamThoi'],
            'appointmentsToday' => array_map(function ($a) {
                return [
                    'maLichKham' => (int)$a['maLichKham'],
                    'maBacSi' => $a['maBacSi'],
                    'tenBacSi' => $a['tenBacSi'],
                    'maCa' => (int)$a['maCa'],
                    'tenCa' => $a['tenCa'],
                    'gioBatDau' => $a['gioBatDau'],
                    'gioKetThuc' => $a['gioKetThuc'],
                    'daCheckIn' => $a['maHangDoi'] !== null,
                    'soThuTu' => $a['soThuTu'] !== null ? (int)$a['soThuTu'] : null,
                    'trangThaiHangDoi' => $a['trangThaiHangDoi']
                ];
            }, $appointments)
        ];
    }
    $apptStmt->close();

    echo json_encode(['success' => true, 'patients' => $result], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>