<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/cors.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── SEARCH AUTOCOMPLETE ──────────────────────────────────────────
        case 'search':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) { echo json_encode([]); exit; }

            $stmt = $pdo->prepare("
                SELECT maThuoc, tenThuoc, donViTinh, soLuongTon, giaTien, loaiThuoc
                FROM thuoc
                WHERE tenThuoc LIKE ?
                ORDER BY tenThuoc
                LIMIT 10
            ");
            $stmt->execute(["%$q%"]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        // ── DANH SÁCH (có phân trang) ────────────────────────────────────
        case 'list':
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $limit    = (int)($_GET['limit'] ?? 20);
            $offset   = ($page - 1) * $limit;
            $search   = trim($_GET['search'] ?? '');
            $loai     = trim($_GET['loai'] ?? '');

            $where  = [];
            $params = [];

            if ($search !== '') {
                $where[]  = 'tenThuoc LIKE ?';
                $params[] = "%$search%";
            }
            if ($loai !== '') {
                $where[]  = 'loaiThuoc = ?';
                $params[] = $loai;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // Đếm tổng (dùng Deferred Join pattern để tối ưu)
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM thuoc $whereClause");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            // Deferred Join tối ưu OFFSET lớn
            $stmtData = $pdo->prepare("
                SELECT t.*
                FROM thuoc t
                INNER JOIN (
                    SELECT maThuoc FROM thuoc $whereClause
                    ORDER BY tenThuoc
                    LIMIT $limit OFFSET $offset
                ) AS sub USING (maThuoc)
                ORDER BY t.tenThuoc
            ");
            $stmtData->execute(array_merge($params, $params)); // params dùng 2 lần

            echo json_encode([
                'data'       => $stmtData->fetchAll(PDO::FETCH_ASSOC),
                'total'      => $total,
                'page'       => $page,
                'totalPages' => ceil($total / $limit),
            ]);
            break;

        // ── THÊM THUỐC ──────────────────────────────────────────────────
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("
                INSERT INTO thuoc
                  (tenThuoc, donViTinh, soLuongTon, giaTien, cachDungMacDinh,
                   loaiThuoc, nhaSanXuat, hanSuDung, nguongCanhBao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($data['tenThuoc']),
                trim($data['donViTinh']        ?? ''),
                (int)  ($data['soLuongTon']     ?? 0),
                (float)($data['giaTien']        ?? 0),
                trim($data['cachDungMacDinh']   ?? ''),
                trim($data['loaiThuoc']         ?? ''),
                trim($data['nhaSanXuat']        ?? ''),
                $data['hanSuDung']              ?: null,
                (int)  ($data['nguongCanhBao']  ?? 10),
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        // ── SỬA THUỐC ───────────────────────────────────────────────────
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int)($data['maThuoc'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

            $stmt = $pdo->prepare("
                UPDATE thuoc SET
                  tenThuoc        = ?,
                  donViTinh       = ?,
                  soLuongTon      = ?,
                  giaTien         = ?,
                  cachDungMacDinh = ?,
                  loaiThuoc       = ?,
                  nhaSanXuat      = ?,
                  hanSuDung       = ?,
                  nguongCanhBao   = ?
                WHERE maThuoc = ?
            ");
            $stmt->execute([
                trim($data['tenThuoc']),
                trim($data['donViTinh']        ?? ''),
                (int)  ($data['soLuongTon']     ?? 0),
                (float)($data['giaTien']        ?? 0),
                trim($data['cachDungMacDinh']   ?? ''),
                trim($data['loaiThuoc']         ?? ''),
                trim($data['nhaSanXuat']        ?? ''),
                $data['hanSuDung']              ?: null,
                (int)  ($data['nguongCanhBao']  ?? 10),
                $id,
            ]);
            echo json_encode(['success' => true]);
            break;

        // ── XÓA THUỐC ───────────────────────────────────────────────────
        case 'delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

            $pdo->prepare("DELETE FROM thuoc WHERE maThuoc = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        // ── IMPORT HÀNG LOẠT ────────────────────────────────────────────
        case 'import':
            $rows    = json_decode(file_get_contents('php://input'), true) ?? [];
            $stmt    = $pdo->prepare("
                INSERT INTO thuoc
                  (tenThuoc, donViTinh, soLuongTon, giaTien, cachDungMacDinh,
                   loaiThuoc, nhaSanXuat, hanSuDung, nguongCanhBao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  soLuongTon = VALUES(soLuongTon),
                  giaTien    = VALUES(giaTien)
            ");

            $inserted = $skipped = 0;
            $pdo->beginTransaction();
            foreach ($rows as $row) {
                if (empty(trim($row['tenThuoc'] ?? ''))) { $skipped++; continue; }
                $stmt->execute([
                    trim($row['tenThuoc']),
                    trim($row['donViTinh']       ?? ''),
                    (int)  ($row['soLuongTon']    ?? 0),
                    (float)($row['giaTien']       ?? 0),
                    trim($row['cachDungMacDinh']  ?? ''),
                    trim($row['loaiThuoc']        ?? ''),
                    trim($row['nhaSanXuat']       ?? ''),
                    $row['hanSuDung']             ?: null,
                    (int)  ($row['nguongCanhBao'] ?? 10),
                ]);
                $inserted++;
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
            break;

        // ── LẤY DANH SÁCH LOẠI THUỐC ────────────────────────────────────
        case 'categories':
            $stmt = $pdo->query("SELECT DISTINCT loaiThuoc FROM thuoc WHERE loaiThuoc IS NOT NULL AND loaiThuoc != '' ORDER BY loaiThuoc");
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}