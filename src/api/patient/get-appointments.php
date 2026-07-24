<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/schedule-management.php';

require_role('benhnhan');

function buildAppointmentFilterClause($tab, $search, $status, &$params, &$types)
{
    $where = " WHERE lk.maBenhNhan = ?";
    $params[] = null;
    $types .= 's';

    if ($tab === 'upcoming') {
        $where .= " AND lk.trangThai = 'Đã đặt'";
    } elseif ($tab === 'history') {
        $where .= " AND lk.trangThai IN ('Hoàn thành', 'Hủy')";
    } else {
        throw new Exception('Tab không hợp lệ');
    }

    if ($status !== '' && $tab === 'history') {
        if (!in_array($status, ['Hoàn thành', 'Hủy'], true)) {
            throw new Exception('Trạng thái lọc không hợp lệ');
        }
        $where .= " AND lk.trangThai = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($search !== '') {
        $where .= " AND (bs.tenBacSi LIKE ? OR ck.tenChuyenKhoa LIKE ? OR gk.tenGoi LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }

    return $where;
}

function resolveOrderClause($tab, $sort)
{
    if ($tab === 'upcoming') {
        return $sort === 'date-desc'
            ? ' ORDER BY lk.ngayKham DESC, sk.gioBatDau DESC, lk.maLichKham DESC'
            : ' ORDER BY lk.ngayKham ASC, sk.gioBatDau ASC, lk.maLichKham ASC';
    }

    return $sort === 'date-asc'
        ? ' ORDER BY lk.ngayKham ASC, sk.gioBatDau ASC, lk.maLichKham ASC'
        : ' ORDER BY lk.ngayKham DESC, sk.gioBatDau DESC, lk.maLichKham DESC';
}

function splitPatientNoteAndCancelReason($rawNote)
{
    $note = trim((string)$rawNote);
    if ($note === '') {
        return ['', ''];
    }

    $marker = '[Lý do hủy]:';
    $markerPos = stripos($note, $marker);

    if ($markerPos === false) {
        return [$note, ''];
    }

    $patientNote = trim(substr($note, 0, $markerPos));
    $cancelReason = trim(substr($note, $markerPos + strlen($marker)));

    return [$patientNote, $cancelReason];
}

function mapAppointmentRow($row)
{
    list($patientNote, $cancelReason) = splitPatientNoteAndCancelReason($row['ghiChu'] ?? '');
    $rescheduleState = buildAppointmentRescheduleState($row);

    return [
        'maLichKham' => (int)$row['maLichKham'],
        'maBacSi' => (string)($row['maBacSi'] ?? ''),
        'maCa' => (int)($row['maCa'] ?? 0),
        'maSuat' => (int)($row['maSuat'] ?? 0),
        'maGoi' => (int)($row['maGoi'] ?? 0),
        'ngayKhamRaw' => (string)($row['ngayKham'] ?? ''),
        'ngayKham' => date('d/m/Y', strtotime($row['ngayKham'])),
        'gioKham' => substr($row['gioBatDau'], 0, 5) . ' - ' . substr($row['gioKetThuc'], 0, 5),
        'bacSi' => 'BS. ' . $row['tenBacSi'],
        'chuyenKhoa' => $row['tenChuyenKhoa'] ?: 'Đa khoa',
        'goiKham' => $row['tenGoi'],
        'giaGoi' => (float)$row['gia'],
        'tenCa' => $row['tenCa'] ?? '',
        'gioBatDau' => substr((string)($row['gioBatDau'] ?? ''), 0, 5),
        'gioKetThuc' => substr((string)($row['gioKetThuc'] ?? ''), 0, 5),
        'trangThai' => $row['trangThai'],
        'ghiChuBenhNhan' => $patientNote,
        'lyDoHuy' => $cancelReason,
        'soLanDoiLich' => (int)($row['soLanDoiLich'] ?? 0),
        'thoiGianDoiLich' => $row['thoiGianDoiLich'] ?? null,
        'canReschedule' => $rescheduleState['canReschedule'],
        'rescheduleReason' => $rescheduleState['reason']
    ];
}

function buildAppointmentRescheduleState(array $row): array
{
    $default = [
        'canReschedule' => false,
        'reason' => 'Không thể đổi lịch vào lúc này'
    ];

    if (($row['trangThai'] ?? '') !== 'Đã đặt') {
        return [
            'canReschedule' => false,
            'reason' => 'Chỉ có thể đổi lịch cho các lịch đang ở trạng thái Đã đặt'
        ];
    }

    if ((int)($row['soLanDoiLich'] ?? 0) >= 1) {
        return [
            'canReschedule' => false,
            'reason' => 'Lịch này chỉ được đổi 1 lần'
        ];
    }

    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $appointmentTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string)($row['ngayKham'] ?? '') . ' ' . substr((string)($row['gioBatDau'] ?? ''), 0, 8),
        $tz
    );

    if (!$appointmentTime) {
        return $default;
    }

    $now = new DateTimeImmutable('now', $tz);
    $cutoffMinutes = getAppointmentRescheduleLimitHours() * 60;
    $remainingMinutes = (int)floor(($appointmentTime->getTimestamp() - $now->getTimestamp()) / 60);

    if ($remainingMinutes < $cutoffMinutes) {
        return [
            'canReschedule' => false,
            'reason' => 'Chỉ được đổi trước ' . getAppointmentRescheduleLimitHours() . ' giờ so với giờ khám'
        ];
    }

    return [
        'canReschedule' => true,
        'reason' => 'Có thể đổi lịch'
    ];
}

function getPatientCode($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT maBenhNhan
        FROM benhnhan
        WHERE nguoiDungId = ?
    ");
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn thông tin bệnh nhân');
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

function fetchSummary($conn, $maBenhNhan)
{
    $summaryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS tongLich,
            SUM(CASE WHEN lk.trangThai = 'Đã đặt' THEN 1 ELSE 0 END) AS sapToi,
            SUM(CASE WHEN lk.trangThai = 'Hoàn thành' THEN 1 ELSE 0 END) AS hoanThanh,
            SUM(CASE WHEN lk.trangThai = 'Hủy' THEN 1 ELSE 0 END) AS daHuy,
            COALESCE(SUM(CASE WHEN lk.trangThai IN ('Đã đặt', 'Hoàn thành') THEN gk.gia ELSE 0 END), 0) AS tongGiaTriGoi,
            COALESCE(SUM(CASE WHEN lk.trangThai = 'Hoàn thành' THEN (gk.gia + COALESCE(dt.tongTienThuoc, 0)) ELSE 0 END), 0) AS tongChiTieuDaKham
        FROM lichkham lk
        LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi
        LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
        WHERE lk.maBenhNhan = ?
    ");
    if (!$summaryStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn thống kê tổng quan');
    }
    $summaryStmt->bind_param("s", $maBenhNhan);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $summaryRow = $summaryResult->fetch_assoc() ?: [];
    $summaryStmt->close();

    $specialties = [];
    $specialtyStmt = $conn->prepare("
        SELECT
            COALESCE(ck.tenChuyenKhoa, 'Đa khoa') AS tenChuyenKhoa,
            COUNT(*) AS soLuot
        FROM lichkham lk
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        WHERE lk.maBenhNhan = ?
        GROUP BY COALESCE(ck.tenChuyenKhoa, 'Đa khoa')
        ORDER BY soLuot DESC, tenChuyenKhoa ASC
        LIMIT 5
    ");
    if (!$specialtyStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn thống kê chuyên khoa');
    }
    $specialtyStmt->bind_param("s", $maBenhNhan);
    $specialtyStmt->execute();
    $specialtyResult = $specialtyStmt->get_result();
    while ($row = $specialtyResult->fetch_assoc()) {
        $specialties[] = [
            'tenChuyenKhoa' => $row['tenChuyenKhoa'],
            'soLuot' => (int)$row['soLuot']
        ];
    }
    $specialtyStmt->close();

    $packages = [];
    $packageStmt = $conn->prepare("
        SELECT
            gk.tenGoi,
            COUNT(*) AS soLuot,
            COALESCE(SUM(gk.gia), 0) AS tongTien
        FROM lichkham lk
        JOIN goikham gk ON lk.maGoi = gk.maGoi
        WHERE lk.maBenhNhan = ?
        GROUP BY gk.maGoi, gk.tenGoi
        ORDER BY soLuot DESC, gk.tenGoi ASC
    ");
    if (!$packageStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn thống kê gói khám');
    }
    $packageStmt->bind_param("s", $maBenhNhan);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    while ($row = $packageResult->fetch_assoc()) {
        $packages[] = [
            'tenGoi' => $row['tenGoi'],
            'soLuot' => (int)$row['soLuot'],
            'tongTien' => (float)$row['tongTien']
        ];
    }
    $packageStmt->close();

    return [
        'counts' => [
            'total' => (int)($summaryRow['tongLich'] ?? 0),
            'upcoming' => (int)($summaryRow['sapToi'] ?? 0),
            'completed' => (int)($summaryRow['hoanThanh'] ?? 0),
            'cancelled' => (int)($summaryRow['daHuy'] ?? 0)
        ],
        'financial' => [
            'totalPackageAmount' => (float)($summaryRow['tongGiaTriGoi'] ?? 0),
            'completedAmount' => (float)($summaryRow['tongChiTieuDaKham'] ?? 0)
        ],
        'topSpecialty' => $specialties[0] ?? null,
        'topPackage' => $packages[0] ?? null,
        'specialties' => $specialties,
        'packages' => $packages
    ];
}

function fetchAppointmentsByTab($conn, $maBenhNhan, $tab, $page, $limit, $search, $status, $sort)
{
    ensureScheduleManagementSchema($conn);

    $baseJoinSql = "
        FROM lichkham lk
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN goikham gk ON lk.maGoi = gk.maGoi
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
    ";

    $params = [];
    $types = '';
    $whereClause = buildAppointmentFilterClause($tab, $search, $status, $params, $types);
    $params[0] = $maBenhNhan;
    $orderClause = resolveOrderClause($tab, $sort);

    $countSql = "SELECT COUNT(*) AS total " . $baseJoinSql . $whereClause;
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn đếm lịch khám');
    }
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $total = (int)($countRow['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $limit) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    if ($total === 0) {
        return [
            'data' => [],
            'pagination' => [
                'mode' => 'deferred',
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
                'offset' => 0,
                'totalPages' => 0
            ]
        ];
    }

    $idsSql = "
        SELECT lk.maLichKham
        " . $baseJoinSql . "
        " . $whereClause . "
        " . $orderClause . "
        LIMIT ? OFFSET ?
    ";
    $idsStmt = $conn->prepare($idsSql);
    if (!$idsStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn phân trang lịch khám');
    }

    $idsParams = $params;
    $idsTypes = $types . 'ii';
    $idsParams[] = $limit;
    $idsParams[] = $offset;

    if ($idsTypes !== '') {
        $idsStmt->bind_param($idsTypes, ...$idsParams);
    }
    $idsStmt->execute();
    $idsResult = $idsStmt->get_result();
    $appointmentIds = [];
    while ($row = $idsResult->fetch_assoc()) {
        $appointmentIds[] = (int)$row['maLichKham'];
    }
    $idsStmt->close();

    if (empty($appointmentIds)) {
        return [
            'data' => [],
            'pagination' => [
                'mode' => 'deferred',
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
                'totalPages' => $totalPages
            ]
        ];
    }

    $idPlaceholders = implode(',', array_fill(0, count($appointmentIds), '?'));
    $detailSql = "
        SELECT
            lk.maLichKham,
            lk.maBacSi,
            lk.ngayKham,
            lk.maCa,
            lk.maSuat,
            lk.maGoi,
            lk.soLanDoiLich,
            lk.thoiGianDoiLich,
            lk.trangThai,
            lk.ghiChu,
            bs.tenBacSi,
            ck.tenChuyenKhoa,
            ca.tenCa,
            gk.tenGoi,
            gk.gia,
            sk.gioBatDau,
            sk.gioKetThuc
        FROM lichkham lk
        JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
        JOIN goikham gk ON lk.maGoi = gk.maGoi
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE lk.maLichKham IN ($idPlaceholders)
        " . $orderClause;

    $detailStmt = $conn->prepare($detailSql);
    if (!$detailStmt) {
        throw new Exception('Không thể chuẩn bị truy vấn chi tiết lịch khám');
    }
    $detailTypes = str_repeat('i', count($appointmentIds));
    $detailStmt->bind_param($detailTypes, ...$appointmentIds);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    $appointments = [];
    while ($row = $detailResult->fetch_assoc()) {
        $appointments[] = mapAppointmentRow($row);
    }
    $detailStmt->close();

    return [
        'data' => $appointments,
        'pagination' => [
            'mode' => 'deferred',
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'totalPages' => $totalPages
        ]
    ];
}

try {
    $maBenhNhan = getPatientCode($conn, (int)$_SESSION['id']);
    $tab = strtolower(trim($_GET['tab'] ?? 'upcoming'));
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $sort = trim($_GET['sort'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min((int)($_GET['limit'] ?? 10), 50));

    if (!in_array($tab, ['upcoming', 'history', 'summary'], true)) {
        throw new Exception('Tab không hợp lệ');
    }

    $summary = fetchSummary($conn, $maBenhNhan);

    if ($tab === 'summary') {
        echo json_encode([
            'success' => true,
            'tab' => 'summary',
            'summary' => $summary
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    $result = fetchAppointmentsByTab($conn, $maBenhNhan, $tab, $page, $limit, $search, $status, $sort);

    echo json_encode([
        'success' => true,
        'tab' => $tab,
        'data' => $result['data'],
        'pagination' => $result['pagination'],
        'summary' => $summary
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tab' => 'error',
        'data' => [],
        'pagination' => [
            'mode' => 'deferred',
            'total' => 0,
            'page' => 1,
            'limit' => 10,
            'offset' => 0,
            'totalPages' => 0
        ],
        'summary' => [
            'counts' => [
                'total' => 0,
                'upcoming' => 0,
                'completed' => 0,
                'cancelled' => 0
            ],
            'financial' => [
                'totalPackageAmount' => 0,
                'completedAmount' => 0
            ],
            'topSpecialty' => null,
            'topPackage' => null,
            'specialties' => [],
            'packages' => []
        ]
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
