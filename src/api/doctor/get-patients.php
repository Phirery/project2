<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('bacsi');

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
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            nd.email,
            COUNT(h.maHoSo) AS soLanKham,
            MIN(COALESCE(h.ngayKham, lk.ngayKham, DATE(h.ngayHoanThanh))) AS lanKhamDauTien,
            MAX(COALESCE(h.ngayKham, lk.ngayKham, DATE(h.ngayHoanThanh))) AS lanKhamGanNhat,
            (
                SELECT hs2.maHoSo
                FROM hosobenhan hs2
                LEFT JOIN lichkham lk2 ON hs2.maLichKham = lk2.maLichKham
                WHERE hs2.maBenhNhan = bn.maBenhNhan
                  AND hs2.maBacSi = ?
                  AND hs2.trangThai = 'Đã hoàn thành'
                  AND hs2.isDeleted = 0
                ORDER BY COALESCE(hs2.ngayKham, lk2.ngayKham, DATE(hs2.ngayHoanThanh)) DESC, hs2.ngayHoanThanh DESC, hs2.maHoSo DESC
                LIMIT 1
            ) AS maHoSoGanNhat,
            (
                SELECT hs2.chanDoan
                FROM hosobenhan hs2
                LEFT JOIN lichkham lk2 ON hs2.maLichKham = lk2.maLichKham
                WHERE hs2.maBenhNhan = bn.maBenhNhan
                  AND hs2.maBacSi = ?
                  AND hs2.trangThai = 'Đã hoàn thành'
                  AND hs2.isDeleted = 0
                ORDER BY COALESCE(hs2.ngayKham, lk2.ngayKham, DATE(hs2.ngayHoanThanh)) DESC, hs2.ngayHoanThanh DESC, hs2.maHoSo DESC
                LIMIT 1
            ) AS chanDoanGanNhat
        FROM benhnhan bn
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN hosobenhan h ON bn.maBenhNhan = h.maBenhNhan
        LEFT JOIN lichkham lk ON h.maLichKham = lk.maLichKham
        WHERE h.maBacSi = ?
          AND h.trangThai = 'Đã hoàn thành'
          AND h.isDeleted = 0
          AND nd.isDeleted = 0
        GROUP BY bn.maBenhNhan, bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh, bn.soTheBHYT, nd.soDienThoai, nd.email
        ORDER BY lanKhamGanNhat DESC, bn.tenBenhNhan ASC
    ");
    $stmt->bind_param("sss", $maBacSi, $maBacSi, $maBacSi);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $patients
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
