<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('quantri');

$data = json_decode(file_get_contents('php://input'), true);

$maLichKham = isset($data['maLichKham']) ? (int)$data['maLichKham'] : 0;
$maBenhNhan = trim($data['maBenhNhan'] ?? '');
$maBacSi = trim($data['maBacSi'] ?? '');
$ngayKham = trim($data['ngayKham'] ?? '');
$maCa = isset($data['maCa']) ? (int)$data['maCa'] : 0;
$maSuat = isset($data['maSuat']) ? (int)$data['maSuat'] : 0;
$maGoi = isset($data['maGoi']) && $data['maGoi'] !== '' ? (int)$data['maGoi'] : null;
$trangThai = trim($data['trangThai'] ?? '');
$ghiChu = trim($data['ghiChu'] ?? '');
$lyDoHuy = trim($data['lyDoHuy'] ?? '');

if (
    $maLichKham <= 0 ||
    $maBenhNhan === '' ||
    $maBacSi === '' ||
    $ngayKham === '' ||
    $maCa <= 0 ||
    $maSuat <= 0 ||
    $trangThai === ''
) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Lấy snapshot cũ để xác định có đổi lịch/hủy hay không
    $oldStmt = $conn->prepare(
        "SELECT
            lk.maLichKham,
            lk.maBacSi,
            lk.ngayKham,
            lk.maCa,
            lk.maSuat,
            lk.maGoi,
            lk.trangThai,
            lk.ghiChu,
            bs.tenBacSi,
            TIME_FORMAT(sk.gioBatDau, '%H:%i') AS gioBatDau,
            TIME_FORMAT(sk.gioKetThuc, '%H:%i') AS gioKetThuc,
            ca.tenCa
         FROM lichkham lk
         LEFT JOIN bacsi bs ON lk.maBacSi = bs.maBacSi
         LEFT JOIN suatkham sk ON lk.maSuat = sk.maSuat
         LEFT JOIN calamviec ca ON lk.maCa = ca.maCa
         WHERE lk.maLichKham = ?
         LIMIT 1"
    );
    $oldStmt->bind_param('i', $maLichKham);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    if (!$oldRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy lịch khám'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $oldSnapshot = extractAppointmentSnapshotFromDbRow($oldRow);

    $finalGhiChu = $ghiChu !== '' ? $ghiChu : null;

    if ($trangThai === 'Hủy' && $lyDoHuy !== '') {
        $reasonLine = '[Lý do hủy]: ' . $lyDoHuy;
        if ($finalGhiChu === null || $finalGhiChu === '') {
            $finalGhiChu = $reasonLine;
        } elseif (strpos($finalGhiChu, $reasonLine) === false) {
            $finalGhiChu .= "\n" . $reasonLine;
        }
    }

    if ($maGoi === null) {
        $updateSql = "UPDATE lichkham
                      SET maBenhNhan = ?,
                          maBacSi = ?,
                          ngayKham = ?,
                          maCa = ?,
                          maSuat = ?,
                          maGoi = NULL,
                          trangThai = ?,
                          ghiChu = ?,
                          nguoiHuy = ?
                      WHERE maLichKham = ?";
    } else {
        $updateSql = "UPDATE lichkham
                      SET maBenhNhan = ?,
                          maBacSi = ?,
                          ngayKham = ?,
                          maCa = ?,
                          maSuat = ?,
                          maGoi = ?,
                          trangThai = ?,
                          ghiChu = ?,
                          nguoiHuy = ?
                      WHERE maLichKham = ?";
    }

    $nguoiHuy = null;
    if ($trangThai === 'Hủy') {
        $nguoiHuy = 'quantri';
    }

    $stmt = $conn->prepare($updateSql);
    if ($maGoi === null) {
        $stmt->bind_param(
            'sssiisssi',
            $maBenhNhan,
            $maBacSi,
            $ngayKham,
            $maCa,
            $maSuat,
            $trangThai,
            $finalGhiChu,
            $nguoiHuy,
            $maLichKham
        );
    } else {
        $stmt->bind_param(
            'sssiiisssi',
            $maBenhNhan,
            $maBacSi,
            $ngayKham,
            $maCa,
            $maSuat,
            $maGoi,
            $trangThai,
            $finalGhiChu,
            $nguoiHuy,
            $maLichKham
        );
    }
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Không có thay đổi dữ liệu'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isCancellation = ($oldRow['trangThai'] !== 'Hủy' && $trangThai === 'Hủy');
    $isRescheduled = (
        $oldRow['trangThai'] !== 'Hủy' &&
        $trangThai !== 'Hủy' &&
        (
            (string)$oldRow['maBacSi'] !== $maBacSi ||
            (string)$oldRow['ngayKham'] !== $ngayKham ||
            (int)$oldRow['maCa'] !== $maCa ||
            (int)$oldRow['maSuat'] !== $maSuat ||
            (int)($oldRow['maGoi'] ?? 0) !== (int)($maGoi ?? 0)
        )
    );

    try {
        if ($isCancellation) {
            $cancelReason = $lyDoHuy !== '' ? $lyDoHuy : ($finalGhiChu ?? 'Không có lý do cụ thể');
            sendAppointmentCancelledEmails($conn, $maLichKham, 'quantri', $cancelReason);
        } elseif ($isRescheduled) {
            sendAppointmentRescheduledEmails($conn, $maLichKham, $oldSnapshot, 'quantri');
        }
    } catch (Throwable $mailError) {
        error_log('Admin update appointment mail error: ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật lịch khám thành công!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
