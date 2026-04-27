<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// analyze_ajax.php
header('Content-Type: application/json');

// --- Configuration ---
define('OPENROUTER_API_KEY', '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'); // <?define('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions');
define('MODEL', 'openrouter/free'); // reliable, low cost, great for this task

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data provided']);
    exit;
}

// The image is already a data URI like "data:image/jpeg;base64,..."
$data_uri = $input['image'];

// Basic validation (optional, but recommended)
if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $data_uri)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image format. Please send a valid JPEG, PNG or WebP data URI.']);
    exit;
}

// --- Prepare the prompt ---
$prompt = <<<EOT
You are a professional dermatologist AI. Analyze the provided skin image thoroughly and provide a detailed report with the following sections exactly numbered as shown:

1. **Skin Type**: 
2. **Visible Conditions**: 
3. **Severity Assessment**: mild/moderate/severe for each condition
4. **Possible Causes**: 
5. **Skincare Recommendations**: ingredients and product suggestions
6. **When to See a Doctor**: 

Format the response in plain text with clear headings. Be empathetic and professional. Keep each section concise but informative.
EOT;

// --- Build API payload ---
$payload = [
    'model' => MODEL,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $data_uri]
                ]
            ]
        ]
    ],
    'max_tokens' => 1500,
    'temperature' => 0.3
];

// --- Call OpenRouter ---
$ch = curl_init(OPENROUTER_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'HTTP-Referer: http://thebizportwebs.online/aiskin',
        'X-Title: AI Skin Analysis'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

if ($http_code !== 200 || isset($result['error'])) {
    $error_msg = $result['error']['message'] ?? "HTTP $http_code - Unknown error";
    http_response_code(502);
    echo json_encode(['error' => 'API Error: ' . $error_msg]);
    exit;
}

$analysis = $result['choices'][0]['message']['content'] ?? '';

if (empty($analysis)) {
    http_response_code(500);
    echo json_encode(['error' => 'No analysis returned from AI.']);
    exit;
}

// Return the raw analysis text (frontend will parse & display)
echo json_encode(['analysis' => $analysis]);
exit;