<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('bacsi');

$maHoSo = $_GET['maHoSo'] ?? '';

if (!$maHoSo) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã hồ sơ']);
    exit;
}

try {
    // Verify doctor owns this record
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $sql = "SELECT h.maHoSo, h.maLichKham, h.ngayTao, h.ngayHoanThanh, h.chanDoan, h.dieuTri, h.ghiChu, h.trangThai,
            bn.tenBenhNhan, bn.ngaySinh, bn.gioiTinh,
            l.ngayKham, c.tenCa
            FROM hosobenhan h
            JOIN benhnhan bn ON h.maBenhNhan = bn.maBenhNhan
            LEFT JOIN lichkham l ON h.maLichKham = l.maLichKham
            LEFT JOIN calamviec c ON l.maCa = c.maCa
            WHERE h.maHoSo = ? AND h.maBacSi = ? AND h.isDeleted = 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $maHoSo, $maBacSi);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['toaThuoc'] = [
            'loiDanBacSi' => '',
            'tongTienThuoc' => 0,
            'items' => []
        ];

        if (!empty($row['maLichKham'])) {
            $stmtPrescription = $conn->prepare("
                SELECT maDonThuoc, loiDanBacSi, tongTienThuoc
                FROM donthuoc
                WHERE maLichKham = ?
                ORDER BY ngayKeDon DESC, maDonThuoc DESC
                LIMIT 1
            ");
            $stmtPrescription->bind_param("i", $row['maLichKham']);
            $stmtPrescription->execute();
            $prescription = $stmtPrescription->get_result()->fetch_assoc();
            $stmtPrescription->close();

            if ($prescription) {
                $row['toaThuoc']['maDonThuoc'] = (int)$prescription['maDonThuoc'];
                $row['toaThuoc']['loiDanBacSi'] = $prescription['loiDanBacSi'] ?? '';
                $row['toaThuoc']['tongTienThuoc'] = (float)($prescription['tongTienThuoc'] ?? 0);

                $stmtItems = $conn->prepare("
                    SELECT
                        ct.maThuoc,
                        ct.soLuong,
                        ct.lieuDung,
                        t.tenThuoc,
                        t.donViTinh,
                        t.giaTien,
                        t.cachDungMacDinh,
                        t.loaiThuoc,
                        t.soLuongTon
                    FROM chitietdonthuoc ct
                    JOIN thuoc t ON ct.maThuoc = t.maThuoc
                    WHERE ct.maDonThuoc = ?
                    ORDER BY t.tenThuoc
                ");
                $stmtItems->bind_param("i", $prescription['maDonThuoc']);
                $stmtItems->execute();
                $itemsResult = $stmtItems->get_result();

                while ($item = $itemsResult->fetch_assoc()) {
                    $item['maThuoc'] = (int)$item['maThuoc'];
                    $item['soLuong'] = (int)$item['soLuong'];
                    $item['giaTien'] = (float)($item['giaTien'] ?? 0);
                    $item['thanhTien'] = $item['giaTien'] * $item['soLuong'];
                    $row['toaThuoc']['items'][] = $item;
                }

                $stmtItems->close();
            }
        }

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>
