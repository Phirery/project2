<?php
require_once '../../config/cors.php';
require_once '../../config/session.php';
require_once '../../config/db.php';
require_role('quantri');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$rows = json_decode(file_get_contents('php://input'), true) ?? [];
if (!$rows) { echo json_encode(['success'=>false,'message'=>'Không có dữ liệu']); exit; }

try {
    $stmt = $conn->prepare(
        "INSERT INTO thuoc (tenThuoc,donViTinh,soLuongTon,giaTien,cachDungMacDinh,loaiThuoc,nhaSanXuat,hanSuDung,nguongCanhBao)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE soLuongTon = soLuongTon + VALUES(soLuongTon), giaTien = VALUES(giaTien)"
    );

    $inserted = 0; $skipped = 0;
    $conn->begin_transaction();

    foreach ($rows as $row) {
        $ten = trim($row['tenThuoc'] ?? '');
        if (!$ten) { $skipped++; continue; }

        $donVi   = trim($row['donViTinh']        ?? '');
        $soLuong = (int)($row['soLuongTon']       ?? 0);
        $gia     = (float)($row['giaTien']        ?? 0);
        $cachDung= trim($row['cachDungMacDinh']   ?? '');
        $loai    = trim($row['loaiThuoc']         ?? '');
        $nhaSX   = trim($row['nhaSanXuat']        ?? '');
        $han     = ($row['hanSuDung'] !== '') ? trim($row['hanSuDung']) : null;
        $nguong  = (int)($row['nguongCanhBao']    ?? 10);

        $stmt->bind_param('ssidssssi', $ten, $donVi, $soLuong, $gia, $cachDung, $loai, $nhaSX, $han, $nguong);
        $stmt->execute();
        $inserted++;
    }

    $conn->commit();
    echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}