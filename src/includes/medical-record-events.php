<?php

function sendMedicalRecordCompletedNotification(mysqli $conn, string $maHoSo): array
{
    $sql = "
        SELECT
            hs.maHoSo,
            hs.maLichKham,
            hs.chanDoan,
            hs.ngayHoanThanh,
            hs.maBenhNhan,
            bn.tenBenhNhan,
            bs.tenBacSi,
            COALESCE(lk.ngayKham, hs.ngayKham) AS ngayKham
        FROM hosobenhan hs
        LEFT JOIN benhnhan bn ON hs.maBenhNhan = bn.maBenhNhan
        LEFT JOIN bacsi bs ON hs.maBacSi = bs.maBacSi
        LEFT JOIN lichkham lk ON hs.maLichKham = lk.maLichKham
        WHERE hs.maHoSo = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'reason' => 'prepare_failed'];
    }

    $stmt->bind_param('s', $maHoSo);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ctx || empty($ctx['maBenhNhan'])) {
        return ['success' => false, 'reason' => 'record_missing'];
    }

    $title = 'Hồ sơ khám bệnh đã hoàn tất';
    $parts = [];

    if (!empty($ctx['maLichKham'])) {
        $parts[] = 'Lịch khám #' . (int)$ctx['maLichKham'];
    }

    if (!empty($ctx['ngayKham'])) {
        $parts[] = 'ngày ' . date('d/m/Y', strtotime((string)$ctx['ngayKham']));
    }

    if (!empty($ctx['tenBacSi'])) {
        $parts[] = 'bác sĩ ' . $ctx['tenBacSi'];
    }

    $message = 'Hồ sơ ' . $ctx['maHoSo'] . ' của bạn đã được bác sĩ cập nhật hoàn tất.';
    if (!empty($parts)) {
        $message .= ' ' . implode(', ', $parts) . '.';
    }

    $chanDoan = trim((string)($ctx['chanDoan'] ?? ''));
    if ($chanDoan !== '') {
        $snippet = function_exists('mb_substr') ? mb_substr($chanDoan, 0, 180) : substr($chanDoan, 0, 180);
        $length = function_exists('mb_strlen') ? mb_strlen($chanDoan) : strlen($chanDoan);
        $message .= ' Chẩn đoán: ' . $snippet;
        if ($length > 180) {
            $message .= '...';
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO thongbaobenhnhan (maBenhNhan, loai, tieuDe, noiDung)
        VALUES (?, 'Lịch khám', ?, ?)
    ");
    if (!$stmt) {
        return ['success' => false, 'reason' => 'prepare_insert_failed'];
    }

    $stmt->bind_param('sss', $ctx['maBenhNhan'], $title, $message);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'reason' => 'insert_failed', 'message' => $error];
    }

    return ['success' => true];
}
