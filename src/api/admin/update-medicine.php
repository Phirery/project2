<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/db.php';
require_role('quantri');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['maThuoc'] ?? 0);
$ten  = trim($data['tenThuoc'] ?? '');

if (!$id)  { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Thiếu mã thuốc']); exit; }
if (!$ten) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Tên thuốc không được để trống']); exit; }

try {
    $stmt = $conn->prepare(
        "UPDATE thuoc SET tenThuoc=?,donViTinh=?,soLuongTon=?,giaTien=?,cachDungMacDinh=?,loaiThuoc=?,nhaSanXuat=?,hanSuDung=?,nguongCanhBao=?
         WHERE maThuoc=?"
    );
    $donVi   = trim($data['donViTinh']        ?? '');
    $soLuong = (int)($data['soLuongTon']       ?? 0);
    $gia     = (float)($data['giaTien']        ?? 0);
    $cachDung= trim($data['cachDungMacDinh']   ?? '');
    $loai    = trim($data['loaiThuoc']         ?? '');
    $nhaSX   = trim($data['nhaSanXuat']        ?? '');
    $han     = ($data['hanSuDung'] && $data['hanSuDung'] !== '') ? $data['hanSuDung'] : null;
    $nguong  = (int)($data['nguongCanhBao']    ?? 10);

    $stmt->bind_param('ssidssssii', $ten, $donVi, $soLuong, $gia, $cachDung, $loai, $nhaSX, $han, $nguong, $id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}