<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = currentUser();
$payload = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$message = trim((string) ($payload['message'] ?? ''));
$scope = trim((string) ($payload['scope'] ?? ($user['role'] ?? 'customer')));
$keywords = is_array($payload['keywords'] ?? null) ? array_values(array_filter(array_map('strval', $payload['keywords']))) : [];
$history = is_array($payload['history'] ?? null) ? array_values(array_filter($payload['history'], 'is_array')) : [];

if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'Message is required.',
    ]);
    exit;
}

if ($scope === 'admin' && (!$user || ($user['role'] ?? '') !== 'admin')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Admin support is available only to admin accounts.',
    ]);
    exit;
}

try {
    $assistant = new SupportAssistant(Database::connect());
    $response = $assistant->respond($message, $scope, $history, $keywords);

    if (($response['kind'] ?? '') === 'ai') {
        $geminiKey = trim((string) (getenv('GEMINI_API_KEY') ?: ''));

        if ($geminiKey !== '') {
            $geminiReply = askGeminiSupport(
                $geminiKey,
                $scope,
                $message,
                (string) ($response['context'] ?? $response['reply'] ?? ''),
                $history,
                $keywords
            );

            if ($geminiReply !== '') {
                $response = [
                    'kind' => 'gemini-fallback',
                    'reply' => $geminiReply,
                ];
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'scope' => $scope,
        'kind' => $response['kind'],
        'reply' => $response['reply'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
}

function askGeminiSupport(string $apiKey, string $scope, string $message, string $context, array $history, array $keywords): string
{
    $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
    $endpoint = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        rawurlencode($model),
        rawurlencode($apiKey)
    );

    $systemPrompt = $scope === 'admin'
        ? 'You are Emperor Hotel admin support. Use dashboard, reports, room, reservation, payment, and guest context only. Do not invent numbers or database facts. When the user asks for room availability, room types, room pricing, monthly sales, or report rows, present the result as a concise markdown table when possible, and keep the table in its own paragraph block.'
        : 'You are Emperor Hotel customer support. Use room availability, room prices, hotel history, and booking guidance only. Do not invent facts. When the user asks for rooms, room types, or prices, present the result as a concise markdown table when possible, and keep the table in its own paragraph block.';

    $conversationParts = [];

    foreach (array_slice($history, -10) as $entry) {
        $role = (string) ($entry['role'] ?? 'user');
        $text = trim((string) ($entry['text'] ?? ''));

        if ($text === '') {
            continue;
        }

        $conversationParts[] = strtoupper($role) . ': ' . $text;
    }

    $payload = json_encode([
        'system_instruction' => [
            'parts' => [
                ['text' => $systemPrompt . "\n\nContext:\n" . $context . "\n\nKeywords:\n" . implode(', ', $keywords) . "\n\nConversation:\n" . implode("\n", $conversationParts)],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $message],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 400,
        ],
    ]);

    if ($payload === false) {
        return '';
    }

    $response = @file_get_contents($endpoint, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]));

    if ($response === false) {
        return '';
    }

    $decoded = json_decode($response, true);
    $reply = trim((string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    return $reply;
}
