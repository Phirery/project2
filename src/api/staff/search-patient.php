<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

/**
 * Tra cứu bệnh nhân theo mã BN (nhập tay hoặc quét QR) hoặc số điện thoại.
 * Trả kèm lịch khám "Đã đặt" của hôm nay (nếu có) và trạng thái đã check-in hay chưa,
 * để trang check-in quyết định luồng B (chưa có lịch) hay C (đã có lịch).
 */

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã bệnh nhân hoặc số điện thoại'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT bn.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh, bn.soTheBHYT,
               nd.id AS nguoiDungId, nd.soDienThoai, nd.email, nd.taiKhoanTamThoi, nd.isDeleted
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        WHERE bn.maBenhNhan = ? OR nd.soDienThoai = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $q, $q);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient || (int)$patient['isDeleted'] === 1) {
        echo json_encode(['success' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $today = date('Y-m-d');

    // Tìm lịch "Đã đặt" hôm nay của bệnh nhân này (mới nhất trước)
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
    $apptStmt->bind_param('ss', $patient['maBenhNhan'], $today);
    $apptStmt->execute();
    $appointments = $apptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $apptStmt->close();

    echo json_encode([
        'success' => true,
        'found' => true,
        'patient' => [
            'maBenhNhan' => $patient['maBenhNhan'],
            'tenBenhNhan' => $patient['tenBenhNhan'],
            'ngaySinh' => $patient['ngaySinh'],
            'gioiTinh' => $patient['gioiTinh'],
            'soTheBHYT' => $patient['soTheBHYT'],
            'soDienThoai' => $patient['soDienThoai'],
            'email' => $patient['email'],
            'taiKhoanTamThoi' => (bool)$patient['taiKhoanTamThoi']
        ],
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
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>