<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "datlichkham";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get top 3 doctors with most appointments
    $sql = "SELECT 
                bs.maBacSi,
                bs.tenBacSi,
                bs.gioiTinh,
                bs.namLamViec,
                bs.moTa,
                ck.tenChuyenKhoa,
                k.tenKhoa,
                nd.avatar,
                COUNT(lk.maLichKham) as totalAppointments,
                COUNT(DISTINCT lk.maBenhNhan) as totalPatients
            FROM bacsi bs
            LEFT JOIN nguoidung nd ON bs.nguoiDungId = nd.id
            LEFT JOIN chuyenkhoa ck ON bs.maChuyenKhoa = ck.maChuyenKhoa
            LEFT JOIN khoa k ON ck.maKhoa = k.maKhoa
            LEFT JOIN lichkham lk ON bs.maBacSi = lk.maBacSi
            GROUP BY bs.maBacSi, bs.tenBacSi, bs.gioiTinh, bs.namLamViec, 
                     bs.moTa, ck.tenChuyenKhoa, k.tenKhoa, nd.avatar
            ORDER BY totalAppointments DESC, totalPatients DESC
            LIMIT 3";

    $result = $conn->query($sql);

    $doctors = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $maleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962515/doctor_male_pna01s.png';
            $femaleDefault = 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769962514/doctor_female_zvmhtg.png';
            $gender = strtolower((string)($row['gioiTinh'] ?? ''));
            $fallbackAvatar = ($gender === 'nu') ? $femaleDefault : $maleDefault;
            $avatar = trim((string)($row['avatar'] ?? ''));
            $hasCustomAvatar = $avatar !== '' && stripos($avatar, 'samples/paper.png') === false;

            $doctors[] = [
                'maBacSi' => $row['maBacSi'],
                'tenBacSi' => $row['tenBacSi'],
                'gioiTinh' => $row['gioiTinh'],
                'namLamViec' => $row['namLamViec'] ? (int)$row['namLamViec'] : null,
                'moTa' => $row['moTa'],
                'tenChuyenKhoa' => $row['tenChuyenKhoa'],
                'tenKhoa' => $row['tenKhoa'],
                'anhDaiDien' => $hasCustomAvatar ? $avatar : $fallbackAvatar,
                'totalAppointments' => (int)$row['totalAppointments'],
                'totalPatients' => (int)$row['totalPatients']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $doctors,
        'total' => count($doctors)
    ], JSON_UNESCAPED_UNICODE);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>
