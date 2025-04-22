<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredProps = [
    'past',
    'question',
    'present',
    'future',
    'past_reverse',
    'present_reverse',
    'future_reverse'
];
$missingProps = [];
foreach ($requiredProps as $prop) {
    if (!isset($input[$prop])) {
        $missingProps[] = $prop;
    }
}

if (!empty($missingProps)) {
    http_response_code(400);
    echo json_encode([
        'message' => 'Missing required data',
        'missing' => $missingProps
    ]);
    exit;
}

// Build the prompt
$question = $input['question'];
$past = $input['past'];
$present = $input['present'];
$future = $input['future'];

$positions = [
    'past' => $input['past_reverse'] ? 'Reversed' : 'Upright',
    'present' => $input['present_reverse'] ? 'Reversed' : 'Upright',
    'future' => $input['future_reverse'] ? 'Reversed' : 'Upright'
];

$systemPrompt = "You are a seasoned tarot reader with 30+ years of experience. Analyze this 3-card spread. Be honest, natural, and insightful. Respond in the user's language.";
$userPrompt = "Question: $question\n"
    . "Past Card ($positions[past]): $past\n"
    . "Present Card ($positions[present]): $present\n"
    . "Future Card ($positions[future]): $future";

// API Configuration
$apiKey = "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";
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['message' => 'Server configuration error']);
    exit;
}

$url = 'https://openrouter.ai/api/v1/chat/completions';
$headers = [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'HTTP-Referer: https://your-domain.com', // Update with your domain
    'X-Title: Tarot Reader'                  // Update with your app name
];

$payload = [
    'model' => 'google/gemini-2.0-flash-exp:free',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ]
];

// Make API request
$context = stream_context_create([
    'http' => [
        'header' => implode("\r\n", $headers),
        'method' => 'POST',
        'content' => json_encode($payload),
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to contact API']);
    exit;
}

$responseData = json_decode($response, true);

// Handle API response
if (isset($responseData['choices'][0]['message']['content'])) {
    echo json_encode([
        'content' => $responseData['choices'][0]['message']['content']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'message' => 'API response error',
        'details' => $responseData
    ]);
}
