<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('nhanvien');

/**
 * Check-in 1 lịch khám (đã tồn tại trong `lichkham`, dù đặt online hay vừa tạo walk-in)
 * vào hàng đợi khám của bác sĩ hôm nay. STT được tính riêng theo (maBacSi, ngày),
 * theo thứ tự check-in thực tế (FIFO) - dùng SELECT ... FOR UPDATE để tránh 2 nhân viên
 * check-in cùng lúc bị trùng STT.
 */

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$maLichKham = isset($data['maLichKham']) ? (int)$data['maLichKham'] : 0;
$ghiChu = trim($data['ghiChu'] ?? '');

if ($maLichKham <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã lịch khám'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $staffStmt = $conn->prepare("SELECT maNhanVien FROM nhanvien WHERE nguoiDungId = ?");
    $staffStmt->bind_param('i', $_SESSION['id']);
    $staffStmt->execute();
    $staffRow = $staffStmt->get_result()->fetch_assoc();
    $staffStmt->close();
    if (!$staffRow) {
        throw new Exception('Không xác định được nhân viên thực hiện');
    }
    $maNhanVienCheckIn = $staffRow['maNhanVien'];

    $conn->begin_transaction();

    // Khóa dòng lịch khám để tránh check-in trùng đồng thời
    $lichStmt = $conn->prepare("
        SELECT maLichKham, maBacSi, maBenhNhan, ngayKham, trangThai, nguon
        FROM lichkham WHERE maLichKham = ? FOR UPDATE
    ");
    $lichStmt->bind_param('i', $maLichKham);
    $lichStmt->execute();
    $lich = $lichStmt->get_result()->fetch_assoc();
    $lichStmt->close();

    if (!$lich) {
        throw new Exception('Không tìm thấy lịch khám');
    }
    if ($lich['ngayKham'] !== date('Y-m-d')) {
        throw new Exception('Chỉ có thể check-in lịch khám của hôm nay');
    }
    if ($lich['trangThai'] !== 'Đã đặt') {
        throw new Exception('Lịch khám không ở trạng thái "Đã đặt", không thể check-in');
    }

    $existsStmt = $conn->prepare("SELECT maHangDoi FROM hangdoikham WHERE maLichKham = ? FOR UPDATE");
    $existsStmt->bind_param('i', $maLichKham);
    $existsStmt->execute();
    $existing = $existsStmt->get_result()->fetch_assoc();
    $existsStmt->close();
    if ($existing) {
        throw new Exception('Lịch khám này đã được check-in trước đó');
    }

    $maBacSi = $lich['maBacSi'];
    $ngay = $lich['ngayKham'];

    // Khóa toàn bộ hàng đợi của bác sĩ hôm nay để tính STT tiếp theo an toàn
    $maxStmt = $conn->prepare("
        SELECT COALESCE(MAX(soThuTu), 0) AS maxStt
        FROM hangdoikham WHERE maBacSi = ? AND ngay = ? FOR UPDATE
    ");
    $maxStmt->bind_param('ss', $maBacSi, $ngay);
    $maxStmt->execute();
    $soThuTu = (int)$maxStmt->get_result()->fetch_assoc()['maxStt'] + 1;
    $maxStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO hangdoikham (maLichKham, maBacSi, ngay, soThuTu, trangThai, nguon, maNhanVienCheckIn, ghiChu)
        VALUES (?, ?, ?, ?, 'Đang chờ', ?, ?, ?)
    ");
    $insertStmt->bind_param('ississs', $maLichKham, $maBacSi, $ngay, $soThuTu, $lich['nguon'], $maNhanVienCheckIn, $ghiChu);
    if (!$insertStmt->execute()) {
        throw new Exception('Không thể check-in: ' . $insertStmt->error);
    }
    $maHangDoi = (int)$conn->insert_id;
    $insertStmt->close();

    // Số người đang chờ trước bệnh nhân này (để hiển thị vị trí)
    $aheadStmt = $conn->prepare("
        SELECT COUNT(*) AS soNguoiTruoc
        FROM hangdoikham
        WHERE maBacSi = ? AND ngay = ? AND trangThai IN ('Đang chờ', 'Đang khám') AND soThuTu < ?
    ");
    $aheadStmt->bind_param('ssi', $maBacSi, $ngay, $soThuTu);
    $aheadStmt->execute();
    $soNguoiTruoc = (int)$aheadStmt->get_result()->fetch_assoc()['soNguoiTruoc'];
    $aheadStmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Check-in thành công!',
        'maHangDoi' => $maHangDoi,
        'soThuTu' => $soThuTu,
        'soNguoiTruoc' => $soNguoiTruoc
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>