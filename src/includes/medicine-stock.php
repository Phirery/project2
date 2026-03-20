<?php

function ensureMedicineStockLogTable(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS medicine_stock_log (
            id INT NOT NULL AUTO_INCREMENT,
            maThuoc INT NOT NULL,
            maLichKham INT DEFAULT NULL,
            maHoSo VARCHAR(30) DEFAULT NULL,
            changeQty INT NOT NULL,
            balanceAfter INT NOT NULL,
            actionType VARCHAR(32) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_medicine_stock_log_maThuoc (maThuoc),
            KEY idx_medicine_stock_log_maLichKham (maLichKham),
            KEY idx_medicine_stock_log_maHoSo (maHoSo),
            KEY idx_medicine_stock_log_createdAt (createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    $conn->query($sql);
    $done = true;
}

function normalizeMedicineQuantityMap(array $items): array
{
    $map = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $maThuoc = (int)($item['maThuoc'] ?? 0);
        $soLuong = (int)($item['soLuong'] ?? 0);

        if ($maThuoc <= 0 || $soLuong <= 0) {
            continue;
        }

        if (!isset($map[$maThuoc])) {
            $map[$maThuoc] = 0;
        }

        $map[$maThuoc] += $soLuong;
    }

    return $map;
}

function getPrescriptionItemsByAppointment(mysqli $conn, int $maLichKham): array
{
    $sql = "
        SELECT ct.maThuoc, SUM(ct.soLuong) AS soLuong
        FROM donthuoc dt
        JOIN chitietdonthuoc ct ON dt.maDonThuoc = ct.maDonThuoc
        WHERE dt.maLichKham = ?
        GROUP BY ct.maThuoc
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $maLichKham);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'maThuoc' => (int)$row['maThuoc'],
            'soLuong' => (int)$row['soLuong']
        ];
    }
    $stmt->close();

    return $items;
}

function logMedicineStockChange(
    mysqli $conn,
    int $maThuoc,
    ?int $maLichKham,
    ?string $maHoSo,
    int $changeQty,
    int $balanceAfter,
    string $actionType,
    string $note = ''
): void {
    ensureMedicineStockLogTable($conn);

    $stmt = $conn->prepare("
        INSERT INTO medicine_stock_log (maThuoc, maLichKham, maHoSo, changeQty, balanceAfter, actionType, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iisisss', $maThuoc, $maLichKham, $maHoSo, $changeQty, $balanceAfter, $actionType, $note);
    $stmt->execute();
    $stmt->close();
}

function applyPrescriptionStockDelta(
    mysqli $conn,
    int $maLichKham,
    string $maHoSo,
    array $oldItems,
    array $newItems,
    bool $oldStockApplied,
    bool $newStockApplied
): array {
    $oldMap = normalizeMedicineQuantityMap($oldItems);
    $newMap = normalizeMedicineQuantityMap($newItems);
    $medicineIds = array_values(array_unique(array_merge(array_keys($oldMap), array_keys($newMap))));

    if (empty($medicineIds)) {
        return [];
    }

    $lowStockWarnings = [];

    foreach ($medicineIds as $maThuoc) {
        $previousQty = $oldStockApplied ? (int)($oldMap[$maThuoc] ?? 0) : 0;
        $nextQty = $newStockApplied ? (int)($newMap[$maThuoc] ?? 0) : 0;
        $delta = $nextQty - $previousQty;

        if ($delta === 0) {
            continue;
        }

        $stmt = $conn->prepare("
            SELECT maThuoc, tenThuoc, soLuongTon, COALESCE(nguongCanhBao, 10) AS nguongCanhBao
            FROM thuoc
            WHERE maThuoc = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param('i', $maThuoc);
        $stmt->execute();
        $medicine = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$medicine) {
            throw new Exception('Một hoặc nhiều thuốc không còn tồn tại trong kho.');
        }

        $currentStock = (int)($medicine['soLuongTon'] ?? 0);
        $warningThreshold = (int)($medicine['nguongCanhBao'] ?? 10);

        if ($delta > 0 && $currentStock < $delta) {
            throw new Exception('Thuốc "' . $medicine['tenThuoc'] . '" chỉ còn ' . $currentStock . ' trong kho, không đủ cho số lượng kê đơn.');
        }

        $newBalance = $currentStock - $delta;
        $stmt = $conn->prepare("UPDATE thuoc SET soLuongTon = ? WHERE maThuoc = ?");
        $stmt->bind_param('ii', $newBalance, $maThuoc);
        if (!$stmt->execute()) {
            throw new Exception('Không thể cập nhật tồn kho thuốc: ' . $stmt->error);
        }
        $stmt->close();

        $actionType = $delta > 0 ? ($previousQty > 0 ? 'adjust_deduct' : 'deduct') : 'restore';
        $note = 'Dong bo toa thuoc cho ho so ' . $maHoSo;
        logMedicineStockChange($conn, $maThuoc, $maLichKham, $maHoSo, -$delta, $newBalance, $actionType, $note);

        if ($newBalance <= $warningThreshold) {
            $lowStockWarnings[] = [
                'maThuoc' => (int)$medicine['maThuoc'],
                'tenThuoc' => $medicine['tenThuoc'],
                'soLuongTon' => $newBalance,
                'nguongCanhBao' => $warningThreshold
            ];
        }
    }

    return $lowStockWarnings;
}
