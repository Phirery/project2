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

function ensureScheduleManagementSchema(mysqli $conn): void
{
    if (!scheduleManagementColumnExists($conn, 'suatkham', 'isActive')) {
        $conn->query("ALTER TABLE suatkham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gioKetThuc");
    }

    if (!scheduleManagementColumnExists($conn, 'goikham', 'isActive')) {
        $conn->query("ALTER TABLE goikham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gia");
    }
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

function getCurrentActiveSlots(mysqli $conn): array
{
    ensureScheduleManagementSchema($conn);

    $result = $conn->query(
        "SELECT
            maSuat,
            maCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            isActive
         FROM suatkham
         WHERE isActive = 1
         ORDER BY maCa, gioBatDau, maSuat"
    );

    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = [
            'maSuat' => (int)$row['maSuat'],
            'maCa' => (int)$row['maCa'],
            'gioBatDau' => $row['gioBatDau'],
            'gioKetThuc' => $row['gioKetThuc'],
            'isActive' => (int)$row['isActive']
        ];
    }

    return $slots;
}

function getCurrentSlotPresetMinutes(mysqli $conn, int $defaultMinutes = 40): int
{
    $slots = getCurrentActiveSlots($conn);

    if (empty($slots)) {
        return $defaultMinutes;
    }

    $durations = [];
    foreach ($slots as $slot) {
        $durations[] = calculateDurationMinutes($slot['gioBatDau'], $slot['gioKetThuc']);
    }

    $uniqueDurations = array_values(array_unique($durations));

    if (count($uniqueDurations) === 1 && $uniqueDurations[0] > 0) {
        return $uniqueDurations[0];
    }

    return $defaultMinutes;
}

function getSlotRowById(mysqli $conn, int $maSuat): ?array
{
    ensureScheduleManagementSchema($conn);

    $stmt = $conn->prepare(
        "SELECT
            maSuat,
            maCa,
            TIME_FORMAT(gioBatDau, '%H:%i:%s') AS gioBatDau,
            TIME_FORMAT(gioKetThuc, '%H:%i:%s') AS gioKetThuc,
            isActive
         FROM suatkham
         WHERE maSuat = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $maSuat);
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
        'isActive' => (int)$row['isActive']
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

    $conn->begin_transaction();

    try {
        $conn->query("UPDATE suatkham SET isActive = 0 WHERE isActive = 1");

        $stmt = $conn->prepare(
            "INSERT INTO suatkham (maCa, gioBatDau, gioKetThuc, isActive)
             VALUES (?, ?, ?, 1)"
        );

        foreach ($generatedSlots as $slot) {
            $stmt->bind_param('iss', $slot['maCa'], $slot['gioBatDau'], $slot['gioKetThuc']);
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
    $activeSlots = getCurrentActiveSlots($conn);
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
        'presetOptions' => getAllowedSlotPresets(),
        'shifts' => $shiftConfigs
    ];
}
