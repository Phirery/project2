<?php

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

    $conn->query("UPDATE suatkham SET effectiveFrom = '1900-01-01' WHERE effectiveFrom IS NULL OR effectiveFrom = '0000-00-00'");
    $conn->query("UPDATE suatkham SET presetMinutes = 40 WHERE presetMinutes IS NULL OR presetMinutes <= 0");
    $conn->query("UPDATE suatkham SET effectiveTo = NULL WHERE effectiveTo = '0000-00-00'");
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
    $sql = "
        SELECT
            lk.maLichKham,
            lk.maSuat,
            TIME_FORMAT(sk.gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i:%s') AS gioKetThuc
        FROM lichkham lk
        JOIN suatkham sk ON lk.maSuat = sk.maSuat
        WHERE lk.$columnName = ?
          AND lk.ngayKham = ?
          AND lk.trangThai != 'Hủy'
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
        'ssiissssss',
        $columnValue,
        $ngayKham,
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

function applySlotPreset(mysqli $conn, int $durationMinutes): array
{
    ensureScheduleManagementSchema($conn);

    if (!isValidSlotPreset($durationMinutes)) {
        throw new InvalidArgumentException('Preset slot không hợp lệ');
    }

    $shifts = getShiftRows($conn);
    $generatedSlots = buildSlotsForPreset($shifts, $durationMinutes);
    $effectiveFrom = getNextScheduleEffectiveDate($conn);

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