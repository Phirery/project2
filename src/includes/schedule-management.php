<?php
require_once __DIR__ . '/../config/app-env.php';

function getPendingBookingHoldMinutes(): int
{
    return max(1, (int)(getConfigValue('VNPAY_HOLD_MINUTES') ?: 15));
}

function getAppointmentRescheduleLimitHours(): int
{
    return max(1, (int)(getConfigValue('APPOINTMENT_RESCHEDULE_HOURS') ?: 24));
}

function scheduleManagementColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    return $count > 0;
}

function scheduleManagementIndexExists(mysqli $conn, string $tableName, string $indexName): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?"
    );
    $stmt->bind_param('ss', $tableName, $indexName);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    return $count > 0;
}

function ensureScheduleManagementSchema(mysqli $conn): void
{
    if (!scheduleManagementColumnExists($conn, 'suatkham', 'isActive')) {
        $conn->query("ALTER TABLE suatkham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gioKetThuc");
    }

    if (!scheduleManagementColumnExists($conn, 'suatkham', 'effectiveFrom')) {
        $conn->query("ALTER TABLE suatkham ADD COLUMN effectiveFrom DATE NOT NULL DEFAULT '1900-01-01' AFTER isActive");
    }

    if (!scheduleManagementColumnExists($conn, 'suatkham', 'effectiveTo')) {
        $conn->query("ALTER TABLE suatkham ADD COLUMN effectiveTo DATE DEFAULT NULL AFTER effectiveFrom");
    }

    if (!scheduleManagementColumnExists($conn, 'suatkham', 'presetMinutes')) {
        $conn->query("ALTER TABLE suatkham ADD COLUMN presetMinutes INT(11) NOT NULL DEFAULT 40 AFTER effectiveTo");
    }

    if (!scheduleManagementIndexExists($conn, 'suatkham', 'uniq_suatkham_slot_version')) {
        $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_suatkham_duplicate_map");
        $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_suatkham_canonical");
        $conn->query(
            "CREATE TEMPORARY TABLE tmp_suatkham_canonical
             SELECT
                MIN(maSuat) AS keepMaSuat,
                maCa,
                gioBatDau,
                gioKetThuc,
                effectiveFrom
             FROM suatkham
             GROUP BY maCa, gioBatDau, gioKetThuc, effectiveFrom"
        );
        $conn->query(
            "CREATE TEMPORARY TABLE tmp_suatkham_duplicate_map
             SELECT
                s.maSuat AS oldMaSuat,
                c.keepMaSuat AS newMaSuat
             FROM suatkham s
             JOIN tmp_suatkham_canonical c
               ON c.maCa = s.maCa
              AND c.gioBatDau = s.gioBatDau
              AND c.gioKetThuc = s.gioKetThuc
              AND c.effectiveFrom = s.effectiveFrom
             WHERE s.maSuat <> c.keepMaSuat"
        );
        $conn->query(
            "UPDATE lichkham lk
             JOIN tmp_suatkham_duplicate_map m ON lk.maSuat = m.oldMaSuat
             SET lk.maSuat = m.newMaSuat"
        );
        $conn->query(
            "DELETE s
             FROM suatkham s
             JOIN tmp_suatkham_duplicate_map m ON s.maSuat = m.oldMaSuat"
        );
        $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_suatkham_duplicate_map");
        $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_suatkham_canonical");

        $conn->query(
            "ALTER TABLE suatkham
             ADD UNIQUE KEY uniq_suatkham_slot_version (maCa, gioBatDau, gioKetThuc, effectiveFrom)"
        );
    }

    if (!scheduleManagementColumnExists($conn, 'goikham', 'isActive')) {
        $conn->query("ALTER TABLE goikham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gia");
    }

    if (!scheduleManagementColumnExists($conn, 'lichkham', 'soLanDoiLich')) {
        $conn->query("ALTER TABLE lichkham ADD COLUMN soLanDoiLich TINYINT(1) NOT NULL DEFAULT 0 AFTER nguoiHuy");
    }

    if (!scheduleManagementColumnExists($conn, 'lichkham', 'thoiGianDoiLich')) {
        $conn->query("ALTER TABLE lichkham ADD COLUMN thoiGianDoiLich DATETIME DEFAULT NULL AFTER soLanDoiLich");
    }

    $conn->query("UPDATE suatkham SET effectiveFrom = '1900-01-01' WHERE effectiveFrom IS NULL OR effectiveFrom = '0000-00-00'");
    $conn->query("UPDATE suatkham SET presetMinutes = 40 WHERE presetMinutes IS NULL OR presetMinutes <= 0");
    $conn->query("UPDATE suatkham SET effectiveTo = NULL WHERE effectiveTo = '0000-00-00'");
    $conn->query("UPDATE lichkham SET soLanDoiLich = 0 WHERE soLanDoiLich IS NULL");
    $conn->query(
        "DELETE s
         FROM suatkham s
         LEFT JOIN lichkham lk ON lk.maSuat = s.maSuat
         WHERE lk.maSuat IS NULL
           AND TIME_TO_SEC(TIMEDIFF(s.gioKetThuc, s.gioBatDau)) <> (s.presetMinutes * 60)"
    );
}

function getAllowedSlotPresets(): array
{
    return [30, 40, 60];
}

function isValidSlotPreset(int $durationMinutes): bool
{
    return in_array($durationMinutes, getAllowedSlotPresets(), true);
}

function timeStringToMinutes(string $timeString): int
{
    [$hours, $minutes] = array_map('intval', explode(':', substr($timeString, 0, 5)));
    return ($hours * 60) + $minutes;
}

function minutesToTimeString(int $totalMinutes): string
{
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return sprintf('%02d:%02d:00', $hours, $minutes);
}

function calculateDurationMinutes(string $startTime, string $endTime): int
{
    return timeStringToMinutes($endTime) - timeStringToMinutes($startTime);
}

function getNormalizedScheduleDate(?string $date = null): string
{
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    return date('Y-m-d');
}

function getScheduleMonthBounds(?string $referenceDate = null): array
{
    $normalizedDate = getNormalizedScheduleDate($referenceDate);
    $monthStart = substr($normalizedDate, 0, 7) . '-01';
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $monthStart, new DateTimeZone('Asia/Ho_Chi_Minh'));

    if (!$start) {
        $start = new DateTimeImmutable('first day of this month', new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    $end = $start->modify('last day of this month');

    return [
        'month' => $start->format('Y-m'),
        'startDate' => $start->format('Y-m-d'),
        'endDate' => $end->format('Y-m-d')
    ];
}

function getScheduleWeekdayIso(string $date): int
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 0;
    }

    return (int)date('N', $timestamp);
}

function isScheduleSunday(string $date): bool
{
    return getScheduleWeekdayIso($date) === 7;
}

function isDefaultWorkingScheduleDate(string $date): bool
{
    return !isScheduleSunday($date);
}

function getDefaultWorkingShiftIdsForDate(mysqli $conn, string $date): array
{
    if (!isDefaultWorkingScheduleDate($date)) {
        return [];
    }

    return array_map(
        static fn(array $shift): int => (int)$shift['maCa'],
        getShiftRows($conn)
    );
}

function getDoctorLeaveRowsForRange(mysqli $conn, string $maBacSi, string $startDate, string $endDate): array
{
    ensureScheduleManagementSchema($conn);

    $stmt = $conn->prepare(
        "SELECT
            maNghi,
            ngayNghi,
            maCa,
            lyDo
         FROM ngaynghi
         WHERE maBacSi = ?
           AND ngayNghi BETWEEN ? AND ?
         ORDER BY ngayNghi ASC, maCa IS NULL DESC, maCa ASC, maNghi ASC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('sss', $maBacSi, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rowsByDate = [];
    while ($row = $result->fetch_assoc()) {
        $date = (string)$row['ngayNghi'];
        if (!isset($rowsByDate[$date])) {
            $rowsByDate[$date] = [
                'allDay' => false,
                'shiftMap' => [],
                'rows' => []
            ];
        }

        $leaveRow = [
            'maNghi' => (int)$row['maNghi'],
            'maCa' => $row['maCa'] !== null ? (int)$row['maCa'] : null,
            'lyDo' => $row['lyDo'] ?? null
        ];

        $rowsByDate[$date]['rows'][] = $leaveRow;
        if ($leaveRow['maCa'] === null) {
            $rowsByDate[$date]['allDay'] = true;
            continue;
        }

        $rowsByDate[$date]['shiftMap'][$leaveRow['maCa']] = $leaveRow;
    }

    $stmt->close();

    return $rowsByDate;
}

function buildDoctorMonthlySchedule(mysqli $conn, string $maBacSi, ?string $referenceDate = null): array
{
    ensureScheduleManagementSchema($conn);

    $bounds = getScheduleMonthBounds($referenceDate);
    $shifts = getShiftRows($conn);
    $leaveRowsByDate = getDoctorLeaveRowsForRange($conn, $maBacSi, $bounds['startDate'], $bounds['endDate']);

    $start = DateTimeImmutable::createFromFormat('Y-m-d', $bounds['startDate'], new DateTimeZone('Asia/Ho_Chi_Minh'));
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $bounds['endDate'], new DateTimeZone('Asia/Ho_Chi_Minh'));

    if (!$start || !$end) {
        throw new RuntimeException('Không thể tạo khung lịch tháng');
    }

    $days = [];
    $workingCells = 0;
    $offCells = 0;
    $workingDays = 0;
    $offDays = 0;

    $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
    foreach ($period as $day) {
        $date = $day->format('Y-m-d');
        $weekday = (int)$day->format('N');
        $defaultWorkingDay = $weekday >= 1 && $weekday <= 6;
        $dayLeaves = $leaveRowsByDate[$date] ?? [
            'allDay' => false,
            'shiftMap' => [],
            'rows' => []
        ];

        $shiftStates = [];
        $dayWorkingCells = 0;
        $dayOffCells = 0;

        foreach ($shifts as $shift) {
            $maCa = (int)$shift['maCa'];
            $hasShiftLeave = isset($dayLeaves['shiftMap'][$maCa]);
            $isWorking = $defaultWorkingDay && !$dayLeaves['allDay'] && !$hasShiftLeave;

            if ($isWorking) {
                $dayWorkingCells++;
            } else {
                $dayOffCells++;
            }

            $reason = null;
            if (!$defaultWorkingDay) {
                $reason = 'Chủ nhật nghỉ mặc định';
            } elseif ($dayLeaves['allDay']) {
                $reason = 'Bác sĩ không xếp lịch cho ngày này';
            } elseif ($hasShiftLeave) {
                $reason = 'Bác sĩ không xếp lịch cho ca này';
            }

            $shiftStates[] = [
                'maCa' => $maCa,
                'tenCa' => $shift['tenCa'],
                'gioBatDau' => $shift['gioBatDau'],
                'gioKetThuc' => $shift['gioKetThuc'],
                'isWorking' => $isWorking,
                'isDefaultWorking' => $defaultWorkingDay,
                'reason' => $reason
            ];
        }

        if ($dayWorkingCells > 0) {
            $workingDays++;
        } else {
            $offDays++;
        }

        $workingCells += $dayWorkingCells;
        $offCells += $dayOffCells;

        $days[] = [
            'date' => $date,
            'weekday' => $weekday,
            'isSunday' => $weekday === 7,
            'isDefaultWorkingDay' => $defaultWorkingDay,
            'hasLeave' => !empty($dayLeaves['rows']),
            'shifts' => $shiftStates
        ];
    }

    return [
        'month' => $bounds['month'],
        'startDate' => $bounds['startDate'],
        'endDate' => $bounds['endDate'],
        'shifts' => $shifts,
        'days' => $days,
        'summary' => [
            'totalDays' => count($days),
            'workingDays' => $workingDays,
            'offDays' => $offDays,
            'workingCells' => $workingCells,
            'offCells' => $offCells
        ]
    ];
}

function normalizeScheduleCellSelection(array $input, array $allowedShiftIds, string $monthStart, string $monthEnd): array
{
    $selected = [];
    $allowedShiftLookup = array_fill_keys(array_map('intval', $allowedShiftIds), true);

    foreach ($input as $item) {
        $date = null;
        $maCa = null;

        if (is_string($item)) {
            $raw = trim($item);
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^(\d{4}-\d{2}-\d{2})[|:#](\d+)$/', $raw, $matches)) {
                $date = $matches[1];
                $maCa = (int)$matches[2];
            }
        } elseif (is_array($item)) {
            $date = trim((string)($item['date'] ?? $item['ngay'] ?? $item['ngayKham'] ?? $item['ngayNghi'] ?? ''));
            $maCaValue = $item['maCa'] ?? $item['shiftId'] ?? $item['ca'] ?? null;
            if ($maCaValue !== null && $maCaValue !== '') {
                $maCa = (int)$maCaValue;
            }
        }

        if (!$date || $maCa === null || $maCa <= 0) {
            continue;
        }

        if ($date < $monthStart || $date > $monthEnd) {
            throw new InvalidArgumentException('Lịch gửi lên phải nằm trong tháng đang xử lý');
        }

        if (!isset($allowedShiftLookup[$maCa])) {
            throw new InvalidArgumentException('Ca làm việc không hợp lệ');
        }

        if (isScheduleSunday($date)) {
            throw new InvalidArgumentException('Chủ nhật là ngày nghỉ mặc định');
        }

        $selected[$date . '|' . $maCa] = [
            'date' => $date,
            'maCa' => $maCa
        ];
    }

    return $selected;
}

function getDoctorMonthlySchedulePayload(mysqli $conn, string $maBacSi, ?string $referenceDate = null): array
{
    $schedule = buildDoctorMonthlySchedule($conn, $maBacSi, $referenceDate);

    return [
        'success' => true,
        'data' => $schedule
    ];
}

function getDoctorAppointmentsForRange(mysqli $conn, string $maBacSi, string $startDate, string $endDate): array
{
    ensureScheduleManagementSchema($conn);

    $stmt = $conn->prepare(
        "SELECT
            lk.maLichKham,
            lk.ngayKham,
            lk.maCa,
            bn.tenBenhNhan,
            ca.tenCa,
            TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc
         FROM lichkham lk
         JOIN benhnhan bn ON lk.maBenhNhan = bn.maBenhNhan
         LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
         LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
         WHERE lk.maBacSi = ?
           AND lk.ngayKham BETWEEN ? AND ?
           AND lk.trangThai IN ('Chờ', 'Đã đặt')
         ORDER BY lk.ngayKham ASC, lk.maCa ASC, sk.gioBatDau ASC, lk.maLichKham ASC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('sss', $maBacSi, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = [
            'maLichKham' => (int)$row['maLichKham'],
            'ngayKham' => (string)$row['ngayKham'],
            'maCa' => (int)$row['maCa'],
            'tenBenhNhan' => $row['tenBenhNhan'],
            'tenCa' => $row['tenCa'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc']
        ];
    }

    $stmt->close();

    return $appointments;
}

function buildDoctorMonthlyScheduleUpdate(
    mysqli $conn,
    string $maBacSi,
    ?string $referenceDate,
    array $selectedCells,
    string $reason = ''
): array {
    ensureScheduleManagementSchema($conn);

    $bounds = getScheduleMonthBounds($referenceDate);
    $shifts = getShiftRows($conn);
    $shiftIds = array_map(static fn(array $shift): int => (int)$shift['maCa'], $shifts);
    $selectedMap = normalizeScheduleCellSelection($selectedCells, $shiftIds, $bounds['startDate'], $bounds['endDate']);
    $leaveRowsByDate = getDoctorLeaveRowsForRange($conn, $maBacSi, $bounds['startDate'], $bounds['endDate']);

    $start = DateTimeImmutable::createFromFormat('Y-m-d', $bounds['startDate'], new DateTimeZone('Asia/Ho_Chi_Minh'));
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $bounds['endDate'], new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$start || !$end) {
        throw new RuntimeException('Không thể xác định khoảng thời gian của tháng');
    }

    $defaultCellMap = [];
    $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
    foreach ($period as $day) {
        $date = $day->format('Y-m-d');
        if (!isDefaultWorkingScheduleDate($date)) {
            continue;
        }

        foreach ($shiftIds as $maCa) {
            $defaultCellMap[$date . '|' . $maCa] = [
                'date' => $date,
                'maCa' => $maCa
            ];
        }
    }

    $offCellMap = array_diff_key($defaultCellMap, $selectedMap);
    $appointments = getDoctorAppointmentsForRange($conn, $maBacSi, $bounds['startDate'], $bounds['endDate']);
    $affectedAppointments = [];

    foreach ($appointments as $appointment) {
        $key = $appointment['ngayKham'] . '|' . (int)$appointment['maCa'];
        if (!isset($offCellMap[$key])) {
            continue;
        }

        $affectedAppointments[] = $appointment;
    }

    return [
        'month' => $bounds['month'],
        'startDate' => $bounds['startDate'],
        'endDate' => $bounds['endDate'],
        'shifts' => $shifts,
        'selectedCells' => $selectedMap,
        'offCells' => $offCellMap,
        'leaveRowsByDate' => $leaveRowsByDate,
        'appointments' => $appointments,
        'affectedAppointments' => $affectedAppointments,
        'cancelReason' => trim($reason) !== ''
            ? trim($reason)
            : 'Bác sĩ xếp lại lịch làm việc trong tháng'
    ];
}

function getShiftRows(mysqli $conn): array
{
    ensureScheduleManagementSchema($conn);

    $result = $conn->query(
        "SELECT
            maCa,
            tenCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc
         FROM calamviec
         ORDER BY gioBatDau"
    );

    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        $shifts[] = [
            'maCa' => (int)$row['maCa'],
            'tenCa' => $row['tenCa'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc']
        ];
    }

    return $shifts;
}

function getScheduleSlotsForDate(mysqli $conn, ?string $date = null): array
{
    ensureScheduleManagementSchema($conn);
    $targetDate = getNormalizedScheduleDate($date);
    $versionDate = getCurrentScheduleVersionDate($conn, $targetDate);

    if (!$versionDate) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT
            maSuat,
            maCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            isActive,
            effectiveFrom,
            effectiveTo,
            presetMinutes
         FROM suatkham
         WHERE effectiveFrom = ?
           AND effectiveFrom <= ?
           AND (effectiveTo IS NULL OR effectiveTo >= ?)
           AND TIME_TO_SEC(TIMEDIFF(gioKetThuc, gioBatDau)) = (presetMinutes * 60)
         ORDER BY maCa, gioBatDau, maSuat"
    );
    $stmt->bind_param('sss', $versionDate, $targetDate, $targetDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = [
            'maSuat' => (int)$row['maSuat'],
            'maCa' => (int)$row['maCa'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc'],
            'isActive' => (int)$row['isActive'],
            'effectiveFrom' => $row['effectiveFrom'],
            'effectiveTo' => $row['effectiveTo'],
            'presetMinutes' => (int)$row['presetMinutes']
        ];
    }
    $stmt->close();

    return $slots;
}

function getCurrentSlotPresetMinutes(mysqli $conn, int $defaultMinutes = 40): int
{
    $slots = getScheduleSlotsForDate($conn);

    if (empty($slots)) {
        return $defaultMinutes;
    }

    $presetMinutes = (int)($slots[0]['presetMinutes'] ?? 0);
    if ($presetMinutes > 0) {
        return $presetMinutes;
    }

    return $defaultMinutes;
}

function getCurrentActiveSlots(mysqli $conn): array
{
    return getScheduleSlotsForDate($conn);
}

function getSlotRowById(mysqli $conn, int $maSuat, ?string $date = null): ?array
{
    ensureScheduleManagementSchema($conn);
    $targetDate = getNormalizedScheduleDate($date);

    $stmt = $conn->prepare(
        "SELECT
            maSuat,
            maCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            isActive,
            effectiveFrom,
            effectiveTo,
            presetMinutes
         FROM suatkham
         WHERE maSuat = ?
           AND effectiveFrom <= ?
           AND (effectiveTo IS NULL OR effectiveTo >= ?)
           AND TIME_TO_SEC(TIMEDIFF(gioKetThuc, gioBatDau)) = (presetMinutes * 60)
         LIMIT 1"
    );
    $stmt->bind_param('iss', $maSuat, $targetDate, $targetDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'maSuat' => (int)$row['maSuat'],
        'maCa' => (int)$row['maCa'],
        'gioBatDau' => $row['gioBatDau'],
        'gioKetThuc' => $row['gioKetThuc'],
        'isActive' => (int)$row['isActive'],
        'effectiveFrom' => $row['effectiveFrom'],
        'effectiveTo' => $row['effectiveTo'],
        'presetMinutes' => (int)$row['presetMinutes']
    ];
}

function getPackageRowById(mysqli $conn, int $maGoi): ?array
{
    ensureScheduleManagementSchema($conn);

    $stmt = $conn->prepare(
        "SELECT maGoi, tenGoi, moTa, thoiLuong, gia, isActive
         FROM goikham
         WHERE maGoi = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $maGoi);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'maGoi' => (int)$row['maGoi'],
        'tenGoi' => $row['tenGoi'],
        'moTa' => $row['moTa'],
        'thoiLuong' => (int)$row['thoiLuong'],
        'gia' => (float)$row['gia'],
        'isActive' => (int)$row['isActive']
    ];
}

function findOverlappingAppointment(
    mysqli $conn,
    string $columnName,
    string $columnValue,
    string $ngayKham,
    string $gioBatDau,
    string $gioKetThuc,
    int $excludeAppointmentId = 0
): ?array {
    $holdMinutes = getPendingBookingHoldMinutes();
    $sql = "
        SELECT
            lk.maLichKham,
            lk.maSuat,
            TIME_FORMAT(sk.gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i:%s') AS gioKetThuc
        FROM lichkham lk
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        LEFT JOIN hoadon hd ON hd.maLichKham = lk.maLichKham
        WHERE lk.$columnName = ?
          AND lk.ngayKham = ?
          AND lk.trangThai != 'Hủy'
          AND NOT (
              lk.trangThai = 'Chờ'
              AND hd.maHoaDon IS NOT NULL
              AND hd.trangThai = 'Chưa thanh toán'
              AND TIMESTAMPDIFF(MINUTE, hd.ngayTao, NOW()) > ?
          )
          AND (? = 0 OR lk.maLichKham != ?)
          AND (
              (sk.gioBatDau >= ? AND sk.gioBatDau < ?)
              OR (sk.gioKetThuc > ? AND sk.gioKetThuc <= ?)
              OR (sk.gioBatDau <= ? AND sk.gioKetThuc >= ?)
          )
        ORDER BY sk.gioBatDau
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssiiissssss',
        $columnValue,
        $ngayKham,
        $holdMinutes,
        $excludeAppointmentId,
        $excludeAppointmentId,
        $gioBatDau,
        $gioKetThuc,
        $gioBatDau,
        $gioKetThuc,
        $gioBatDau,
        $gioKetThuc
    );
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function findDoctorOverlap(
    mysqli $conn,
    string $maBacSi,
    string $ngayKham,
    string $gioBatDau,
    string $gioKetThuc,
    int $excludeAppointmentId = 0
): ?array {
    return findOverlappingAppointment($conn, 'maBacSi', $maBacSi, $ngayKham, $gioBatDau, $gioKetThuc, $excludeAppointmentId);
}

function findPatientOverlap(
    mysqli $conn,
    string $maBenhNhan,
    string $ngayKham,
    string $gioBatDau,
    string $gioKetThuc,
    int $excludeAppointmentId = 0
): ?array {
    return findOverlappingAppointment($conn, 'maBenhNhan', $maBenhNhan, $ngayKham, $gioBatDau, $gioKetThuc, $excludeAppointmentId);
}

function buildSlotsForPreset(array $shifts, int $durationMinutes): array
{
    if (!isValidSlotPreset($durationMinutes)) {
        throw new InvalidArgumentException('Preset slot không hợp lệ');
    }

    $generatedSlots = [];

    foreach ($shifts as $shift) {
        $startMinutes = timeStringToMinutes($shift['gioBatDau']);
        $endMinutes = timeStringToMinutes($shift['gioKetThuc']);
        $shiftDuration = $endMinutes - $startMinutes;

        if ($shiftDuration <= 0 || $shiftDuration % $durationMinutes !== 0) {
            throw new RuntimeException('Khung giờ ca khám hiện tại không chia hết cho preset đã chọn');
        }

        for ($cursor = $startMinutes; $cursor < $endMinutes; $cursor += $durationMinutes) {
            $generatedSlots[] = [
                'maCa' => $shift['maCa'],
                'gioBatDau' => minutesToTimeString($cursor),
                'gioKetThuc' => minutesToTimeString($cursor + $durationMinutes)
            ];
        }
    }

    return $generatedSlots;
}

function syncPackageDurations(mysqli $conn, int $durationMinutes): void
{
    $stmt = $conn->prepare("UPDATE goikham SET thoiLuong = ?");
    $stmt->bind_param('i', $durationMinutes);
    $stmt->execute();
    $stmt->close();
}

function applySlotPreset(mysqli $conn, int $durationMinutes, ?string $effectiveFromDate = null): array
{
    ensureScheduleManagementSchema($conn);

    if (!isValidSlotPreset($durationMinutes)) {
        throw new InvalidArgumentException('Preset slot không hợp lệ');
    }

    $shifts = getShiftRows($conn);
    $generatedSlots = buildSlotsForPreset($shifts, $durationMinutes);
    $effectiveFrom = $effectiveFromDate ?? getNextScheduleEffectiveDate($conn);

    $conn->begin_transaction();

    try {
        $deleteStmt = $conn->prepare("DELETE FROM suatkham WHERE effectiveFrom >= ?");
        $deleteStmt->bind_param('s', $effectiveFrom);
        $deleteStmt->execute();
        $deleteStmt->close();

        $stmtClose = $conn->prepare(
            "UPDATE suatkham
             SET effectiveTo = DATE_SUB(?, INTERVAL 1 DAY), isActive = 1
             WHERE effectiveFrom < ?
               AND (effectiveTo IS NULL OR effectiveTo >= ?)"
        );
        $stmtClose->bind_param('sss', $effectiveFrom, $effectiveFrom, $effectiveFrom);
        $stmtClose->execute();
        $stmtClose->close();

        $stmt = $conn->prepare(
            "INSERT INTO suatkham (maCa, gioBatDau, gioKetThuc, isActive, effectiveFrom, effectiveTo, presetMinutes)
             VALUES (?, ?, ?, 1, ?, NULL, ?)
             ON DUPLICATE KEY UPDATE
                 gioKetThuc = VALUES(gioKetThuc),
                 isActive = 1,
                 effectiveTo = NULL,
                 presetMinutes = VALUES(presetMinutes)"
        );

        foreach ($generatedSlots as $slot) {
            $stmt->bind_param('isssi', $slot['maCa'], $slot['gioBatDau'], $slot['gioKetThuc'], $effectiveFrom, $durationMinutes);
            $stmt->execute();
        }

        $stmt->close();

        syncPackageDurations($conn, $durationMinutes);

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    return getScheduleConfigData($conn);
}

function getScheduleConfigData(mysqli $conn): array
{
    ensureScheduleManagementSchema($conn);

    $shifts = getShiftRows($conn);
    $currentVersionDate = getCurrentScheduleVersionDate($conn);
    $activeSlots = $currentVersionDate ? getScheduleSlotsForDate($conn, $currentVersionDate) : getCurrentActiveSlots($conn);
    $currentDuration = getCurrentSlotPresetMinutes($conn);
    $slotsByShift = [];

    foreach ($shifts as $shift) {
        $slotsByShift[$shift['maCa']] = [];
    }

    foreach ($activeSlots as $slot) {
        if (!isset($slotsByShift[$slot['maCa']])) {
            $slotsByShift[$slot['maCa']] = [];
        }

        $slotsByShift[$slot['maCa']][] = [
            'maSuat' => $slot['maSuat'],
            'gioBatDau' => substr($slot['gioBatDau'], 0, 5),
            'gioKetThuc' => substr($slot['gioKetThuc'], 0, 5),
            'isActive' => (bool)$slot['isActive']
        ];
    }

    $shiftConfigs = [];
    foreach ($shifts as $shift) {
        $shiftSlots = $slotsByShift[$shift['maCa']] ?? [];
        $shiftConfigs[] = [
            'maCa' => $shift['maCa'],
            'tenCa' => $shift['tenCa'],
            'gioBatDau' => substr($shift['gioBatDau'], 0, 5),
            'gioKetThuc' => substr($shift['gioKetThuc'], 0, 5),
            'soSuat' => count($shiftSlots),
            'slots' => $shiftSlots
        ];
    }

    return [
        'currentDurationMinutes' => $currentDuration,
        'currentEffectiveFrom' => $currentVersionDate,
        'nextEffectiveFrom' => getNextScheduleEffectiveDate($conn),
        'maxScheduledDate' => getLastAppointmentDate($conn),
        'presetOptions' => getAllowedSlotPresets(),
        'shifts' => $shiftConfigs,
        'presetHistory' => getPresetHistory($conn),
        'upcomingPreset' => getUpcomingPreset($conn)
    ];
}

function getCurrentScheduleVersionDate(mysqli $conn, ?string $date = null): ?string
{
    ensureScheduleManagementSchema($conn);
    $targetDate = getNormalizedScheduleDate($date);

    $stmt = $conn->prepare(
        "SELECT effectiveFrom
         FROM suatkham
         WHERE effectiveFrom <= ?
           AND (effectiveTo IS NULL OR effectiveTo >= ?)
         ORDER BY effectiveFrom DESC
         LIMIT 1"
    );
    $stmt->bind_param('ss', $targetDate, $targetDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['effectiveFrom'] ?? null;
}

function getLastAppointmentDate(mysqli $conn): ?string
{
    $result = $conn->query("SELECT MAX(ngayKham) AS maxDate FROM lichkham WHERE trangThai != 'Hủy'");
    $row = $result ? $result->fetch_assoc() : null;
    $date = $row['maxDate'] ?? null;
    return $date ?: null;
}

function getNextScheduleEffectiveDate(mysqli $conn): string
{
    $lastAppointmentDate = getLastAppointmentDate($conn);
    if ($lastAppointmentDate) {
        return date('Y-m-d', strtotime($lastAppointmentDate . ' +1 day'));
    }

    return date('Y-m-d', strtotime('+1 day'));
}
function getPresetHistory(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT DISTINCT presetMinutes, effectiveFrom, effectiveTo
         FROM suatkham
         ORDER BY effectiveFrom ASC"
    );
 
    $history = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'presetMinutes' => (int)$row['presetMinutes'],
                'effectiveFrom' => $row['effectiveFrom'],
                'effectiveTo'   => $row['effectiveTo']
            ];
        }
    }
 
    return $history;
}
 
function getUpcomingPreset(mysqli $conn): ?array
{
    $today = date('Y-m-d');
    $stmt  = $conn->prepare(
        "SELECT DISTINCT presetMinutes, effectiveFrom
         FROM suatkham
         WHERE effectiveFrom > ?
         ORDER BY effectiveFrom ASC
         LIMIT 1"
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
 
    if (!$row) {
        return null;
    }
 
    return [
        'presetMinutes' => (int)$row['presetMinutes'],
        'effectiveFrom' => $row['effectiveFrom']
    ];
}
