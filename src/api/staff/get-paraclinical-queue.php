<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';
require_once '../../includes/clinical-orders.php';

api_require_method('GET');

$nhom = trim((string)($_GET['nhom'] ?? ''));
$requiredPerm = clinical_service_group_permission($nhom);
if ($requiredPerm === null) {
    api_error('Nhóm dịch vụ cận lâm sàng không hợp lệ.', 400);
}

require_staff_permission($requiredPerm);

$dateParam = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    api_error('Định dạng ngày không hợp lệ (YYYY-MM-DD).', 400);
}

$statusFilter = trim((string)($_GET['status'] ?? 'active'));
$search = trim((string)($_GET['search'] ?? ''));

try {
    $statsStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN hq.trangThai = 'Đang chờ' AND ct.trangThai <> 'Hủy' THEN 1 ELSE 0 END) AS choThucHien,
            SUM(CASE WHEN hq.trangThai = 'Đang thực hiện' AND ct.trangThai <> 'Hủy' THEN 1 ELSE 0 END) AS dangThucHien,
            SUM(CASE WHEN ct.trangThai = 'Có kết quả' THEN 1 ELSE 0 END) AS choDuyet,
            SUM(CASE WHEN ct.trangThai = 'Đã duyệt' THEN 1 ELSE 0 END) AS daDuyet,
            SUM(CASE WHEN hq.trangThai = 'Bỏ lỡ' AND ct.trangThai <> 'Hủy' THEN 1 ELSE 0 END) AS boLo
        FROM hangdoi_dichvu hq
        JOIN chi_dinh_chi_tiet ct ON hq.maChiTietChiDinh = ct.maChiTietChiDinh
        WHERE hq.nhomDichVu = ? AND hq.ngay = ?
    ");
    $statsStmt->bind_param('ss', $nhom, $dateParam);
    $statsStmt->execute();
    $statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    $where = " WHERE hq.nhomDichVu = ? AND hq.ngay = ? ";
    $types = 'ss';
    $params = [$nhom, $dateParam];

    if ($statusFilter === 'active') {
        $where .= " AND hq.trangThai IN ('Đang chờ', 'Đang thực hiện', 'Bỏ lỡ') AND ct.trangThai <> 'Hủy' ";
    } elseif ($statusFilter === 'completed') {
        $where .= " AND hq.trangThai = 'Hoàn thành' AND ct.trangThai <> 'Hủy' ";
    } elseif ($statusFilter === 'cancelled') {
        $where .= " AND (hq.trangThai = 'Hủy' OR ct.trangThai = 'Hủy') ";
    } elseif (in_array($statusFilter, ['Đang chờ', 'Đang thực hiện', 'Hoàn thành', 'Bỏ lỡ', 'Hủy'], true)) {
        $where .= " AND hq.trangThai = ? ";
        $types .= 's';
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        $where .= " AND (bn.tenBenhNhan LIKE ? OR bn.maBenhNhan LIKE ? OR cd.maPhieuChiDinh LIKE ? OR dv.tenDichVu LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT
            hq.maHangDoiDichVu,
            hq.maChiTietChiDinh,
            hq.maChiDinh,
            hq.maHoSo,
            hq.maBenhNhan,
            hq.maDichVu,
            hq.nhomDichVu,
            hq.ngay,
            hq.soThuTu,
            hq.doUuTien,
            hq.trangThai AS trangThaiHangDoi,
            hq.thoiGianVaoHangDoi,
            hq.thoiGianGoi,
            hq.thoiGianBatDau,
            hq.thoiGianHoanThanh,
            hq.ghiChu AS ghiChuHangDoi,
            ct.trangThai AS trangThaiChiDinh,
            ct.batBuoc,
            ct.donGia,
            ct.ghiChu AS ghiChuChiTiet,
            ct.lyDoHuy,
            cd.maPhieuChiDinh,
            cd.ghiChuLamSang,
            cd.createdAt AS ngayChiDinh,
            bsCd.tenBacSi AS tenBacSiChiDinh,
            dv.maCode,
            dv.tenDichVu,
            dv.tenNhom,
            dv.donViTinh,
            dv.chuanBiTruocKhiLam,
            dv.mauKetQuaMacDinh,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            nd.soDienThoai,
            nvTh.tenNhanVien AS tenNhanVienThucHien,
            kq.maKetQua,
            kq.trangThai AS trangThaiKetQua,
            kq.chiSoKetQua,
            kq.ketLuan,
            kq.nhanXet,
            kq.deNghi,
            kq.tepDinhKem,
            kq.thoiGianTraKetQua,
            kq.thoiGianDuyet,
            kq.lyDoYeuCauLamLai,
            kq.phienBan
        FROM hangdoi_dichvu hq
        JOIN chi_dinh_chi_tiet ct ON hq.maChiTietChiDinh = ct.maChiTietChiDinh
        JOIN chi_dinh_can_lam_sang cd ON hq.maChiDinh = cd.maChiDinh
        JOIN dichvu_can_lam_sang dv ON hq.maDichVu = dv.maDichVu
        JOIN benhnhan bn ON hq.maBenhNhan = bn.maBenhNhan
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        LEFT JOIN bacsi bsCd ON cd.maBacSiChiDinh = bsCd.maBacSi
        LEFT JOIN nhanvien nvTh ON hq.maNhanVienThucHien = nvTh.maNhanVien
        LEFT JOIN ket_qua_can_lam_sang kq ON kq.maChiTietChiDinh = ct.maChiTietChiDinh
        $where
        ORDER BY FIELD(hq.doUuTien, 'Khẩn', 'Ưu tiên', 'Bình thường'), hq.soThuTu ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn hàng đợi cận lâm sàng');
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $items = array_map(static function (array $r): array {
        $template = null;
        if (!empty($r['mauKetQuaMacDinh'])) {
            $decoded = json_decode((string)$r['mauKetQuaMacDinh'], true);
            $template = is_array($decoded) ? $decoded : null;
        }
        $chiSo = null;
        if (!empty($r['chiSoKetQua'])) {
            $decoded = json_decode((string)$r['chiSoKetQua'], true);
            $chiSo = is_array($decoded) ? $decoded : null;
        }
        $attachments = [];
        if (!empty($r['tepDinhKem'])) {
            $decoded = json_decode((string)$r['tepDinhKem'], true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        return [
            'maHangDoiDichVu' => (int)$r['maHangDoiDichVu'],
            'maChiTietChiDinh' => (int)$r['maChiTietChiDinh'],
            'maChiDinh' => (int)$r['maChiDinh'],
            'maPhieuChiDinh' => $r['maPhieuChiDinh'],
            'maHoSo' => $r['maHoSo'],
            'ngay' => $r['ngay'],
            'soThuTu' => (int)$r['soThuTu'],
            'doUuTien' => $r['doUuTien'],
            'trangThaiHangDoi' => $r['trangThaiHangDoi'],
            'trangThaiChiDinh' => $r['trangThaiChiDinh'],
            'batBuoc' => (bool)$r['batBuoc'],
            'donGia' => (float)$r['donGia'],
            'ghiChuChiTiet' => $r['ghiChuChiTiet'] ?? '',
            'ghiChuLamSang' => $r['ghiChuLamSang'] ?? '',
            'lyDoHuy' => $r['lyDoHuy'] ?? '',
            'ngayChiDinh' => $r['ngayChiDinh'],
            'tenBacSiChiDinh' => $r['tenBacSiChiDinh'] ?: 'Bác sĩ',
            'thoiGianVaoHangDoi' => $r['thoiGianVaoHangDoi'],
            'thoiGianBatDau' => $r['thoiGianBatDau'],
            'thoiGianHoanThanh' => $r['thoiGianHoanThanh'],
            'tenNhanVienThucHien' => $r['tenNhanVienThucHien'] ?? '',
            'dichVu' => [
                'maDichVu' => (int)$r['maDichVu'],
                'maCode' => $r['maCode'],
                'tenDichVu' => $r['tenDichVu'],
                'nhomDichVu' => $r['nhomDichVu'],
                'tenNhom' => $r['tenNhom'],
                'donViTinh' => $r['donViTinh'],
                'chuanBiTruocKhiLam' => $r['chuanBiTruocKhiLam'] ?? '',
                'mauKetQuaMacDinh' => $template,
            ],
            'benhNhan' => [
                'maBenhNhan' => $r['maBenhNhan'],
                'tenBenhNhan' => $r['tenBenhNhan'],
                'ngaySinh' => $r['ngaySinh'],
                'gioiTinh' => $r['gioiTinh'],
                'soDienThoai' => $r['soDienThoai'],
            ],
            'ketQua' => $r['maKetQua'] !== null ? [
                'maKetQua' => (int)$r['maKetQua'],
                'trangThai' => $r['trangThaiKetQua'],
                'chiSoKetQua' => $chiSo,
                'ketLuan' => $r['ketLuan'] ?? '',
                'nhanXet' => $r['nhanXet'] ?? '',
                'deNghi' => $r['deNghi'] ?? '',
                'tepDinhKem' => $attachments,
                'thoiGianTraKetQua' => $r['thoiGianTraKetQua'],
                'thoiGianDuyet' => $r['thoiGianDuyet'],
                'lyDoYeuCauLamLai' => $r['lyDoYeuCauLamLai'] ?? '',
                'phienBan' => (int)$r['phienBan'],
            ] : null,
        ];
    }, $rows);

    api_success([
        'date' => $dateParam,
        'group' => CLINICAL_SERVICE_GROUPS[$nhom],
        'stats' => [
            'choThucHien' => (int)($statsRow['choThucHien'] ?? 0),
            'dangThucHien' => (int)($statsRow['dangThucHien'] ?? 0),
            'choDuyet' => (int)($statsRow['choDuyet'] ?? 0),
            'daDuyet' => (int)($statsRow['daDuyet'] ?? 0),
            'boLo' => (int)($statsRow['boLo'] ?? 0),
        ],
        'items' => $items,
    ], 'Tải hàng đợi cận lâm sàng thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải hàng đợi dịch vụ cận lâm sàng.');
}
