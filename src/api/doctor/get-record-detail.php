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
        $row['sangLocDieuDuong'] = null;
        if (!empty($row['maLichKham'])) {
            $stmtScreen = $conn->prepare("
                SELECT
                    sl.maSangLoc,
                    sl.mach,
                    sl.huyetApTamThu,
                    sl.huyetApTamTruong,
                    sl.nhietDo,
                    sl.spo2,
                    sl.nhipTho,
                    sl.canNang,
                    sl.chieuCao,
                    sl.diUng,
                    sl.canhBaoLamSang,
                    sl.ghiChu,
                    sl.trangThai,
                    sl.maNhanVienSangLoc,
                    nv.tenNhanVien AS tenNhanVienSangLoc,
                    sl.thoiGianSangLoc,
                    sl.thoiGianChuyenBacSi
                FROM sang_loc_dieu_duong sl
                LEFT JOIN nhanvien nv ON sl.maNhanVienSangLoc = nv.maNhanVien
                WHERE sl.maLichKham = ?
                ORDER BY sl.thoiGianSangLoc DESC
                LIMIT 1
            ");
            if ($stmtScreen) {
                $stmtScreen->bind_param("i", $row['maLichKham']);
                $stmtScreen->execute();
                $screen = $stmtScreen->get_result()->fetch_assoc();
                $stmtScreen->close();

                if ($screen) {
                    $row['sangLocDieuDuong'] = [
                        'maSangLoc' => (int)$screen['maSangLoc'],
                        'mach' => $screen['mach'] !== null ? (int)$screen['mach'] : null,
                        'huyetApTamThu' => $screen['huyetApTamThu'] !== null ? (int)$screen['huyetApTamThu'] : null,
                        'huyetApTamTruong' => $screen['huyetApTamTruong'] !== null ? (int)$screen['huyetApTamTruong'] : null,
                        'nhietDo' => $screen['nhietDo'] !== null ? (float)$screen['nhietDo'] : null,
                        'spo2' => $screen['spo2'] !== null ? (int)$screen['spo2'] : null,
                        'nhipTho' => $screen['nhipTho'] !== null ? (int)$screen['nhipTho'] : null,
                        'canNang' => $screen['canNang'] !== null ? (float)$screen['canNang'] : null,
                        'chieuCao' => $screen['chieuCao'] !== null ? (float)$screen['chieuCao'] : null,
                        'diUng' => $screen['diUng'] ?? '',
                        'canhBaoLamSang' => $screen['canhBaoLamSang'] ?? '',
                        'ghiChu' => $screen['ghiChu'] ?? '',
                        'trangThai' => $screen['trangThai'],
                        'maNhanVienSangLoc' => $screen['maNhanVienSangLoc'],
                        'tenNhanVienSangLoc' => $screen['tenNhanVienSangLoc'] ?: $screen['maNhanVienSangLoc'],
                        'thoiGianSangLoc' => $screen['thoiGianSangLoc'],
                        'thoiGianChuyenBacSi' => $screen['thoiGianChuyenBacSi'],
                    ];
                }
            }
        }

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
