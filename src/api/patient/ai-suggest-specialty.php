<?php
require_once '../../config/cors.php';
require_once '../../config/db.php';

require_once '../../config/llm.php';
require_once '../../includes/llm-service.php';

const AI_SUGGEST_MODE_FAST = 'fast';
const AI_SUGGEST_MODE_EXPERT = 'expert';
const AI_SUGGEST_MODE_DEEP = 'deep';

function getAiSuggestModeConfig(): array
{
    return [
        AI_SUGGEST_MODE_FAST => [
            'workspaceSlug' => ANYTHINGLLM_WORKSPACE_FAST,
            'label' => 'Nhanh',
            'maxSuggestions' => 2,
            'reasonStyle' => 'ngắn gọn',
            'adviceStyle' => 'cực ngắn',
            'modeInstruction' => 'Ưu tiên phản hồi thật nhanh. Chỉ nêu kết luận ngắn gọn, tránh giải thích dài.',
        ],
        AI_SUGGEST_MODE_EXPERT => [
            'workspaceSlug' => ANYTHINGLLM_WORKSPACE_REASON,
            'label' => 'Chuyên gia',
            'maxSuggestions' => 3,
            'reasonStyle' => 'rõ ràng',
            'adviceStyle' => 'ngắn gọn',
            'modeInstruction' => 'Giữ cân bằng giữa độ chính xác và độ súc tích.',
        ],
        AI_SUGGEST_MODE_DEEP => [
            'workspaceSlug' => ANYTHINGLLM_WORKSPACE_DEEP,
            'label' => 'Chẩn đoán sâu',
            'maxSuggestions' => 3,
            'reasonStyle' => 'phân tích kỹ hơn',
            'adviceStyle' => 'chi tiết hơn',
            'modeInstruction' => 'Suy nghĩ kỹ hơn, tận dụng ngữ cảnh kiến thức trong workspace khi hữu ích.',
        ],
    ];
}

function normalizeAiSuggestMode(?string $mode): string
{
    $mode = trim((string)$mode);
    $allowed = [AI_SUGGEST_MODE_FAST, AI_SUGGEST_MODE_EXPERT, AI_SUGGEST_MODE_DEEP];
    return in_array($mode, $allowed, true) ? $mode : AI_SUGGEST_MODE_EXPERT;
}

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
$mode = normalizeAiSuggestMode($body['mode'] ?? null);
$modeConfig = getAiSuggestModeConfig()[$mode];

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
function buildPrompt(string $symptoms, array $specialties, array $modeConfig): string
{
    $specialtyList = '';
    foreach ($specialties as $s) {
        $desc = $s['moTa'] !== '' ? " - {$s['moTa']}" : '';
        $specialtyList .= "- {$s['maChuyenKhoa']}: {$s['tenChuyenKhoa']}{$desc}\n";
    }

    $maxSuggestions = (int)($modeConfig['maxSuggestions'] ?? 3);
    $reasonStyle = $modeConfig['reasonStyle'] ?? 'rõ ràng';
    $adviceStyle = $modeConfig['adviceStyle'] ?? 'ngắn gọn';
    $modeInstruction = $modeConfig['modeInstruction'] ?? '';

    return <<<PROMPT
Bạn là trợ lý y tế AI của hệ thống Eden Health. Nhiệm vụ của bạn là dựa trên triệu chứng bệnh nhân mô tả, gợi ý chuyên khoa phù hợp nhất từ danh sách chuyên khoa bên dưới.

{$modeInstruction}

DANH SÁCH CHUYÊN KHOA CÓ SẴN:
{$specialtyList}

QUY TẮC:
1. Chỉ gợi ý từ danh sách chuyên khoa trên, KHÔNG tạo chuyên khoa mới.
2. Gợi ý tối đa {$maxSuggestions} chuyên khoa, sắp xếp theo độ phù hợp giảm dần.
3. Mỗi gợi ý phải có lý do {$reasonStyle} bằng tiếng Việt.
4. Đánh giá độ tin cậy từ 0-100 cho mỗi gợi ý.
5. Đưa ra lời khuyên chung {$adviceStyle} bằng tiếng Việt.
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

    $prompt = buildPrompt($symptoms, $specialties, $modeConfig);
    $llmResult = callAnythingLLM($prompt, $modeConfig['workspaceSlug']);

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
        'mode' => $mode,
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
