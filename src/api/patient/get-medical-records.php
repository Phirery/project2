<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';

require_role('benhnhan');

function getPatientCode($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT maBenhNhan
        FROM benhnhan
        WHERE nguoiDungId = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn bệnh nhân');
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    $stmt->close();

    if (!$patient || empty($patient['maBenhNhan'])) {
        throw new Exception('Không tìm thấy thông tin bệnh nhân');
    }

    return $patient['maBenhNhan'];
}

function baseRecordJoinSql()
{
    return "
        FROM hosobenhan hs
        JOIN lichkham lk ON hs.maLichKham = lk.maLichKham
        JOIN bacsi bs ON hs.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
    ";
}

function buildRecordFilterClause($maBenhNhan, $search, $doctor, $date, &$params, &$types)
{
    $where = "
        WHERE hs.maBenhNhan = ?
          AND hs.trangThai = 'Đã hoàn thành'
          AND hs.isDeleted = 0
    ";
    $params[] = $maBenhNhan;
    $types .= 's';

    if ($search !== '') {
        $where .= " AND (hs.chanDoan LIKE ? OR bs.tenBacSi LIKE ? OR ck.tenChuyenKhoa LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }

    if ($doctor !== '') {
        $where .= " AND bs.tenBacSi = ?";
        $params[] = $doctor;
        $types .= 's';
    }

    if ($date !== '') {
        $where .= " AND lk.ngayKham = ?";
        $params[] = $date;
        $types .= 's';
    }

    return $where;
}

function recordOrderSql()
{
    return " ORDER BY lk.ngayKham DESC, sk.gioBatDau DESC, hs.maHoSo DESC";
}

function mapRecordRow($row)
{
    return [
        'maHoSo' => $row['maHoSo'],
        'maLichKham' => (int)$row['maLichKham'],
        'ngayKham' => date('d/m/Y', strtotime($row['ngayKham'])),
        'ngayKhamRaw' => $row['ngayKham'],
        'gioKham' => substr($row['gioBatDau'], 0, 5) . ' - ' . substr($row['gioKetThuc'], 0, 5),
        'tenCa' => $row['tenCa'],
        'tenBacSi' => $row['tenBacSi'],
        'tenChuyenKhoa' => $row['tenChuyenKhoa'] ?: 'Đa khoa',
        'chanDoan' => $row['chanDoan'] ?? '',
        'dieuTri' => $row['dieuTri'] ?? '',
        'ghiChu' => $row['ghiChu'] ?? '',
        'ngayHoanThanh' => $row['ngayHoanThanh'] ? date('d/m/Y H:i', strtotime($row['ngayHoanThanh'])) : null
    ];
}

function fetchDoctorsForFilter($conn, $maBenhNhan)
{
    $stmt = $conn->prepare("
        SELECT DISTINCT bs.tenBacSi
        " . baseRecordJoinSql() . "
        WHERE hs.maBenhNhan = ?
          AND hs.trangThai = 'Đã hoàn thành'
          AND hs.isDeleted = 0
        ORDER BY bs.tenBacSi ASC
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn danh sách bác sĩ');
    }
    $stmt->bind_param("s", $maBenhNhan);
    $stmt->execute();
    $result = $stmt->get_result();

    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row['tenBacSi'];
    }
    $stmt->close();

    return $doctors;
}

function fetchTotalRecords($conn, $maBenhNhan)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM hosobenhan hs
        WHERE hs.maBenhNhan = ?
          AND hs.trangThai = 'Đã hoàn thành'
          AND hs.isDeleted = 0
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn tổng hồ sơ');
    }
    $stmt->bind_param("s", $maBenhNhan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function fetchLatestRecord($conn, $maBenhNhan)
{
    $stmt = $conn->prepare("
        SELECT
            hs.maHoSo,
            hs.maLichKham,
            hs.chanDoan,
            hs.dieuTri,
            hs.ghiChu,
            hs.ngayHoanThanh,
            lk.ngayKham,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            ca.tenCa,
            sk.gioBatDau,
            sk.gioKetThuc
        " . baseRecordJoinSql() . "
        WHERE hs.maBenhNhan = ?
          AND hs.trangThai = 'Đã hoàn thành'
          AND hs.isDeleted = 0
        " . recordOrderSql() . "
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn hồ sơ mới nhất');
    }
    $stmt->bind_param("s", $maBenhNhan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) return null;
    return mapRecordRow($row);
}

function fetchPrescriptionMap($conn, $appointmentIds)
{
    if (empty($appointmentIds)) return [];

    $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
    $types = str_repeat('i', count($appointmentIds));

    $sql = "
        SELECT
            dt.maLichKham,
            dt.maDonThuoc,
            dt.chuanDoan,
            dt.loiDanBacSi,
            dt.ngayKeDon,
            dt.tongTienThuoc,
            ct.id AS chiTietId,
            ct.soLuong,
            ct.lieuDung,
            t.tenThuoc,
            t.donViTinh,
            t.giaTien
        FROM donthuoc dt
        LEFT JOIN chitietdonthuoc ct ON dt.maDonThuoc = ct.maDonThuoc
        LEFT JOIN thuoc t ON ct.maThuoc = t.maThuoc
        WHERE dt.maLichKham IN ($placeholders)
        ORDER BY dt.maLichKham ASC, dt.ngayKeDon DESC, dt.maDonThuoc DESC, ct.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn đơn thuốc');
    }
    $stmt->bind_param($types, ...$appointmentIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    while ($row = $result->fetch_assoc()) {
        $maLichKham = (int)$row['maLichKham'];
        $maDonThuoc = (int)$row['maDonThuoc'];

        if (!isset($map[$maLichKham])) {
            $map[$maLichKham] = [
                'selectedDonThuocId' => $maDonThuoc,
                'chuanDoan' => $row['chuanDoan'] ?? '',
                'loiDanBacSi' => $row['loiDanBacSi'] ?? '',
                'ngayKeDon' => $row['ngayKeDon'] ? date('d/m/Y H:i', strtotime($row['ngayKeDon'])) : null,
                'tongTienThuoc' => (float)($row['tongTienThuoc'] ?? 0),
                'thuoc' => []
            ];
        }

        // Chỉ lấy đơn mới nhất cho mỗi lịch khám
        if ($map[$maLichKham]['selectedDonThuocId'] !== $maDonThuoc) {
            continue;
        }

        if (!empty($row['tenThuoc'])) {
            $map[$maLichKham]['thuoc'][] = [
                'tenThuoc' => $row['tenThuoc'],
                'soLuong' => (int)($row['soLuong'] ?? 0),
                'donViTinh' => $row['donViTinh'] ?? '',
                'lieuDung' => $row['lieuDung'] ?? '',
                'giaTien' => (float)($row['giaTien'] ?? 0)
            ];
        }
    }
    $stmt->close();

    foreach ($map as $maLichKham => $donThuoc) {
        if ($donThuoc['tongTienThuoc'] <= 0 && !empty($donThuoc['thuoc'])) {
            $total = 0;
            foreach ($donThuoc['thuoc'] as $item) {
                $total += ((int)$item['soLuong']) * ((float)$item['giaTien']);
            }
            $map[$maLichKham]['tongTienThuoc'] = $total;
        }

        unset($map[$maLichKham]['selectedDonThuocId']);
    }

    return $map;
}

function attachPrescriptionData($conn, $records, $latest)
{
    $appointmentIds = [];
    foreach ($records as $record) {
        $appointmentIds[] = (int)$record['maLichKham'];
    }
    if ($latest) {
        $appointmentIds[] = (int)$latest['maLichKham'];
    }
    $appointmentIds = array_values(array_unique(array_filter($appointmentIds, function ($id) {
        return $id > 0;
    })));

    $prescriptionMap = fetchPrescriptionMap($conn, $appointmentIds);

    foreach ($records as &$record) {
        $record['donThuoc'] = $prescriptionMap[(int)$record['maLichKham']] ?? [
            'chuanDoan' => '',
            'loiDanBacSi' => '',
            'ngayKeDon' => null,
            'tongTienThuoc' => 0,
            'thuoc' => []
        ];
    }
    unset($record);

    if ($latest) {
        $latest['donThuoc'] = $prescriptionMap[(int)$latest['maLichKham']] ?? [
            'chuanDoan' => '',
            'loiDanBacSi' => '',
            'ngayKeDon' => null,
            'tongTienThuoc' => 0,
            'thuoc' => []
        ];
    }

    return [$records, $latest];
}

try {
    $maBenhNhan = getPatientCode($conn, (int)$_SESSION['id']);
    $search = trim($_GET['search'] ?? '');
    $doctor = trim($_GET['doctor'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min((int)($_GET['limit'] ?? 10), 50));

    $overviewTotal = fetchTotalRecords($conn, $maBenhNhan);
    $latest = $overviewTotal > 0 ? fetchLatestRecord($conn, $maBenhNhan) : null;
    $doctorFilters = $overviewTotal > 0 ? fetchDoctorsForFilter($conn, $maBenhNhan) : [];

    // Count theo bộ lọc
    $countParams = [];
    $countTypes = '';
    $whereClause = buildRecordFilterClause($maBenhNhan, $search, $doctor, $date, $countParams, $countTypes);
    $countSql = "SELECT COUNT(*) AS total " . baseRecordJoinSql() . $whereClause;
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn đếm hồ sơ');
    }
    if ($countTypes !== '') {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $filteredTotal = (int)($countRow['total'] ?? 0);
    $countStmt->close();

    $totalPages = $filteredTotal > 0 ? (int)ceil($filteredTotal / $limit) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    $records = [];
    if ($filteredTotal > 0) {
        // Deferred join: lấy ID trước
        $idsParams = $countParams;
        $idsTypes = $countTypes . 'ii';
        $idsParams[] = $limit;
        $idsParams[] = $offset;

        $idsSql = "
            SELECT hs.maHoSo
            " . baseRecordJoinSql() . "
            " . $whereClause . "
            " . recordOrderSql() . "
            LIMIT ? OFFSET ?
        ";
        $idsStmt = $conn->prepare($idsSql);
        if (!$idsStmt) {
            throw new Exception('Không thể chuẩn bị truy vấn phân trang hồ sơ');
        }
        $idsStmt->bind_param($idsTypes, ...$idsParams);
        $idsStmt->execute();
        $idsResult = $idsStmt->get_result();

        $recordIds = [];
        while ($row = $idsResult->fetch_assoc()) {
            $recordIds[] = $row['maHoSo'];
        }
        $idsStmt->close();

        if (!empty($recordIds)) {
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $detailTypes = str_repeat('s', count($recordIds));

            $detailSql = "
                SELECT
                    hs.maHoSo,
                    hs.maLichKham,
                    hs.chanDoan,
                    hs.dieuTri,
                    hs.ghiChu,
                    hs.ngayHoanThanh,
                    lk.ngayKham,
                    bs.tenBacSi,
                    ck.tenChuyenKhoa,
                    ca.tenCa,
                    sk.gioBatDau,
                    sk.gioKetThuc
                " . baseRecordJoinSql() . "
                WHERE hs.maHoSo IN ($placeholders)
                " . recordOrderSql();

            $detailStmt = $conn->prepare($detailSql);
            if (!$detailStmt) {
                throw new Exception('Không thể chuẩn bị truy vấn chi tiết hồ sơ');
            }
            $detailStmt->bind_param($detailTypes, ...$recordIds);
            $detailStmt->execute();
            $detailResult = $detailStmt->get_result();

            while ($row = $detailResult->fetch_assoc()) {
                $records[] = mapRecordRow($row);
            }
            $detailStmt->close();
        }
    }

    list($records, $latest) = attachPrescriptionData($conn, $records, $latest);

    echo json_encode([
        'success' => true,
        'latest' => $latest,
        'records' => $records,
        'filters' => [
            'doctors' => $doctorFilters
        ],
        'pagination' => [
            'mode' => 'deferred',
            'total' => $filteredTotal,
            'page' => $filteredTotal > 0 ? $page : 1,
            'limit' => $limit,
            'offset' => $filteredTotal > 0 ? $offset : 0,
            'totalPages' => $totalPages
        ],
        'overview' => [
            'totalRecords' => $overviewTotal
        ],
        'message' => 'Lấy dữ liệu thành công'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'latest' => null,
        'records' => [],
        'filters' => ['doctors' => []],
        'pagination' => [
            'mode' => 'deferred',
            'total' => 0,
            'page' => 1,
            'limit' => 10,
            'offset' => 0,
            'totalPages' => 0
        ],
        'overview' => [
            'totalRecords' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
