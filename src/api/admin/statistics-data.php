<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';

require_role('quantri');

$filter = $_GET['filter'] ?? 'month';
$dateFromInput = $_GET['dateFrom'] ?? null;
$dateToInput = $_GET['dateTo'] ?? null;

// Thống nhất điều kiện ngày tháng
$conditions = getDateConditions($filter, $dateFromInput, $dateToInput);
$dateCondition = $conditions['current'];
$previousDateCondition = $conditions['previous'];

$summary = getSummaryData($conn, $dateCondition, $previousDateCondition);
$appointmentsTrend = getAppointmentsTrend($conn, $filter, $conditions);
$patientsTrend = getPatientsTrend($conn, $filter, $conditions);
$departmentsData = getDepartmentsData($conn, $dateCondition);
$statusData = getStatusData($conn, $dateCondition);
$revenueTrend = getRevenueTrend($conn, $filter, $conditions, false); // Ước tính
$revenueTrendActual = getRevenueTrend($conn, $filter, $conditions, true); // Thực tế (bao gồm thuốc)
$topDoctors = getTopDoctors($conn, $dateCondition);

echo json_encode([
    'success' => true,
    'data' => [
        'summary' => $summary,
        'appointmentsTrend' => $appointmentsTrend,
        'patientsTrend' => $patientsTrend,
        'departmentsData' => $departmentsData,
        'statusData' => $statusData,
        'revenueTrend' => $revenueTrend,
        'revenueTrendActual' => $revenueTrendActual,
        'topDoctors' => $topDoctors
    ]
], JSON_UNESCAPED_UNICODE);

$conn->close();

function getDateConditions($filter, $dateFrom, $dateTo) {
    $current = "1=1";
    $previous = "1=1";
    $start = null;
    $end = null;

    switch($filter) {
        case 'week':
            $start = date('Y-m-d', strtotime('monday this week'));
            $end = date('Y-m-d', strtotime('sunday this week'));
            $current = "ngayKham BETWEEN '$start' AND '$end'";
            
            $pStart = date('Y-m-d', strtotime('monday last week'));
            $pEnd = date('Y-m-d', strtotime('sunday last week'));
            $previous = "ngayKham BETWEEN '$pStart' AND '$pEnd'";
            break;
            
        case 'month':
            $start = date('Y-m-01');
            $end = date('Y-m-t');
            $current = "ngayKham BETWEEN '$start' AND '$end'";
            
            $pStart = date('Y-m-01', strtotime('first day of last month'));
            $pEnd = date('Y-m-t', strtotime('last day of last month'));
            $previous = "ngayKham BETWEEN '$pStart' AND '$pEnd'";
            break;
            
        case 'year':
            $year = date('Y');
            $start = "$year-01-01";
            $end = "$year-12-31";
            $current = "YEAR(ngayKham) = $year";
            
            $pYear = $year - 1;
            $previous = "YEAR(ngayKham) = $pYear";
            break;
            
        case 'custom':
            if ($dateFrom && $dateTo) {
                $start = $dateFrom;
                $end = $dateTo;
                $current = "ngayKham BETWEEN '$start' AND '$end'";
            }
            break;
            
        case 'all':
            $current = "1=1";
            break;
    }

    return [
        'current' => $current,
        'previous' => $previous,
        'start' => $start,
        'end' => $end
    ];
}

function getSummaryData($conn, $dateCondition, $previousDateCondition) {
    // Lịch khám
    $currentAppointments = $conn->query("SELECT COUNT(*) as count FROM lichkham WHERE $dateCondition")->fetch_assoc()['count'];
    $previousAppointments = $conn->query("SELECT COUNT(*) as count FROM lichkham WHERE $previousDateCondition")->fetch_assoc()['count'];
    
    // Bệnh nhân trong kỳ (Unique)
    $currentPatients = $conn->query("SELECT COUNT(DISTINCT maBenhNhan) as count FROM lichkham WHERE $dateCondition")->fetch_assoc()['count'];
    
    // Bác sĩ (Tổng số)
    $totalDoctors = $conn->query("SELECT COUNT(*) as count FROM bacsi")->fetch_assoc()['count'];
    
    // Doanh thu thực tế (Dịch vụ + Thuốc) - Chỉ trạng thái Hoàn thành
    $sqlActual = "SELECT SUM(gk.gia + COALESCE(dt.tongTienThuoc, 0)) as total 
                  FROM lichkham lk 
                  LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                  LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                  WHERE lk.trangThai = 'Hoàn thành' AND $dateCondition";
    $revenueActual = (float)$conn->query($sqlActual)->fetch_assoc()['total'];
    
    // Doanh thu ước tính (Dịch vụ + Thuốc nếu có) - Trạng thái Đã đặt & Hoàn thành
    $sqlEstimated = "SELECT SUM(gk.gia + COALESCE(dt.tongTienThuoc, 0)) as total 
                     FROM lichkham lk 
                     LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                     LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                     WHERE lk.trangThai IN ('Đã đặt', 'Hoàn thành') AND $dateCondition";
    $revenueEstimated = (float)$conn->query($sqlEstimated)->fetch_assoc()['total'];
    
    // Doanh thu kỳ trước để tính % thay đổi
    $sqlPrevRevenue = "SELECT SUM(gk.gia + COALESCE(dt.tongTienThuoc, 0)) as total 
                       FROM lichkham lk 
                       LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                       LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                       WHERE lk.trangThai IN ('Đã đặt', 'Hoàn thành') AND $previousDateCondition";
    $previousRevenue = (float)$conn->query($sqlPrevRevenue)->fetch_assoc()['total'];
    
    $appointmentChange = $previousAppointments > 0 ? (($currentAppointments - $previousAppointments) / $previousAppointments) * 100 : 0;
    $revenueChange = $previousRevenue > 0 ? (($revenueEstimated - $previousRevenue) / $previousRevenue) * 100 : 0;
    
    return [
        'appointments' => (int)$currentAppointments,
        'patients' => (int)$currentPatients,
        'doctors' => (int)$totalDoctors,
        'revenueActual' => $revenueActual,
        'revenueEstimated' => $revenueEstimated,
        'appointmentChange' => round($appointmentChange, 1),
        'revenueChange' => round($revenueChange, 1)
    ];
}

function getAppointmentsTrend($conn, $filter, $conditions) {
    $dateCondition = $conditions['current'];
    $labels = [];
    $values = [];
    
    if ($filter == 'week') {
        $sql = "SELECT ngayKham, COUNT(*) as count FROM lichkham WHERE $dateCondition GROUP BY ngayKham ORDER BY ngayKham";
        $result = $conn->query($sql);
        $dataMap = [];
        while($row = $result->fetch_assoc()) $dataMap[$row['ngayKham']] = $row['count'];
        
        $dayNames = ['Monday'=>'T2','Tuesday'=>'T3','Wednesday'=>'T4','Thursday'=>'T5','Friday'=>'T6','Saturday'=>'T7','Sunday'=>'CN'];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($conditions['start'] . " +$i days"));
            $labels[] = $dayNames[date('l', strtotime($date))];
            $values[] = (int)($dataMap[$date] ?? 0);
        }
    } elseif ($filter == 'month') {
        // Gom nhóm theo tuần trong tháng
        $sql = "SELECT FLOOR((DAY(ngayKham)-1)/7) + 1 as week_num, COUNT(*) as count 
                FROM lichkham WHERE $dateCondition GROUP BY week_num ORDER BY week_num";
        $result = $conn->query($sql);
        $weeks = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
        while($row = $result->fetch_assoc()) $weeks[(int)$row['week_num']] = (int)$row['count'];
        
        for($i=1; $i<=5; $i++) {
            $labels[] = "Tuần $i";
            $values[] = $weeks[$i];
        }
    } elseif ($filter == 'year') {
        $sql = "SELECT MONTH(ngayKham) as month, COUNT(*) as count FROM lichkham WHERE $dateCondition GROUP BY month ORDER BY month";
        $result = $conn->query($sql);
        $months = array_fill(1, 12, 0);
        while($row = $result->fetch_assoc()) $months[(int)$row['month']] = (int)$row['count'];
        
        for($i=1; $i<=12; $i++) {
            $labels[] = "T$i";
            $values[] = $months[$i];
        }
    } elseif ($filter == 'all') {
        $sql = "SELECT YEAR(ngayKham) as year, COUNT(*) as count FROM lichkham GROUP BY year ORDER BY year";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            $labels[] = "Năm " . $row['year'];
            $values[] = (int)$row['count'];
        }
    } else { // Custom
        $diff = (strtotime($conditions['end']) - strtotime($conditions['start'])) / 86400;
        if ($diff <= 60) {
            $sql = "SELECT ngayKham, COUNT(*) as count FROM lichkham WHERE $dateCondition GROUP BY ngayKham ORDER BY ngayKham";
            $result = $conn->query($sql);
            while($row = $result->fetch_assoc()) {
                $labels[] = date('d/m', strtotime($row['ngayKham']));
                $values[] = (int)$row['count'];
            }
        } else {
            $sql = "SELECT DATE_FORMAT(ngayKham, '%Y-%m') as month, COUNT(*) as count FROM lichkham WHERE $dateCondition GROUP BY month ORDER BY month";
            $result = $conn->query($sql);
            while($row = $result->fetch_assoc()) {
                $labels[] = $row['month'];
                $values[] = (int)$row['count'];
            }
        }
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getPatientsTrend($conn, $filter, $conditions) {
    $dateCondition = $conditions['current'];
    $labels = [];
    $values = [];
    
    // Logic tương tự AppointmentsTrend nhưng dùng COUNT(DISTINCT maBenhNhan)
    if ($filter == 'week') {
        $sql = "SELECT ngayKham, COUNT(DISTINCT maBenhNhan) as count FROM lichkham WHERE $dateCondition GROUP BY ngayKham ORDER BY ngayKham";
        $result = $conn->query($sql);
        $dataMap = [];
        while($row = $result->fetch_assoc()) $dataMap[$row['ngayKham']] = $row['count'];
        $dayNames = ['Monday'=>'T2','Tuesday'=>'T3','Wednesday'=>'T4','Thursday'=>'T5','Friday'=>'T6','Saturday'=>'T7','Sunday'=>'CN'];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($conditions['start'] . " +$i days"));
            $labels[] = $dayNames[date('l', strtotime($date))];
            $values[] = (int)($dataMap[$date] ?? 0);
        }
    } elseif ($filter == 'month') {
        $sql = "SELECT FLOOR((DAY(ngayKham)-1)/7) + 1 as week_num, COUNT(DISTINCT maBenhNhan) as count FROM lichkham WHERE $dateCondition GROUP BY week_num ORDER BY week_num";
        $result = $conn->query($sql);
        $weeks = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
        while($row = $result->fetch_assoc()) $weeks[(int)$row['week_num']] = (int)$row['count'];
        for($i=1; $i<=5; $i++) { $labels[] = "Tuần $i"; $values[] = $weeks[$i]; }
    } elseif ($filter == 'year') {
        $sql = "SELECT MONTH(ngayKham) as month, COUNT(DISTINCT maBenhNhan) as count FROM lichkham WHERE $dateCondition GROUP BY month ORDER BY month";
        $result = $conn->query($sql);
        $months = array_fill(1, 12, 0);
        while($row = $result->fetch_assoc()) $months[(int)$row['month']] = (int)$row['count'];
        for($i=1; $i<=12; $i++) { $labels[] = "T$i"; $values[] = $months[$i]; }
    } elseif ($filter == 'all') {
        $sql = "SELECT YEAR(ngayKham) as year, COUNT(DISTINCT maBenhNhan) as count FROM lichkham GROUP BY year ORDER BY year";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            $labels[] = "Năm " . $row['year'];
            $values[] = (int)$row['count'];
        }
    } else { // Custom
        $diff = (strtotime($conditions['end']) - strtotime($conditions['start'])) / 86400;
        $groupBy = ($diff <= 60) ? "ngayKham" : "DATE_FORMAT(ngayKham, '%Y-%m')";
        $sql = "SELECT $groupBy as time_label, COUNT(DISTINCT maBenhNhan) as count FROM lichkham WHERE $dateCondition GROUP BY time_label ORDER BY time_label";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            $labels[] = ($diff <= 60) ? date('d/m', strtotime($row['time_label'])) : $row['time_label'];
            $values[] = (int)$row['count'];
        }
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getRevenueTrend($conn, $filter, $conditions, $isActual = false) {
    $dateCondition = $conditions['current'];
    $statusCondition = $isActual ? "lk.trangThai = 'Hoàn thành'" : "lk.trangThai IN ('Đã đặt', 'Hoàn thành')";
    $labels = [];
    $values = [];
    
    $selectRevenue = "SUM(gk.gia + COALESCE(dt.tongTienThuoc, 0))";

    if ($filter == 'week') {
        $sql = "SELECT lk.ngayKham, $selectRevenue as total 
                FROM lichkham lk 
                LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                WHERE $statusCondition AND $dateCondition GROUP BY lk.ngayKham";
        $result = $conn->query($sql);
        $dataMap = [];
        while($row = $result->fetch_assoc()) $dataMap[$row['ngayKham']] = (float)$row['total'];
        
        $dayNames = ['Monday'=>'T2','Tuesday'=>'T3','Wednesday'=>'T4','Thursday'=>'T5','Friday'=>'T6','Saturday'=>'T7','Sunday'=>'CN'];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($conditions['start'] . " +$i days"));
            $labels[] = $dayNames[date('l', strtotime($date))];
            $values[] = $dataMap[$date] ?? 0;
        }
    } elseif ($filter == 'month') {
        $sql = "SELECT FLOOR((DAY(lk.ngayKham)-1)/7) + 1 as week_num, $selectRevenue as total 
                FROM lichkham lk 
                LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                WHERE $statusCondition AND $dateCondition GROUP BY week_num ORDER BY week_num";
        $result = $conn->query($sql);
        $weeks = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
        while($row = $result->fetch_assoc()) $weeks[(int)$row['week_num']] = (float)$row['total'];
        for($i=1; $i<=5; $i++) { $labels[] = "Tuần $i"; $values[] = $weeks[$i]; }
    } elseif ($filter == 'year') {
        $sql = "SELECT MONTH(lk.ngayKham) as month, $selectRevenue as total 
                FROM lichkham lk 
                LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                WHERE $statusCondition AND $dateCondition GROUP BY month ORDER BY month";
        $result = $conn->query($sql);
        $months = array_fill(1, 12, 0);
        while($row = $result->fetch_assoc()) $months[(int)$row['month']] = (float)$row['total'];
        for($i=1; $i<=12; $i++) { $labels[] = "T$i"; $values[] = $months[$i]; }
    } elseif ($filter == 'all') {
        $sql = "SELECT YEAR(lk.ngayKham) as year, $selectRevenue as total 
                FROM lichkham lk 
                LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                WHERE $statusCondition GROUP BY year ORDER BY year";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            $labels[] = "Năm " . $row['year'];
            $values[] = (float)$row['total'];
        }
    } else { // Custom
        $diff = (strtotime($conditions['end']) - strtotime($conditions['start'])) / 86400;
        $groupBy = ($diff <= 60) ? "lk.ngayKham" : "DATE_FORMAT(lk.ngayKham, '%Y-%m')";
        $sql = "SELECT $groupBy as time_label, $selectRevenue as total 
                FROM lichkham lk 
                LEFT JOIN goikham gk ON lk.maGoi = gk.maGoi 
                LEFT JOIN donthuoc dt ON lk.maLichKham = dt.maLichKham
                WHERE $statusCondition AND $dateCondition GROUP BY time_label ORDER BY time_label";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            $labels[] = ($diff <= 60) ? date('d/m', strtotime($row['time_label'])) : $row['time_label'];
            $values[] = (float)$row['total'];
        }
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getDepartmentsData($conn, $dateCondition) {
    $sql = "SELECT k.tenKhoa, COUNT(lk.maLichKham) as count
            FROM khoa k
            LEFT JOIN chuyenkhoa ck ON k.maKhoa = ck.maKhoa
            LEFT JOIN bacsi bs ON ck.maChuyenKhoa = bs.maChuyenKhoa
            LEFT JOIN lichkham lk ON bs.maBacSi = lk.maBacSi AND $dateCondition
            GROUP BY k.maKhoa, k.tenKhoa
            HAVING count > 0
            ORDER BY count DESC
            LIMIT 7";
    
    $result = $conn->query($sql);
    $labels = [];
    $values = [];
    
    while($row = $result->fetch_assoc()) {
        $labels[] = $row['tenKhoa'];
        $values[] = (int)$row['count'];
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getStatusData($conn, $dateCondition) {
    $sql = "SELECT 
                SUM(CASE WHEN trangThai = 'Đã đặt' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN trangThai = 'Chờ' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN trangThai = 'Hoàn thành' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN trangThai = 'Hủy' THEN 1 ELSE 0 END) as cancelled
            FROM lichkham WHERE $dateCondition";
    
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    
    return [
        'confirmed' => (int)$data['confirmed'],
        'pending' => (int)$data['pending'],
        'completed' => (int)$data['completed'],
        'cancelled' => (int)$data['cancelled']
    ];
}

function getTopDoctors($conn, $dateCondition) {
    $sql = "SELECT 
                bs.maBacSi, bs.tenBacSi, ck.tenChuyenKhoa,
                COUNT(lk.maLichKham) as total,
                SUM(CASE WHEN lk.trangThai = 'Hoàn thành' THEN 1 ELSE 0 END) as completed
            FROM bacsi bs
            LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
            LEFT JOIN lichkham lk ON bs.maBacSi = lk.maBacSi AND $dateCondition
            GROUP BY bs.maBacSi, bs.tenBacSi, ck.tenChuyenKhoa
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 10";
    
    $result = $conn->query($sql);
    $doctors = [];
    while($row = $result->fetch_assoc()) {
        $doctors[] = [
            'maBacSi' => $row['maBacSi'],
            'tenBacSi' => $row['tenBacSi'],
            'tenChuyenKhoa' => $row['tenChuyenKhoa'],
            'total' => (int)$row['total'],
            'completed' => (int)$row['completed']
        ];
    }
    return $doctors;
}
?>
