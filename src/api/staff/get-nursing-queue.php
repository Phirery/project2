<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/api-helper.php';
require_once '../../includes/staff-permission.php';

api_require_method('GET');
require_staff_permission('nursing.screen');

$dateParam = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    api_error('Định dạng ngày không hợp lệ (YYYY-MM-DD).', 400);
}

$tab = trim((string)($_GET['tab'] ?? 'pending'));
if (!in_array($tab, ['pending', 'transferred'], true)) {
    $tab = 'pending';
}

$search = trim((string)($_GET['search'] ?? ''));
$maBacSi = trim((string)($_GET['maBacSi'] ?? ''));

try {
    $statsStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN hd.trangThai = 'Đang chờ' AND (sl.maSangLoc IS NULL OR sl.trangThai <> 'Đã chuyển bác sĩ') THEN 1 ELSE 0 END) AS choSangLoc,
            SUM(CASE WHEN hd.trangThai = 'Đang chờ' AND sl.trangThai = 'Đã sàng lọc' THEN 1 ELSE 0 END) AS daLuuTam,
            SUM(CASE WHEN sl.trangThai = 'Đã chuyển bác sĩ' THEN 1 ELSE 0 END) AS daChuyenHomNay
        FROM hangdoikham hd
        LEFT JOIN sang_loc_dieu_duong sl ON sl.maHangDoi = hd.maHangDoi
        WHERE hd.ngay = ?
    ");
    if (!$statsStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn thống kê điều dưỡng');
    }
    $statsStmt->bind_param('s', $dateParam);
    $statsStmt->execute();
    $statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
    $statsStmt->close();

    $where = " WHERE hd.ngay = ? ";
    $types = 's';
    $params = [$dateParam];

    if ($tab === 'pending') {
        $where .= " AND hd.trangThai = 'Đang chờ' AND (sl.maSangLoc IS NULL OR sl.trangThai <> 'Đã chuyển bác sĩ') ";
    } else {
        $where .= " AND sl.trangThai = 'Đã chuyển bác sĩ' ";
    }

    if ($maBacSi !== '') {
        $where .= " AND hd.maBacSi = ? ";
        $types .= 's';
        $params[] = $maBacSi;
    }

    if ($search !== '') {
        $where .= " AND (bn.tenBenhNhan LIKE ? OR bn.maBenhNhan LIKE ? OR nd.soDienThoai LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT
            hd.maHangDoi,
            hd.maLichKham,
            hd.maBacSi,
            hd.ngay,
            hd.soThuTu,
            hd.thoiGianCheckIn,
            hd.trangThai AS trangThaiHangDoi,
            hd.nguon,
            bn.maBenhNhan,
            bn.tenBenhNhan,
            bn.ngaySinh,
            bn.gioiTinh,
            bn.soTheBHYT,
            nd.soDienThoai,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            ca.tenCa,
            TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
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
            sl.ghiChu AS ghiChuSangLoc,
            sl.trangThai AS trangThaiSangLoc,
            sl.maNhanVienSangLoc,
            nv.tenNhanVien AS tenNhanVienSangLoc,
            sl.thoiGianSangLoc,
            sl.thoiGianChuyenBacSi
        FROM hangdoikham hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
        JOIN nguoidung nd ON bn.nguoiDungId = nd.id
        JOIN bacsi bs ON hd.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN sang_loc_dieu_duong sl ON sl.maHangDoi = hd.maHangDoi
        LEFT JOIN nhanvien nv ON sl.maNhanVienSangLoc = nv.maNhanVien
        $where
        ORDER BY hd.thoiGianCheckIn ASC, hd.soThuTu ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn hàng đợi điều dưỡng');
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $items = array_map(static function (array $r): array {
        return [
            'maHangDoi' => (int)$r['maHangDoi'],
            'maLichKham' => (int)$r['maLichKham'],
            'maBacSi' => $r['maBacSi'],
            'tenBacSi' => $r['tenBacSi'],
            'tenChuyenKhoa' => $r['tenChuyenKhoa'] ?: 'Đa khoa',
            'ngay' => $r['ngay'],
            'soThuTu' => (int)$r['soThuTu'],
            'thoiGianCheckIn' => $r['thoiGianCheckIn'],
            'trangThaiHangDoi' => $r['trangThaiHangDoi'],
            'nguon' => $r['nguon'],
            'tenCa' => $r['tenCa'],
            'gioHen' => trim(($r['gioBatDau'] ?? '') . ($r['gioKetThuc'] ? ' - ' . $r['gioKetThuc'] : '')),
            'maBenhNhan' => $r['maBenhNhan'],
            'tenBenhNhan' => $r['tenBenhNhan'],
            'ngaySinh' => $r['ngaySinh'],
            'gioiTinh' => $r['gioiTinh'],
            'soTheBHYT' => $r['soTheBHYT'],
            'soDienThoai' => $r['soDienThoai'],
            'sangLoc' => $r['maSangLoc'] !== null ? [
                'maSangLoc' => (int)$r['maSangLoc'],
                'mach' => $r['mach'] !== null ? (int)$r['mach'] : null,
                'huyetApTamThu' => $r['huyetApTamThu'] !== null ? (int)$r['huyetApTamThu'] : null,
                'huyetApTamTruong' => $r['huyetApTamTruong'] !== null ? (int)$r['huyetApTamTruong'] : null,
                'nhietDo' => $r['nhietDo'] !== null ? (float)$r['nhietDo'] : null,
                'spo2' => $r['spo2'] !== null ? (int)$r['spo2'] : null,
                'nhipTho' => $r['nhipTho'] !== null ? (int)$r['nhipTho'] : null,
                'canNang' => $r['canNang'] !== null ? (float)$r['canNang'] : null,
                'chieuCao' => $r['chieuCao'] !== null ? (float)$r['chieuCao'] : null,
                'diUng' => $r['diUng'] ?? '',
                'canhBaoLamSang' => $r['canhBaoLamSang'] ?? '',
                'ghiChu' => $r['ghiChuSangLoc'] ?? '',
                'trangThai' => $r['trangThaiSangLoc'],
                'maNhanVienSangLoc' => $r['maNhanVienSangLoc'],
                'tenNhanVienSangLoc' => $r['tenNhanVienSangLoc'] ?: $r['maNhanVienSangLoc'],
                'thoiGianSangLoc' => $r['thoiGianSangLoc'],
                'thoiGianChuyenBacSi' => $r['thoiGianChuyenBacSi'],
            ] : null,
        ];
    }, $rows);

    $docStmt = $conn->prepare("
        SELECT DISTINCT bs.maBacSi, bs.tenBacSi
        FROM hangdoikham hd
        JOIN bacsi bs ON hd.maBacSi = bs.maBacSi
        WHERE hd.ngay = ?
        ORDER BY bs.tenBacSi ASC
    ");
    $doctors = [];
    if ($docStmt) {
        $docStmt->bind_param('s', $dateParam);
        $docStmt->execute();
        $doctors = $docStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $docStmt->close();
    }

    api_success([
        'date' => $dateParam,
        'tab' => $tab,
        'stats' => [
            'choSangLoc' => (int)($statsRow['choSangLoc'] ?? 0),
            'daLuuTam' => (int)($statsRow['daLuuTam'] ?? 0),
            'daChuyenHomNay' => (int)($statsRow['daChuyenHomNay'] ?? 0),
        ],
        'doctors' => $doctors,
        'items' => $items,
    ], 'Lấy danh sách sàng lọc điều dưỡng thành công.');
} catch (Throwable $e) {
    api_server_error($e, 'Không thể tải danh sách bệnh nhân điều dưỡng.');
}
