<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

$maBacSi = trim($_GET['maBacSi'] ?? '');
$today = date('Y-m-d');

try {
    if ($maBacSi === '') {
        // Tổng quan: số người đang chờ/đang khám của từng bác sĩ hôm nay
        $stmt = $conn->prepare("
            SELECT bs.maBacSi, bs.tenBacSi,
                   SUM(CASE WHEN hd.trangThai = 'Đang chờ' THEN 1 ELSE 0 END) AS soDangCho,
                   SUM(CASE WHEN hd.trangThai = 'Đang khám' THEN 1 ELSE 0 END) AS soDangKham
            FROM bacsi bs
            JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            LEFT JOIN hangdoikham hd ON hd.maBacSi = bs.maBacSi AND hd.ngay = ?
            WHERE nd.isDeleted = 0
            GROUP BY bs.maBacSi, bs.tenBacSi
            ORDER BY bs.tenBacSi
        ");
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $data = array_map(function ($r) {
            return [
                'maBacSi' => $r['maBacSi'],
                'tenBacSi' => $r['tenBacSi'],
                'soDangCho' => (int)$r['soDangCho'],
                'soDangKham' => (int)$r['soDangKham']
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chi tiết hàng đợi của 1 bác sĩ hôm nay, sắp theo STT (thứ tự check-in)
    $stmt = $conn->prepare("
        SELECT hd.maHangDoi, hd.soThuTu, hd.trangThai, hd.nguon, hd.thoiGianCheckIn,
               lk.maLichKham, lk.maBenhNhan, bn.tenBenhNhan,
               TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau
        FROM hangdoikham hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE hd.maBacSi = ? AND hd.ngay = ?
        ORDER BY hd.soThuTu ASC
    ");
    $stmt->bind_param('ss', $maBacSi, $today);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $data = array_map(function ($r) {
        return [
            'maHangDoi' => (int)$r['maHangDoi'],
            'soThuTu' => (int)$r['soThuTu'],
            'trangThai' => $r['trangThai'],
            'nguon' => $r['nguon'],
            'thoiGianCheckIn' => $r['thoiGianCheckIn'],
            'maLichKham' => (int)$r['maLichKham'],
            'maBenhNhan' => $r['maBenhNhan'],
            'tenBenhNhan' => $r['tenBenhNhan'],
            'gioHen' => $r['gioBatDau']
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>