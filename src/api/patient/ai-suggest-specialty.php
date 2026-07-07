<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';

require_once '../../config/llm.php';
require_once '../../includes/llm-service.php';

// ─── Only accept POST ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Chỉ hỗ trợ phương thức POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Parse request body ───
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
$symptoms = trim($body['symptoms'] ?? '');

if ($symptoms === '' || mb_strlen($symptoms) < 5) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng mô tả triệu chứng ít nhất 5 ký tự.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($symptoms) > 1000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Mô tả triệu chứng quá dài, vui lòng rút gọn dưới 1000 ký tự.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Fetch specialties from database ───
function getSpecialtiesFromDB(mysqli $conn): array
{
    $sql = "
        SELECT
            ck.maChuyenKhoa,
            ck.tenChuyenKhoa,
            ck.moTa
        FROM chuyenkhoa ck
        ORDER BY ck.tenChuyenKhoa ASC
    ";
    $result = $conn->query($sql);
    $specialties = [];
    while ($row = $result->fetch_assoc()) {
        $specialties[] = [
            'maChuyenKhoa' => $row['maChuyenKhoa'],
            'tenChuyenKhoa' => $row['tenChuyenKhoa'],
            'moTa' => $row['moTa'] ?? ''
        ];
    }
    return $specialties;
}

// ─── Build the AI prompt ───
function buildPrompt(string $symptoms, array $specialties): string
{
    $specialtyList = '';
    foreach ($specialties as $s) {
        $desc = $s['moTa'] !== '' ? " - {$s['moTa']}" : '';
        $specialtyList .= "- {$s['maChuyenKhoa']}: {$s['tenChuyenKhoa']}{$desc}\n";
    }

    return <<<PROMPT
Bạn là trợ lý y tế AI của hệ thống Eden Health. Nhiệm vụ của bạn là dựa trên triệu chứng bệnh nhân mô tả, gợi ý chuyên khoa phù hợp nhất từ danh sách chuyên khoa bên dưới.

DANH SÁCH CHUYÊN KHOA CÓ SẴN:
{$specialtyList}

QUY TẮC:
1. Chỉ gợi ý từ danh sách chuyên khoa trên, KHÔNG tạo chuyên khoa mới.
2. Gợi ý tối đa 3 chuyên khoa, sắp xếp theo độ phù hợp giảm dần.
3. Mỗi gợi ý phải có lý do ngắn gọn (1-2 câu) bằng tiếng Việt.
4. Đánh giá độ tin cậy từ 0-100 cho mỗi gợi ý.
5. Đưa ra lời khuyên chung ngắn gọn (1-2 câu) bằng tiếng Việt.
6. LUÔN trả lời bằng JSON hợp lệ, KHÔNG thêm text ngoài JSON.
7. Đây chỉ là gợi ý tham khảo, không phải chẩn đoán y khoa.

TRIỆU CHỨNG BỆNH NHÂN:
"{$symptoms}"

Trả lời CHÍNH XÁC theo format JSON sau (không thêm markdown, không thêm text):
{"suggestions":[{"maChuyenKhoa":"...","tenChuyenKhoa":"...","lyDo":"...","doTinCay":0}],"loiKhuyen":"..."}
PROMPT;
}

// ─── Parse AI response to extract JSON suggestions ───
function parseAiResponse(array $llmResponse): ?array
{
    $textResponse = $llmResponse['data']['textResponse'] ?? '';
    return extractJsonFromAiResponse($textResponse, 'suggestions');
}

// ─── Main execution ───
try {
    $specialties = getSpecialtiesFromDB($conn);

    if (empty($specialties)) {
        echo json_encode([
            'success' => false,
            'message' => 'Hiện tại chưa có dữ liệu chuyên khoa trong hệ thống.'
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    $prompt = buildPrompt($symptoms, $specialties);
    $llmResult = callAnythingLLM($prompt);

    if (!$llmResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => $llmResult['error']
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    $parsed = parseAiResponse($llmResult);

    if ($parsed === null) {
        // Fallback: return raw text if JSON parsing fails
        $rawText = $llmResult['data']['textResponse'] ?? 'Không thể phân tích phản hồi từ AI.';
        echo json_encode([
            'success' => true,
            'type' => 'text',
            'message' => $rawText,
            'suggestions' => [],
            'loiKhuyen' => ''
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    // Validate & sanitize suggestions
    $validSuggestions = [];
    $specialtyMap = array_column($specialties, 'tenChuyenKhoa', 'maChuyenKhoa');

    foreach ($parsed['suggestions'] as $suggestion) {
        $maChuyenKhoa = $suggestion['maChuyenKhoa'] ?? '';
        // Verify the specialty actually exists in our database
        if (isset($specialtyMap[$maChuyenKhoa])) {
            $validSuggestions[] = [
                'maChuyenKhoa' => $maChuyenKhoa,
                'tenChuyenKhoa' => $specialtyMap[$maChuyenKhoa],
                'lyDo' => $suggestion['lyDo'] ?? '',
                'doTinCay' => max(0, min(100, (int)($suggestion['doTinCay'] ?? 0)))
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'type' => 'suggestions',
        'suggestions' => $validSuggestions,
        'loiKhuyen' => $parsed['loiKhuyen'] ?? ''
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
