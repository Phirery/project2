<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/operational-report-helper.php';

require_role('quantri');
date_default_timezone_set('Asia/Ho_Chi_Minh');

$dateTo = trim((string)($_GET['dateTo'] ?? date('Y-m-d')));
$dateFrom = trim((string)($_GET['dateFrom'] ?? date('Y-m-d', strtotime('-6 days'))));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) || $dateFrom > $dateTo) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Khoảng ngày không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$report = admin_build_operational_report($conn, $dateFrom, $dateTo, 100);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bao-cao-van-hanh-' . $dateFrom . '_to_' . $dateTo . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

fputcsv($out, ['BÁO CÁO VẬN HÀNH PHÒNG KHÁM EDEN HEALTH']);
fputcsv($out, ['Khoảng ngày', $dateFrom . ' -> ' . $dateTo]);
fputcsv($out, ['Xuất lúc', date('d/m/Y H:i:s')]);
fputcsv($out, []);

fputcsv($out, ['1. CHỈ SỐ VẬN HÀNH THEO NGÀY']);
fputcsv($out, ['Ngày', 'Lượt check-in', 'Chờ khám TB (phút)', 'Số lượt CLS', 'Chờ CLS TB (phút)', 'Số đơn/phiếu phát thuốc', 'Doanh thu đã thu (VND)']);
foreach ($report['dailyMetrics'] as $row) {
    fputcsv($out, [
        $row['ngay'],
        $row['soLuotCheckIn'],
        $row['thoiGianChoKhamPhut'] !== null ? $row['thoiGianChoKhamPhut'] : 'N/A',
        $row['soLuotCLS'],
        $row['thoiGianChoClsPhut'] !== null ? $row['thoiGianChoClsPhut'] : 'N/A',
        $row['soDonPhat'],
        $row['doanhThuThuNgan'],
    ]);
}
fputcsv($out, []);

fputcsv($out, ['2. DANH SÁCH THUỐC CHẠM NGƯỠNG TỒN KHO (' . count($report['lowStockMedicines']) . ')']);
fputcsv($out, ['Mã thuốc', 'Tên thuốc', 'Đơn vị tính', 'Tồn kho hiện tại', 'Ngưỡng cảnh báo', 'Hạn sử dụng']);
foreach ($report['lowStockMedicines'] as $m) {
    fputcsv($out, [
        $m['maThuoc'],
        $m['tenThuoc'],
        $m['donViTinh'],
        $m['soLuongTon'],
        $m['nguongCanhBao'],
        $m['hanSuDung'] ?: '',
    ]);
}
fputcsv($out, []);

fputcsv($out, ['3. NHẬT KÝ AUDIT THAO TÁC QUAN TRỌNG']);
fputcsv($out, ['Thời gian', 'Phân hệ', 'Đối tượng', 'Trạng thái / Hành động', 'Người thao tác', 'Chi tiết']);
foreach ($report['auditTrail'] as $a) {
    fputcsv($out, [
        $a['thoiGian'],
        $a['phanHe'],
        $a['maDoiTuong'],
        $a['trangThai'],
        $a['nguoiThaoTac'],
        $a['chiTiet'],
    ]);
}

fclose($out);
$conn->close();
