<?php
header('Content-Type: application/json');

// Hardcoded API key
$STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

function validateApiKey() {
    // Always valid since no input key is required
    return [
        'valid' => true,
        'response' => ['apiKey' => $GLOBALS['STATIC_API_KEY']]
    ];
}

// Handle API request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $errorMsg = ['error' => 'Method not allowed'];
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' Error 405: ' . json_encode($errorMsg) . ' Headers: ' . print_r(getallheaders(), true) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Log request details
$rawInput = file_get_contents('php://input');
$headers = getallheaders();
$logMsg = date('Y-m-d H:i:s') . ' Request: Method=POST, RawInput=' . $rawInput . ', Headers=' . print_r($headers, true);
$logMsg .= ', User-Agent=' . ($headers['User-Agent'] ?? 'Unknown');
file_put_contents(__DIR__ . '/debug.log', $logMsg . PHP_EOL, FILE_APPEND);

// Return hardcoded API key
echo json_encode(['apiKey' => $STATIC_API_KEY]);
