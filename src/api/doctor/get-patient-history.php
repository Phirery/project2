<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('bacsi');

if (!isset($_GET['maBenhNhan'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bệnh nhân']);
    exit;
}

$maBenhNhan = $_GET['maBenhNhan'];

try {
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            h.maHoSo,
            COALESCE(h.ngayKham, lk.ngayKham) AS ngayKham,
            h.ngayHoanThanh,
            h.chanDoan,
            h.dieuTri,
            h.ghiChu,
            ca.tenCa,
            gk.tenGoi,
            sk.gioBatDau,
            sk.gioKetThuc,
            (
                SELECT COUNT(*)
                FROM chitietdonthuoc ct
                JOIN donthuoc dt ON dt.maDonThuoc = ct.maDonThuoc
                WHERE dt.maLichKham = h.maLichKham
            ) AS soThuoc,
            (
                SELECT dt.loiDanBacSi
                FROM donthuoc dt
                WHERE dt.maLichKham = h.maLichKham
                ORDER BY dt.ngayKeDon DESC, dt.maDonThuoc DESC
                LIMIT 1
            ) AS loiDanBacSi
        FROM hosobenhan h
        LEFT JOIN lichkham lk ON h.maLichKham = lk.maLichKham
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
        LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE h.maBacSi = ?
          AND h.maBenhNhan = ?
          AND h.trangThai = 'Đã hoàn thành'
          AND h.isDeleted = 0
        ORDER BY COALESCE(h.ngayKham, lk.ngayKham) DESC, h.ngayHoanThanh DESC, h.maHoSo DESC
    ");
    $stmt->bind_param("ss", $maBacSi, $maBenhNhan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
