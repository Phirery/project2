<?php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/dp.php';

try {
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
            WHERE nd.isDeleted = 0
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
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Không thể tải danh sách bác sĩ nổi bật.',
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
