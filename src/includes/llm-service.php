<?php
require_once __DIR__ . '/../config/llm.php';

/**
 * ─── Call AnythingLLM API ───
 * @param string $message The message to send to the AI
 * @return array The response array containing 'success', 'data' or 'error'
 */
function callAnythingLLM(string $message): array
{
    $url = ANYTHINGLLM_BASE_URL . '/api/v1/workspace/' . ANYTHINGLLM_WORKSPACE_SLUG . '/chat';

    $payload = json_encode([
        'message' => $message,
        'mode' => 'chat'
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => ANYTHINGLLM_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ANYTHINGLLM_API_KEY,
            'accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return [
            'success' => false,
            'error' => 'Không thể kết nối đến AI server: ' . $curlError
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "AI server trả về mã lỗi HTTP {$httpCode}."
        ];
    }

    $decoded = json_decode($response, true);
    if (!$decoded) {
        return [
            'success' => false,
            'error' => 'Phản hồi từ AI server không hợp lệ.'
        ];
    }

    return [
        'success' => true,
        'data' => $decoded
    ];
}

/**
 * ─── Extract JSON from AI text response ───
 * @param string $textResponse The raw text response from AI
 * @param string|null $requiredKey An optional key that must be present in the root of the JSON object
 * @return array|null The parsed JSON array or null if invalid
 */
function extractJsonFromAiResponse(string $textResponse, string $requiredKey = null): ?array
{
    if ($textResponse === '') {
        return null;
    }

    $jsonStr = $textResponse;

    // Remove markdown code fences if present
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $jsonStr, $matches)) {
        $jsonStr = $matches[1];
    }

    // Try to find JSON object pattern if requiredKey is provided
    if ($requiredKey !== null && preg_match('/\{[\s\S]*"' . preg_quote($requiredKey, '/') . '"[\s\S]*\}/', $jsonStr, $matches)) {
        $jsonStr = $matches[0];
    } else {
        // Find general JSON object pattern
        if (preg_match('/\{[\s\S]*\}/', $jsonStr, $matches)) {
            $jsonStr = $matches[0];
        }
    }

    $parsed = json_decode(trim($jsonStr), true);
    
    if (!$parsed) {
        return null;
    }
    
    if ($requiredKey !== null && !isset($parsed[$requiredKey])) {
        return null;
    }

    return $parsed;
}
