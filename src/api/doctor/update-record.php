<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';
require_once '../../includes/medical-record-events.php';
require_once '../../includes/medicine-stock.php';

require_role('bacsi');

$input = json_decode(file_get_contents('php://input'), true);
$maHoSo = $input['maHoSo'] ?? '';
$chanDoan = trim((string)($input['chanDoan'] ?? ''));
$dieuTri = trim((string)($input['dieuTri'] ?? ''));
$ghiChu = trim((string)($input['ghiChu'] ?? ''));
$loiDanBacSi = trim((string)($input['loiDanBacSi'] ?? ''));
$medicines = is_array($input['medicines'] ?? null) ? $input['medicines'] : [];
$action = strtolower(trim((string)($input['action'] ?? 'complete')));
$isDraftSave = $action === 'draft';

if (!$maHoSo) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã hồ sơ']);
    exit;
}

if (!$isDraftSave && (!$chanDoan || !$dieuTri)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

$transactionStarted = false;

function normalizePrescriptionItems(array $medicines): array {
    $normalized = [];

    foreach ($medicines as $item) {
        if (!is_array($item)) {
            continue;
        }

        $maThuoc = (int)($item['maThuoc'] ?? 0);
        $soLuong = (int)($item['soLuong'] ?? 0);
        $lieuDung = trim((string)($item['lieuDung'] ?? ''));

        if ($maThuoc <= 0 || $soLuong <= 0) {
            continue;
        }

        $normalized[] = [
            'maThuoc' => $maThuoc,
            'soLuong' => $soLuong,
            'lieuDung' => $lieuDung
        ];
    }

    return $normalized;
}

function syncPrescriptionForRecord(mysqli $conn, int $maLichKham, string $chanDoan, string $loiDanBacSi, array $medicines): void
{
    $normalizedMedicines = normalizePrescriptionItems($medicines);

    $stmt = $conn->prepare("SELECT maDonThuoc FROM donthuoc WHERE maLichKham = ?");
    $stmt->bind_param("i", $maLichKham);
    $stmt->execute();
    $existingPrescriptionIds = [];
    $prescriptionResult = $stmt->get_result();

    while ($prescriptionRow = $prescriptionResult->fetch_assoc()) {
        $existingPrescriptionIds[] = (int)$prescriptionRow['maDonThuoc'];
    }
    $stmt->close();

    foreach ($existingPrescriptionIds as $prescriptionId) {
        $stmt = $conn->prepare("DELETE FROM chitietdonthuoc WHERE maDonThuoc = ?");
        $stmt->bind_param("i", $prescriptionId);
        if (!$stmt->execute()) {
            throw new Exception('Không thể xóa chi tiết toa thuốc cũ: ' . $stmt->error);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM donthuoc WHERE maLichKham = ?");
    $stmt->bind_param("i", $maLichKham);
    if (!$stmt->execute()) {
        throw new Exception('Không thể đồng bộ toa thuốc: ' . $stmt->error);
    }
    $stmt->close();

    if (empty($normalizedMedicines) && $loiDanBacSi === '') {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO donthuoc (maLichKham, chuanDoan, loiDanBacSi, ngayKeDon, tongTienThuoc)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->bind_param("iss", $maLichKham, $chanDoan, $loiDanBacSi);
    if (!$stmt->execute()) {
        throw new Exception('Không thể tạo toa thuốc: ' . $stmt->error);
    }
    $maDonThuoc = (int)$conn->insert_id;
    $stmt->close();

    $tongTienThuoc = 0;

    foreach ($normalizedMedicines as $medicine) {
        $stmt = $conn->prepare("SELECT giaTien FROM thuoc WHERE maThuoc = ? LIMIT 1");
        $stmt->bind_param("i", $medicine['maThuoc']);
        $stmt->execute();
        $medicineInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$medicineInfo) {
            throw new Exception('Một hoặc nhiều thuốc không còn tồn tại trong hệ thống.');
        }

        $giaTien = (float)($medicineInfo['giaTien'] ?? 0);
        $tongTienThuoc += $giaTien * $medicine['soLuong'];

        $stmt = $conn->prepare("
            INSERT INTO chitietdonthuoc (maDonThuoc, maThuoc, soLuong, lieuDung)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $maDonThuoc, $medicine['maThuoc'], $medicine['soLuong'], $medicine['lieuDung']);
        if (!$stmt->execute()) {
            throw new Exception('Không thể lưu chi tiết toa thuốc: ' . $stmt->error);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE donthuoc SET tongTienThuoc = ? WHERE maDonThuoc = ?");
    $stmt->bind_param("di", $tongTienThuoc, $maDonThuoc);
    if (!$stmt->execute()) {
        throw new Exception('Không thể cập nhật tổng tiền toa thuốc: ' . $stmt->error);
    }
    $stmt->close();
}

try {
    $stmt = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $maBacSi = $stmt->get_result()->fetch_assoc()['maBacSi'] ?? null;
    $stmt->close();

    if (!$maBacSi) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        exit;
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        SELECT maLichKham, trangThai
        FROM hosobenhan
        WHERE maHoSo = ? AND maBacSi = ? AND isDeleted = 0
        LIMIT 1
    ");
    $stmt->bind_param("ss", $maHoSo, $maBacSi);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ hoặc không có quyền']);
        exit;
    }

    if ($isDraftSave && ($record['trangThai'] ?? '') === 'Đã hoàn thành') {
        $conn->rollback();
        $transactionStarted = false;
        echo json_encode(['success' => false, 'message' => 'Không thể chuyển hồ sơ đã hoàn thành về trạng thái nháp']);
        exit;
    }

    $existingPrescriptionItems = [];
    $lowStockWarnings = [];
    $recordStatus = (string)($record['trangThai'] ?? '');
    $wasCompleted = $recordStatus === 'Đã hoàn thành';

    if (!empty($record['maLichKham'])) {
        $existingPrescriptionItems = getPrescriptionItemsByAppointment($conn, (int)$record['maLichKham']);
    }

    $normalizedMedicines = normalizePrescriptionItems($medicines);

    if ($isDraftSave) {
        $stmt = $conn->prepare("
            UPDATE hosobenhan
            SET chanDoan = ?, dieuTri = ?, ghiChu = ?, trangThai = 'Chưa hoàn thành', ngayHoanThanh = NULL
            WHERE maHoSo = ? AND maBacSi = ? AND isDeleted = 0
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE hosobenhan
            SET chanDoan = ?, dieuTri = ?, ghiChu = ?, trangThai = 'Đã hoàn thành', ngayHoanThanh = NOW()
            WHERE maHoSo = ? AND maBacSi = ? AND isDeleted = 0
        ");
    }
    $stmt->bind_param("sssss", $chanDoan, $dieuTri, $ghiChu, $maHoSo, $maBacSi);

    if (!$stmt->execute()) {
        throw new Exception('Không thể cập nhật hồ sơ: ' . $stmt->error);
    }
    $stmt->close();

    if (!empty($record['maLichKham'])) {
        $lowStockWarnings = applyPrescriptionStockDelta(
            $conn,
            (int)$record['maLichKham'],
            $maHoSo,
            $existingPrescriptionItems,
            $normalizedMedicines,
            $wasCompleted,
            !$isDraftSave
        );

        syncPrescriptionForRecord($conn, (int)$record['maLichKham'], $chanDoan, $loiDanBacSi, $normalizedMedicines);

        if (!$isDraftSave) {
            $stmt = $conn->prepare("UPDATE lichkham SET trangThai = 'Hoàn thành' WHERE maLichKham = ? AND maBacSi = ? AND trangThai <> 'Hủy'");
            $stmt->bind_param("is", $record['maLichKham'], $maBacSi);

            if (!$stmt->execute()) {
                throw new Exception('Không thể đồng bộ lịch khám: ' . $stmt->error);
            }
            $stmt->close();

            // Đồng bộ hàng đợi khám (nếu lịch này có check-in) sang Hoàn thành.
            // Chỉ áp dụng khi hoàn tất thật sự - không đụng tới khi lưu nháp,
            // để bác sĩ vẫn xử lý được nhiều hồ sơ/bệnh nhân cùng lúc.
            $stmt = $conn->prepare("
                UPDATE hangdoikham
                SET trangThai = 'Hoàn thành', thoiGianHoanThanh = NOW()
                WHERE maLichKham = ? AND maBacSi = ? AND trangThai <> 'Hủy'
            ");
            $stmt->bind_param("is", $record['maLichKham'], $maBacSi);

            if (!$stmt->execute()) {
                throw new Exception('Không thể đồng bộ hàng đợi khám: ' . $stmt->error);
            }
            $stmt->close();
        }
    }

    $conn->commit();
    $transactionStarted = false;

    $mailStatus = ['attempted' => false, 'success' => false, 'reason' => null];
    $notificationStatus = ['attempted' => false, 'success' => false, 'reason' => null];

    if (!$isDraftSave && ($record['trangThai'] ?? '') !== 'Đã hoàn thành') {
        try {
            $notificationStatus['attempted'] = true;
            $notificationResult = sendMedicalRecordCompletedNotification($conn, $maHoSo);
            $notificationStatus['success'] = (bool)($notificationResult['success'] ?? false);
            $notificationStatus['reason'] = $notificationResult['reason'] ?? ($notificationResult['message'] ?? null);
        } catch (Throwable $notificationError) {
            $notificationStatus['attempted'] = true;
            $notificationStatus['reason'] = $notificationError->getMessage();
            error_log('Medical record notification error: ' . $notificationError->getMessage());
        }

        try {
            $mailStatus['attempted'] = true;
            $mailResult = sendMedicalRecordCompletedEmail($conn, $maHoSo);
            $mailStatus['success'] = (bool)($mailResult['success'] ?? false);
            $mailStatus['reason'] = $mailResult['reason'] ?? ($mailResult['message'] ?? null);
        } catch (Throwable $mailError) {
            $mailStatus['attempted'] = true;
            $mailStatus['reason'] = $mailError->getMessage();
            error_log('Medical record mail error: ' . $mailError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $isDraftSave ? 'Đã lưu nháp hồ sơ' : 'Hoàn tất hồ sơ thành công',
        'recordStatus' => $isDraftSave ? 'Chưa hoàn thành' : 'Đã hoàn thành',
        'lowStockMedicines' => $lowStockWarnings,
        'mailStatus' => $mailStatus,
        'notificationStatus' => $notificationStatus
    ]);
} catch (Exception $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

$conn->close();
?>