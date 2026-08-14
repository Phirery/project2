<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../includes/mail-events.php';

require_role('bacsi');

// Lấy ID người dùng (bác sĩ) từ session
$nguoiDungId = $_SESSION['id'];

// Lấy dữ liệu JSON từ body request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['maLichKham'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin mã lịch khám']);
    exit;
}
$maLichKham = $input['maLichKham'];
$lyDo = trim($input['lyDo'] ?? '');
$fromQueue = !empty($input['fromQueue']);

try {
    // 3. Lấy maBacSi của bác sĩ đang đăng nhập (để bảo mật)
    $stmt_bs = $conn->prepare("SELECT maBacSi FROM bacsi WHERE nguoiDungId = ?");
    $stmt_bs->bind_param("i", $nguoiDungId);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();
    
    if ($result_bs->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin bác sĩ liên kết.']);
        exit;
    }
    
    $bacsi = $result_bs->fetch_assoc();
    $maBacSi = $bacsi['maBacSi']; // Đây là mã bác sĩ đã được xác thực
    $stmt_bs->close();

    // Nếu lịch này đã check-in (có trong hàng đợi) và không phải hủy từ hàng đợi,
    // chặn lại để nhân viên/bác sĩ xử lý trong hàng đợi trước.
    $queueStmt = $conn->prepare("SELECT maHangDoi, trangThai FROM hangdoikham WHERE maLichKham = ? LIMIT 1");
    $queueStmt->bind_param("i", $maLichKham);
    $queueStmt->execute();
    $queueRow = $queueStmt->get_result()->fetch_assoc();
    $queueStmt->close();

    if ($queueRow && !$fromQueue) {
        echo json_encode([
            'success' => false,
            'message' => 'Lịch khám này đã check-in (đang trong hàng đợi khám). Vui lòng hủy từ hàng đợi khám thay vì trang lịch khám.'
        ]);
        exit;
    }

    $conn->begin_transaction();

    if ($lyDo !== '') {
        $stmt = $conn->prepare("
            UPDATE lichkham 
            SET trangThai = 'Hủy',
                nguoiHuy = 'bacsi',
                ghiChu = CONCAT(COALESCE(ghiChu, ''), '\n[Lý do hủy]: ', ?)
            WHERE maLichKham = ? AND maBacSi = ? AND trangThai = 'Đã đặt'
        ");
        $stmt->bind_param("sis", $lyDo, $maLichKham, $maBacSi);
    } else {
        $stmt = $conn->prepare("
            UPDATE lichkham 
            SET trangThai = 'Hủy',
                nguoiHuy = 'bacsi'
            WHERE maLichKham = ? AND maBacSi = ? AND trangThai = 'Đã đặt'
        ");
        $stmt->bind_param("is", $maLichKham, $maBacSi);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $stmt->close();

            if ($queueRow && $fromQueue) {
                $cancelQueueStmt = $conn->prepare("UPDATE hangdoikham SET trangThai = 'Hủy' WHERE maLichKham = ?");
                $cancelQueueStmt->bind_param("i", $maLichKham);
                if (!$cancelQueueStmt->execute()) {
                    throw new Exception('Không thể hủy hàng đợi khám: ' . $cancelQueueStmt->error);
                }
                $cancelQueueStmt->close();
            }

            $conn->commit();

            try {
                sendAppointmentCancelledEmails($conn, (int)$maLichKham, 'bacsi', $lyDo);
            } catch (Throwable $mailError) {
                error_log('Doctor cancel mail error: ' . $mailError->getMessage());
            }
            echo json_encode(['success' => true, 'message' => 'Đã hủy lịch khám thành công.']);
        } else {
            $stmt->close();
            $conn->rollback();
            // Không có dòng nào bị ảnh hưởng
            echo json_encode([
                'success' => false, 
                'message' => 'Không thể hủy. Lịch không tồn tại, không phải của bạn, hoặc đã ở trạng thái không thể hủy.'
            ]);
        }
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi lệnh hủy.']);
    }

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}

$conn->close();
?>