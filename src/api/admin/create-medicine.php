<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/dp.php';
require_role('quantri');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ten  = trim($data['tenThuoc'] ?? '');
if (!$ten) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Tên thuốc không được để trống']); exit; }

try {
    $stmt = $conn->prepare(
        "INSERT INTO thuoc (tenThuoc,donViTinh,soLuongTon,giaTien,cachDungMacDinh,loaiThuoc,nhaSanXuat,hanSuDung,nguongCanhBao)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $donVi   = trim($data['donViTinh']        ?? '');
    $soLuong = (int)($data['soLuongTon']       ?? 0);
    $gia     = (float)($data['giaTien']        ?? 0);
    $cachDung= trim($data['cachDungMacDinh']   ?? '');
    $loai    = trim($data['loaiThuoc']         ?? '');
    $nhaSX   = trim($data['nhaSanXuat']        ?? '');
    $han     = ($data['hanSuDung'] && $data['hanSuDung'] !== '') ? $data['hanSuDung'] : null;
    $nguong  = (int)($data['nguongCanhBao']    ?? 10);

    $stmt->bind_param('ssiissssi', $ten, $donVi, $soLuong, $gia, $cachDung, $loai, $nhaSX, $han, $nguong);

    // Fix: giaTien is decimal, use 'd' type
    $stmt = $conn->prepare(
        "INSERT INTO thuoc (tenThuoc,donViTinh,soLuongTon,giaTien,cachDungMacDinh,loaiThuoc,nhaSanXuat,hanSuDung,nguongCanhBao)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssidssssi', $ten, $donVi, $soLuong, $gia, $cachDung, $loai, $nhaSX, $han, $nguong);
    $stmt->execute();

    echo json_encode(['success' => true, 'maThuoc' => $conn->insert_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}