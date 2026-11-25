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

function insertPatientNotification(mysqli $conn, string $maBenhNhan, string $loai, string $tieuDe, string $noiDung): bool
{
    if ($maBenhNhan === '' || $tieuDe === '' || $noiDung === '') {
        return false;
    }
    $allowedTypes = ['Hệ thống', 'Lịch khám', 'Mật khẩu', 'Khác'];
    $safeType = in_array($loai, $allowedTypes, true) ? $loai : 'Lịch khám';

    $stmt = $conn->prepare("
        INSERT INTO thongbaobenhnhan (maBenhNhan, loai, tieuDe, noiDung)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $maBenhNhan, $safeType, $tieuDe, $noiDung);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function notifyParaclinicalApproved(mysqli $conn, int $maChiTietChiDinh): void
{
    $stmt = $conn->prepare("
        SELECT
            cd.maBenhNhan,
            ct.maHoSo,
            dv.tenDichVu,
            bs.tenBacSi
        FROM chi_dinh_chi_tiet ct
        JOIN chi_dinh_can_lam_sang cd ON ct.maChiDinh = cd.maChiDinh
        JOIN dichvu_can_lam_sang dv ON ct.maDichVu = dv.maDichVu
        LEFT JOIN bacsi bs ON ct.maBacSiDuyet = bs.maBacSi
        WHERE ct.maChiTietChiDinh = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $maChiTietChiDinh);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['maBenhNhan'])) {
        return;
    }

    $title = 'Kết quả cận lâm sàng đã được duyệt';
    $msg = "Kết quả dịch vụ \"{$row['tenDichVu']}\" thuộc hồ sơ {$row['maHoSo']} đã được "
        . (!empty($row['tenBacSi']) ? "BS. {$row['tenBacSi']}" : 'bác sĩ')
        . ' duyệt. Bạn có thể xem chi tiết trong mục Hồ sơ bệnh án.';
    insertPatientNotification($conn, (string)$row['maBenhNhan'], 'Lịch khám', $title, $msg);
}

function notifyMedicineDispensed(mysqli $conn, int $maDonThuoc, string $trangThaiPhat, string $maPhieuCode): void
{
    $stmt = $conn->prepare("
        SELECT dt.maDonThuoc, lk.maBenhNhan
        FROM donthuoc dt
        JOIN lichkham lk ON dt.maLichKham = lk.maLichKham
        WHERE dt.maDonThuoc = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $maDonThuoc);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['maBenhNhan'])) {
        return;
    }

    $title = $trangThaiPhat === 'Đã phát đủ' ? 'Đơn thuốc đã được phát đủ' : 'Đơn thuốc đã được phát một phần';
    $msg = "Đơn thuốc #$maDonThuoc (phiếu $maPhieuCode) hiện ở trạng thái: $trangThaiPhat. Vui lòng xem hướng dẫn dùng thuốc trong Hồ sơ bệnh án.";
    insertPatientNotification($conn, (string)$row['maBenhNhan'], 'Lịch khám', $title, $msg);
}

function notifyInvoicePaid(mysqli $conn, int $maHoaDon, float $soTien, string $phuongThuc): void
{
    $stmt = $conn->prepare("
        SELECT lk.maBenhNhan, hd.maLichKham
        FROM hoadon hd
        JOIN lichkham lk ON hd.maLichKham = lk.maLichKham
        WHERE hd.maHoaDon = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $maHoaDon);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['maBenhNhan'])) {
        return;
    }

    $methodLabel = $phuongThuc === 'VNPAY' ? 'VNPAY' : 'Tiền mặt';
    $amountStr = number_format($soTien, 0, ',', '.') . 'đ';
    $title = "Thanh toán thành công hóa đơn #$maHoaDon";
    $msg = "Hóa đơn #$maHoaDon (lượt khám #{$row['maLichKham']}) số tiền $amountStr qua $methodLabel đã được xác nhận thanh toán.";
    insertPatientNotification($conn, (string)$row['maBenhNhan'], 'Lịch khám', $title, $msg);
}
