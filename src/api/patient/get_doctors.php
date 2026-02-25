<?php
require_once '../../config/cors.php';
require_once '../../config/dp.php';

// Get filter parameters
$maKhoa = $_GET['maKhoa'] ?? '';
$maChuyenKhoa = $_GET['maChuyenKhoa'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query with filters
    $sql = "
        SELECT 
            bs.maBacSi,
            bs.tenBacSi,
            bs.gioiTinh,
            bs.namLamViec,
            bs.moTa,
            ck.maChuyenKhoa,
            ck.tenChuyenKhoa,
            ck.maKhoa,
            k.tenKhoa,
            nd.avatar
        FROM bacsi bs
        LEFT JOIN nguoidung nd ON bs.nguoiDungId = nd.id
        LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
        LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($maKhoa)) {
        $sql .= " AND k.maKhoa = ?";
        $params[] = $maKhoa;
        $types .= 's';
    }
    
    if (!empty($maChuyenKhoa)) {
        $sql .= " AND ck.maChuyenKhoa = ?";
        $params[] = $maChuyenKhoa;
        $types .= 's';
    }
    
    if (!empty($search)) {
        $sql .= " AND (bs.tenBacSi LIKE ? OR ck.tenChuyenKhoa LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    $sql .= " ORDER BY bs.tenBacSi ASC";
    
    // Prepare and execute
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $doctors = [];
    $currentYear = date('Y');
    
    while ($row = $result->fetch_assoc()) {
        // Calculate experience
        $experience = 0;
        if ($row['namLamViec']) {
            $experience = $currentYear - (int)$row['namLamViec'];
        }
        
        $maleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962515/doctor_male_pna01s.png';
        $femaleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962514/doctor_female_zvmhtg.png';
        $gender = strtolower((string)($row['gioiTinh'] ?? ''));
        $fallbackAvatar = ($gender === 'nu') ? $femaleDefault : $maleDefault;

        $avatar = trim((string)($row['avatar'] ?? ''));
        $hasCustomAvatar = $avatar !== '' && stripos($avatar, 'samples/paper.png') === false;
        $imageUrl = $hasCustomAvatar ? $avatar : $fallbackAvatar;
        
        $doctors[] = [
            'maBacSi' => $row['maBacSi'],
            'tenBacSi' => $row['tenBacSi'],
            'gioiTinh' => $row['gioiTinh'],
            'namLamViec' => $row['namLamViec'],
            'namKinhNghiem' => $experience,
            'moTa' => $row['moTa'],
            'maChuyenKhoa' => $row['maChuyenKhoa'],
            'tenChuyenKhoa' => $row['tenChuyenKhoa'],
            'maKhoa' => $row['maKhoa'],
            'tenKhoa' => $row['tenKhoa'],
            'anhDaiDien' => $imageUrl
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $doctors,
        'total' => count($doctors)
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
