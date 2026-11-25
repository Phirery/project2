<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/billing-helper.php';

api_require_method('GET');
require_role('benhnhan');

$maHoSo = trim((string)($_GET['maHoSo'] ?? ''));
if ($maHoSo === '') {
    api_error('Thiếu mã hồ sơ cần xem.', 400);
}

try {
    $userId = (int)$_SESSION['id'];
    $bnStmt = $conn->prepare("SELECT maBenhNhan FROM benhnhan WHERE nguoiDungId = ? LIMIT 1");
    $bnStmt->bind_param('i', $userId);
    $bnStmt->execute();
    $bnRow = $bnStmt->get_result()->fetch_assoc();
    $bnStmt->close();

    if (!$bnRow || empty($bnRow['maBenhNhan'])) {
        api_error('Không tìm thấy hồ sơ bệnh nhân của tài khoản.', 404);
    }
    $sessionMaBn = (string)$bnRow['maBenhNhan'];

    $hsStmt = $conn->prepare("
        SELECT
            hs.maHoSo,
            hs.maBenhNhan,
            hs.maLichKham,
            hs.chanDoan,
            hs.dieuTri,
            hs.ghiChu,
            hs.trangThai,
            hs.ngayHoanThanh,
            hs.isDeleted,
            lk.ngayKham,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            ca.tenCa,
            sk.gioBatDau,
            sk.gioKetThuc
        FROM hosobenhan hs
        JOIN lichkham lk ON hs.maLichKham = lk.maLichKham
        JOIN bacsi bs ON hs.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE hs.maHoSo = ?
        LIMIT 1
    ");
    $hsStmt->bind_param('s', $maHoSo);
    $hsStmt->execute();
    $row = $hsStmt->get_result()->fetch_assoc();
    $hsStmt->close();

    if (!$row || (int)$row['isDeleted'] === 1) {
        api_error('Không tìm thấy hồ sơ bệnh án.', 404);
    }

    if ((string)$row['maBenhNhan'] !== $sessionMaBn) {
        api_error('Bạn không có quyền truy cập hồ sơ bệnh án của bệnh nhân khác.', 403);
    }

    if ($row['trangThai'] !== 'Đã hoàn thành') {
        api_error('Hồ sơ đang trong quá trình khám và chưa được bác sĩ hoàn tất.', 403);
    }

    $maLichKham = (int)$row['maLichKham'];

    // Only approved paraclinical results
    $clsStmt = $conn->prepare("
        SELECT
            ct.maChiTietChiDinh,
            dv.tenDichVu,
            dv.nhomDichVu,
            dv.tenNhom,
            kq.maKetQua,
            kq.chiSoKetQua,
            kq.ketLuan,
            kq.nhanXet,
            kq.deNghi,
            kq.tepDinhKem,
            kq.thoiGianTraKetQua,
            kq.thoiGianDuyet,
            nv.tenNhanVien AS tenNhanVienThucHien,
            bs.tenBacSi AS tenBacSiDuyet
        FROM chi_dinh_chi_tiet ct
        JOIN dichvu_can_lam_sang dv ON ct.maDichVu = dv.maDichVu
        JOIN ket_qua_can_lam_sang kq ON kq.maChiTietChiDinh = ct.maChiTietChiDinh
        LEFT JOIN nhanvien nv ON kq.maNhanVienThucHien = nv.maNhanVien
        LEFT JOIN bacsi bs ON kq.maBacSiDuyet = bs.maBacSi
        WHERE ct.maHoSo = ?
          AND ct.trangThai = 'Đã duyệt'
          AND kq.trangThai = 'Đã duyệt'
        ORDER BY ct.maChiTietChiDinh ASC
    ");
    $clsStmt->bind_param('s', $maHoSo);
    $clsStmt->execute();
    $clsRows = $clsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $clsStmt->close();

    $canLamSang = array_map(static function (array $c): array {
        $chiSo = !empty($c['chiSoKetQua']) ? json_decode((string)$c['chiSoKetQua'], true) : null;
        $tep = !empty($c['tepDinhKem']) ? json_decode((string)$c['tepDinhKem'], true) : [];
        return [
            'maChiTietChiDinh' => (int)$c['maChiTietChiDinh'],
            'maKetQua' => (int)$c['maKetQua'],
            'tenDichVu' => $c['tenDichVu'],
            'nhomDichVu' => $c['nhomDichVu'],
            'tenNhom' => $c['tenNhom'],
            'chiSoKetQua' => is_array($chiSo) ? $chiSo : null,
            'ketLuan' => $c['ketLuan'] ?? '',
            'nhanXet' => $c['nhanXet'] ?? '',
            'deNghi' => $c['deNghi'] ?? '',
            'tepDinhKem' => is_array($tep) ? $tep : [],
            'tenNhanVienThucHien' => $c['tenNhanVienThucHien'] ?? '',
            'tenBacSiDuyet' => $c['tenBacSiDuyet'] ?? '',
            'thoiGianTraKetQua' => $c['thoiGianTraKetQua'] ? date('d/m/Y H:i', strtotime($c['thoiGianTraKetQua'])) : null,
            'thoiGianDuyet' => $c['thoiGianDuyet'] ? date('d/m/Y H:i', strtotime($c['thoiGianDuyet'])) : null,
        ];
    }, $clsRows);

    // Prescription + dispensing status
    $rxStmt = $conn->prepare("
        SELECT
            dt.maDonThuoc,
            dt.chuanDoan,
            dt.loiDanBacSi,
            dt.ngayKeDon,
            dt.tongTienThuoc,
            dt.trangThaiPhat,
            t.tenThuoc,
            t.donViTinh,
            t.giaTien,
            ct.soLuong,
            ct.soLuongDaPhat,
            ct.lieuDung
        FROM donthuoc dt
        LEFT JOIN chitietdonthuoc ct ON dt.maDonThuoc = ct.maDonThuoc
        LEFT JOIN thuoc t ON ct.maThuoc = t.maThuoc
        WHERE dt.maLichKham = ?
        ORDER BY dt.maDonThuoc DESC, ct.id ASC
    ");
    $rxStmt->bind_param('i', $maLichKham);
    $rxStmt->execute();
    $rxRows = $rxStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rxStmt->close();

    $donThuoc = [
        'maDonThuoc' => null,
        'trangThaiPhat' => null,
        'chuanDoan' => '',
        'loiDanBacSi' => '',
        'ngayKeDon' => null,
        'tongTienThuoc' => 0,
        'thuoc' => [],
    ];
    foreach ($rxRows as $r) {
        if ($donThuoc['maDonThuoc'] === null) {
            $donThuoc['maDonThuoc'] = (int)$r['maDonThuoc'];
            $donThuoc['trangThaiPhat'] = $r['trangThaiPhat'] ?? 'Chờ phát';
            $donThuoc['chuanDoan'] = $r['chuanDoan'] ?? '';
            $donThuoc['loiDanBacSi'] = $r['loiDanBacSi'] ?? '';
            $donThuoc['ngayKeDon'] = $r['ngayKeDon'] ? date('d/m/Y H:i', strtotime($r['ngayKeDon'])) : null;
            $donThuoc['tongTienThuoc'] = (float)($r['tongTienThuoc'] ?? 0);
        }
        if ((int)$r['maDonThuoc'] !== $donThuoc['maDonThuoc']) {
            continue;
        }
        if (!empty($r['tenThuoc'])) {
            $donThuoc['thuoc'][] = [
                'tenThuoc' => $r['tenThuoc'],
                'soLuong' => (int)($r['soLuong'] ?? 0),
                'soLuongDaPhat' => (int)($r['soLuongDaPhat'] ?? 0),
                'donViTinh' => $r['donViTinh'] ?? '',
                'lieuDung' => $r['lieuDung'] ?? '',
                'giaTien' => (float)($r['giaTien'] ?? 0),
            ];
        }
    }

    $billing = billing_build_encounter_summary($conn, $maLichKham);

    api_success([
        'maHoSo' => $row['maHoSo'],
        'maLichKham' => $maLichKham,
        'ngayKham' => date('d/m/Y', strtotime($row['ngayKham'])),
        'gioKham' => substr($row['gioBatDau'], 0, 5) . ' - ' . substr($row['gioKetThuc'], 0, 5),
        'tenCa' => $row['tenCa'],
        'tenBacSi' => $row['tenBacSi'],
        'tenChuyenKhoa' => $row['tenChuyenKhoa'] ?: 'Đa khoa',
        'chanDoan' => $row['chanDoan'] ?? '',
        'dieuTri' => $row['dieuTri'] ?? '',
        'ghiChu' => $row['ghiChu'] ?? '',
        'ngayHoanThanh' => $row['ngayHoanThanh'] ? date('d/m/Y H:i', strtotime($row['ngayHoanThanh'])) : null,
        'canLamSang' => $canLamSang,
        'donThuoc' => $donThuoc,
        'hoaDon' => $billing ? [
            'maLichKham' => $billing['maLichKham'],
            'billingStatus' => $billing['billingStatus'],
            'totals' => $billing['totals'],
            'groups' => $billing['groups'],
            'items' => $billing['items'],
            'invoices' => $billing['invoices'],
        ] : null,
    ], 'Lấy chi tiết hồ sơ bệnh án thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải chi tiết hồ sơ bệnh án.');
}
