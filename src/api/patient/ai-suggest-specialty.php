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
            'reasonStyle' => 'ngắn gọn (1 câu)',
            'adviceStyle' => 'cực ngắn (1 câu)',
            'modeInstruction' => 'Chế độ Nhanh: ưu tiên phản hồi súc tích, đi thẳng vào kết luận.',
        ],
        AI_SUGGEST_MODE_EXPERT => [
            'workspaceSlug' => ANYTHINGLLM_WORKSPACE_REASON,
            'label' => 'Chuyên gia',
            'maxSuggestions' => 3,
            'reasonStyle' => 'rõ ràng (1-2 câu)',
            'adviceStyle' => 'ngắn gọn (1-2 câu)',
            'modeInstruction' => 'Chế độ Chuyên gia: cân bằng giữa độ chính xác lâm sàng và sự súc tích.',
        ],
        AI_SUGGEST_MODE_DEEP => [
            'workspaceSlug' => ANYTHINGLLM_WORKSPACE_DEEP,
            'label' => 'Chẩn đoán sâu',
            'maxSuggestions' => 3,
            'reasonStyle' => 'phân tích kỹ mối liên hệ triệu chứng với chuyên khoa (2-3 câu)',
            'adviceStyle' => 'cụ thể, hữu ích (2-3 câu)',
            'modeInstruction' => 'Chế độ Chẩn đoán sâu: đối chiếu kỹ ngữ cảnh tài liệu RAG (nếu có) và phân tích các khả năng liên quan.',
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
            ck.tenChuyenKhoa
        FROM chuyenkhoa ck
        ORDER BY ck.tenChuyenKhoa ASC
    ";
    $result = $conn->query($sql);
    $specialties = [];
    while ($row = $result->fetch_assoc()) {
        $specialties[] = [
            'maChuyenKhoa' => $row['maChuyenKhoa'],
            'tenChuyenKhoa' => $row['tenChuyenKhoa']
        ];
    }
    return $specialties;
}

// ─── Build the AI prompt ───
function buildPrompt(string $symptoms, array $specialties, array $modeConfig): string
{
    $specialtyList = '';
    foreach ($specialties as $s) {
        $specialtyList .= "{$s['maChuyenKhoa']}: {$s['tenChuyenKhoa']}\n";
    }
    $specialtyList = rtrim($specialtyList);

    $maxSuggestions = (int)($modeConfig['maxSuggestions'] ?? 3);
    $reasonStyle = $modeConfig['reasonStyle'] ?? 'rõ ràng (1-2 câu)';
    $adviceStyle = $modeConfig['adviceStyle'] ?? 'ngắn gọn (1-2 câu)';
    $modeInstruction = $modeConfig['modeInstruction'] ?? '';

    return <<<PROMPT
Bạn là trợ lý phân luồng chuyên khoa y tế của Eden Health. {$modeInstruction}

TRIỆU CHỨNG BỆNH NHÂN:
"""
{$symptoms}
"""

DANH SÁCH CHUYÊN KHOA HỢP LỆ (mã: tên):
{$specialtyList}

HƯỚNG DẪN:
1. Nếu có ngữ cảnh RAG liên quan đến triệu chứng, tham khảo chuyên khoa theo thứ tự ưu tiên (priority_1 -> priority_4) và đối chiếu đúng `maChuyenKhoa` có trong danh sách trên.
2. Chỉ chọn `maChuyenKhoa` có trong danh sách trên; KHÔNG tự tạo chuyên khoa mới (các nhóm không có khoa riêng như truyền nhiễm, cơ xương khớp, tâm thần... phải quy về chuyên khoa hiện có phù hợp như Nội Tổng Hợp, Ngoại Chấn Thương Chỉnh Hình, Nội Thần Kinh).
3. Không tự suy đoán bệnh nhân là trẻ em (Nhi) nếu mô tả không nêu độ tuổi hoặc đối tượng trẻ nhỏ.
4. Nếu mô tả đã đủ dữ kiện định hướng, hãy chọn tối đa {$maxSuggestions} chuyên khoa theo độ phù hợp giảm dần và chấm `doTinCay` (0-100). Chỉ khi mô tả quá mơ hồ, thiếu dữ kiện phân biệt khoa (vd chỉ ghi "mệt", "sốt", "đau") hoặc không phải triệu chứng bệnh, trả về `"suggestions":[]` và đặt 1-2 câu hỏi làm rõ ngắn gọn (vị trí, triệu chứng kèm theo, thời gian, độ tuổi) trong `loiKhuyen` để người bệnh nhập lại mô tả đầy đủ hơn.
5. Viết `lyDo` {$reasonStyle} và `loiKhuyen` {$adviceStyle} bằng tiếng Việt tự nhiên cho bệnh nhân (không sao chép tên bệnh tiếng Anh hay ghi chú nội bộ từ tài liệu; nhắc đi cấp cứu ngay nếu có dấu hiệu nguy hiểm; nêu rõ chỉ mang tính tham khảo).

Chỉ trả về đúng 1 JSON hợp lệ (không markdown, không thêm chữ):
{"suggestions":[{"maChuyenKhoa":"...","lyDo":"...","doTinCay":0}],"loiKhuyen":"..."}
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
    $seenCodes = [];
    $specialtyMap = array_column($specialties, 'tenChuyenKhoa', 'maChuyenKhoa');
    $maxSuggestions = (int)($modeConfig['maxSuggestions'] ?? 3);

    if (is_array($parsed['suggestions'])) {
        foreach ($parsed['suggestions'] as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }
            $maChuyenKhoa = trim((string)($suggestion['maChuyenKhoa'] ?? ''));
            // Verify the specialty exists in our database and deduplicate
            if ($maChuyenKhoa !== '' && isset($specialtyMap[$maChuyenKhoa]) && !isset($seenCodes[$maChuyenKhoa])) {
                $seenCodes[$maChuyenKhoa] = true;
                $validSuggestions[] = [
                    'maChuyenKhoa' => $maChuyenKhoa,
                    'tenChuyenKhoa' => $specialtyMap[$maChuyenKhoa],
                    'lyDo' => trim((string)($suggestion['lyDo'] ?? '')),
                    'doTinCay' => max(0, min(100, (int)($suggestion['doTinCay'] ?? 0)))
                ];
                if (count($validSuggestions) >= $maxSuggestions) {
                    break;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'type' => 'suggestions',
        'mode' => $mode,
        'suggestions' => $validSuggestions,
        'loiKhuyen' => trim((string)($parsed['loiKhuyen'] ?? ''))
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
