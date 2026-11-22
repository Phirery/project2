<?php

function ensureMedicineStockLogTable(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $existsRes = $conn->query("SHOW TABLES LIKE 'medicine_stock_log'");
    if ($existsRes && $existsRes->num_rows > 0) {
        $done = true;
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

/**
 * Phát thuốc nguyên tử tại quầy dược (Task 8):
 * - Chống phát trùng bằng clientRequestId (idempotency).
 * - Khóa dòng donthuoc, chitietdonthuoc và thuoc (theo thứ tự maThuoc tránh deadlock).
 * - Không bao giờ trừ vượt số lượng còn lại hoặc vượt tồn kho hiện có.
 */
function dispensePrescriptionWithStockLock(
    mysqli $conn,
    int $maDonThuoc,
    string $maDuocSi,
    int $nguoiPhatUserId,
    array $itemsInput,
    ?string $clientRequestId = null,
    string $huongDanSuDung = '',
    string $ghiChu = ''
): array {
    $clientRequestId = $clientRequestId !== null ? trim($clientRequestId) : null;
    if ($clientRequestId === '') {
        $clientRequestId = null;
    }

    if ($clientRequestId !== null) {
        $replayStmt = $conn->prepare("
            SELECT maPhieuPhat, maPhieuCode, maDonThuoc, maLichKham, maBenhNhan, maDuocSi, loaiPhat, tongTienPhat, thoiGianPhat
            FROM phieu_phat_thuoc
            WHERE clientRequestId = ?
            LIMIT 1
            FOR UPDATE
        ");
        $replayStmt->bind_param('s', $clientRequestId);
        $replayStmt->execute();
        $existingVoucher = $replayStmt->get_result()->fetch_assoc();
        $replayStmt->close();

        if ($existingVoucher) {
            if ((int)$existingVoucher['maDonThuoc'] !== $maDonThuoc) {
                throw new RuntimeException('Mã yêu cầu phát thuốc đã được sử dụng cho đơn thuốc khác.', 409);
            }

            $dtStmt = $conn->prepare("SELECT trangThaiPhat FROM donthuoc WHERE maDonThuoc = ? LIMIT 1");
            $dtStmt->bind_param('i', $maDonThuoc);
            $dtStmt->execute();
            $trangThaiPhat = (string)($dtStmt->get_result()->fetch_assoc()['trangThaiPhat'] ?? '');
            $dtStmt->close();

            return [
                'idempotentReplay' => true,
                'trangThaiPhat' => $trangThaiPhat,
                'voucher' => [
                    'maPhieuPhat' => (int)$existingVoucher['maPhieuPhat'],
                    'maPhieuCode' => $existingVoucher['maPhieuCode'],
                    'maDonThuoc' => (int)$existingVoucher['maDonThuoc'],
                    'loaiPhat' => $existingVoucher['loaiPhat'],
                    'tongTienPhat' => (float)$existingVoucher['tongTienPhat'],
                    'maDuocSi' => $existingVoucher['maDuocSi'],
                    'thoiGianPhat' => $existingVoucher['thoiGianPhat'],
                ],
            ];
        }
    }

    $rxStmt = $conn->prepare("
        SELECT
            dt.maDonThuoc,
            dt.maLichKham,
            dt.trangThaiPhat,
            lk.maBenhNhan,
            hs.maHoSo
        FROM donthuoc dt
        JOIN lichkham lk ON dt.maLichKham = lk.maLichKham
        LEFT JOIN hosobenhan hs ON hs.maLichKham = lk.maLichKham AND hs.isDeleted = 0
        WHERE dt.maDonThuoc = ?
        LIMIT 1
        FOR UPDATE
    ");
    $rxStmt->bind_param('i', $maDonThuoc);
    $rxStmt->execute();
    $rx = $rxStmt->get_result()->fetch_assoc();
    $rxStmt->close();

    if (!$rx) {
        throw new RuntimeException('Không tìm thấy đơn thuốc.', 404);
    }

    $currentRxStatus = (string)($rx['trangThaiPhat'] ?? '');
    if (!in_array($currentRxStatus, ['Chờ phát', 'Đã phát một phần'], true)) {
        throw new RuntimeException('Đơn thuốc đang ở trạng thái "' . $currentRxStatus . '", không thể thực hiện phát thuốc.', 409);
    }

    // Chuẩn hóa danh sách số lượng phát theo chiTietDonThuocId
    $requestedByDetailId = [];
    foreach ($itemsInput as $item) {
        if (!is_array($item)) {
            continue;
        }
        $detailId = (int)($item['chiTietDonThuocId'] ?? 0);
        $qty = (int)($item['soLuongThucPhat'] ?? 0);
        if ($detailId <= 0 || $qty <= 0) {
            continue;
        }
        $requestedByDetailId[$detailId] = ($requestedByDetailId[$detailId] ?? 0) + $qty;
    }

    if (empty($requestedByDetailId)) {
        throw new RuntimeException('Vui lòng nhập số lượng thực phát lớn hơn 0 cho ít nhất một thuốc.', 422);
    }

    $detailStmt = $conn->prepare("
        SELECT id, maThuoc, soLuong, soLuongDaPhat
        FROM chitietdonthuoc
        WHERE maDonThuoc = ?
        ORDER BY maThuoc ASC, id ASC
        FOR UPDATE
    ");
    $detailStmt->bind_param('i', $maDonThuoc);
    $detailStmt->execute();
    $detailRows = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $detailStmt->close();

    $detailMap = [];
    foreach ($detailRows as $row) {
        $detailMap[(int)$row['id']] = $row;
    }

    foreach ($requestedByDetailId as $detailId => $qty) {
        if (!isset($detailMap[$detailId])) {
            throw new RuntimeException('Dòng chi tiết đơn thuốc #' . $detailId . ' không thuộc đơn thuốc này.', 422);
        }
        $ke = (int)($detailMap[$detailId]['soLuong'] ?? 0);
        $daPhat = (int)($detailMap[$detailId]['soLuongDaPhat'] ?? 0);
        $conLai = max(0, $ke - $daPhat);
        if ($qty > $conLai) {
            throw new RuntimeException('Số lượng phát cho dòng thuốc #' . $detailId . ' (' . $qty . ') vượt quá số lượng còn lại (' . $conLai . ').', 422);
        }
    }

    // Khóa và trừ kho theo thứ tự maThuoc tăng dần
    $medicineDemand = [];
    foreach ($requestedByDetailId as $detailId => $qty) {
        $maThuoc = (int)$detailMap[$detailId]['maThuoc'];
        $medicineDemand[$maThuoc] = ($medicineDemand[$maThuoc] ?? 0) + $qty;
    }
    ksort($medicineDemand);

    $medicineState = [];
    foreach ($medicineDemand as $maThuoc => $needQty) {
        $medStmt = $conn->prepare("
            SELECT maThuoc, tenThuoc, soLuongTon, giaTien, COALESCE(nguongCanhBao, 10) AS nguongCanhBao
            FROM thuoc
            WHERE maThuoc = ?
            LIMIT 1
            FOR UPDATE
        ");
        $medStmt->bind_param('i', $maThuoc);
        $medStmt->execute();
        $med = $medStmt->get_result()->fetch_assoc();
        $medStmt->close();

        if (!$med) {
            throw new RuntimeException('Thuốc #' . $maThuoc . ' không còn tồn tại trong hệ thống.', 404);
        }

        $stockBefore = (int)($med['soLuongTon'] ?? 0);
        if ($stockBefore < $needQty) {
            throw new RuntimeException('Thuốc "' . $med['tenThuoc'] . '" chỉ còn ' . $stockBefore . ' trong kho, không đủ để phát ' . $needQty . '.', 422);
        }

        $medicineState[$maThuoc] = [
            'tenThuoc' => $med['tenThuoc'],
            'giaTien' => (float)($med['giaTien'] ?? 0),
            'nguongCanhBao' => (int)$med['nguongCanhBao'],
            'runningStock' => $stockBefore,
        ];
    }

    // Kiểm tra sau lần phát này đơn đã phát đủ 100% chưa
    $allFulfilled = true;
    foreach ($detailRows as $row) {
        $id = (int)$row['id'];
        $ke = (int)($row['soLuong'] ?? 0);
        $daPhat = (int)($row['soLuongDaPhat'] ?? 0) + ($requestedByDetailId[$id] ?? 0);
        if ($daPhat < $ke) {
            $allFulfilled = false;
            break;
        }
    }

    $loaiPhat = $allFulfilled ? 'Đủ' : 'Một phần';
    $nextRxStatus = $allFulfilled ? 'Đã phát đủ' : 'Đã phát một phần';
    $maPhieuCode = 'PT' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $maLichKham = (int)$rx['maLichKham'];
    $maBenhNhan = (string)$rx['maBenhNhan'];
    $maHoSo = $rx['maHoSo'] ?? null;
    $huongDanParam = trim($huongDanSuDung) !== '' ? trim($huongDanSuDung) : null;
    $ghiChuParam = trim($ghiChu) !== '' ? trim($ghiChu) : null;

    $insVoucher = $conn->prepare("
        INSERT INTO phieu_phat_thuoc (
            maPhieuCode, maDonThuoc, maLichKham, maBenhNhan, maDuocSi, nguoiPhatUserId,
            clientRequestId, loaiPhat, trangThai, tongTienPhat, huongDanSuDung, ghiChu, thoiGianPhat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Hoàn thành', 0, ?, ?, NOW())
    ");
    $insVoucher->bind_param(
        'siississss',
        $maPhieuCode,
        $maDonThuoc,
        $maLichKham,
        $maBenhNhan,
        $maDuocSi,
        $nguoiPhatUserId,
        $clientRequestId,
        $loaiPhat,
        $huongDanParam,
        $ghiChuParam
    );
    if (!$insVoucher->execute()) {
        throw new RuntimeException('Không thể tạo phiếu phát thuốc: ' . $insVoucher->error);
    }
    $maPhieuPhat = (int)$conn->insert_id;
    $insVoucher->close();

    $tongTienPhat = 0.0;
    $dispensedItemsOut = [];
    $lowStockWarnings = [];

    foreach ($requestedByDetailId as $detailId => $qty) {
        $dRow = $detailMap[$detailId];
        $maThuoc = (int)$dRow['maThuoc'];
        $soLuongKe = (int)$dRow['soLuong'];
        $donGia = $medicineState[$maThuoc]['giaTien'];
        $thanhTien = round($donGia * $qty, 2);
        $tongTienPhat += $thanhTien;

        $tonTruoc = $medicineState[$maThuoc]['runningStock'];
        $tonSau = $tonTruoc - $qty;
        $medicineState[$maThuoc]['runningStock'] = $tonSau;

        $upDetail = $conn->prepare("
            UPDATE chitietdonthuoc
            SET soLuongDaPhat = soLuongDaPhat + ?
            WHERE id = ?
        ");
        $upDetail->bind_param('ii', $qty, $detailId);
        $upDetail->execute();
        $upDetail->close();

        $insItem = $conn->prepare("
            INSERT INTO phieu_phat_thuoc_chitiet (
                maPhieuPhat, chiTietDonThuocId, maThuoc, soLuongKe, soLuongThucPhat,
                donGia, thanhTien, tonTruocPhat, tonSauPhat
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insItem->bind_param(
            'iiiiiddii',
            $maPhieuPhat,
            $detailId,
            $maThuoc,
            $soLuongKe,
            $qty,
            $donGia,
            $thanhTien,
            $tonTruoc,
            $tonSau
        );
        if (!$insItem->execute()) {
            throw new RuntimeException('Không thể lưu chi tiết phiếu phát thuốc: ' . $insItem->error);
        }
        $insItem->close();

        $dispensedItemsOut[] = [
            'chiTietDonThuocId' => $detailId,
            'maThuoc' => $maThuoc,
            'tenThuoc' => $medicineState[$maThuoc]['tenThuoc'],
            'soLuongKe' => $soLuongKe,
            'soLuongThucPhat' => $qty,
            'donGia' => $donGia,
            'thanhTien' => $thanhTien,
            'tonTruocPhat' => $tonTruoc,
            'tonSauPhat' => $tonSau,
        ];
    }

    foreach ($medicineState as $maThuoc => $mState) {
        $newBalance = $mState['runningStock'];
        $deducted = $medicineDemand[$maThuoc];

        $upStock = $conn->prepare("UPDATE thuoc SET soLuongTon = ? WHERE maThuoc = ?");
        $upStock->bind_param('ii', $newBalance, $maThuoc);
        if (!$upStock->execute()) {
            throw new RuntimeException('Không thể trừ tồn kho thuốc: ' . $upStock->error);
        }
        $upStock->close();

        logMedicineStockChange(
            $conn,
            $maThuoc,
            $maLichKham,
            $maHoSo,
            -$deducted,
            $newBalance,
            'dispense',
            "Phat thuoc $maPhieuCode (DS: $maDuocSi)"
        );

        if ($newBalance <= $mState['nguongCanhBao']) {
            $lowStockWarnings[] = [
                'maThuoc' => $maThuoc,
                'tenThuoc' => $mState['tenThuoc'],
                'soLuongTon' => $newBalance,
                'nguongCanhBao' => $mState['nguongCanhBao'],
            ];
        }
    }

    $upVoucher = $conn->prepare("UPDATE phieu_phat_thuoc SET tongTienPhat = ? WHERE maPhieuPhat = ?");
    $upVoucher->bind_param('di', $tongTienPhat, $maPhieuPhat);
    $upVoucher->execute();
    $upVoucher->close();

    $upRx = $conn->prepare("UPDATE donthuoc SET trangThaiPhat = ? WHERE maDonThuoc = ?");
    $upRx->bind_param('si', $nextRxStatus, $maDonThuoc);
    $upRx->execute();
    $upRx->close();

    return [
        'idempotentReplay' => false,
        'trangThaiPhat' => $nextRxStatus,
        'lowStockMedicines' => $lowStockWarnings,
        'voucher' => [
            'maPhieuPhat' => $maPhieuPhat,
            'maPhieuCode' => $maPhieuCode,
            'maDonThuoc' => $maDonThuoc,
            'loaiPhat' => $loaiPhat,
            'tongTienPhat' => $tongTienPhat,
            'maDuocSi' => $maDuocSi,
            'items' => $dispensedItemsOut,
        ],
    ];
}
