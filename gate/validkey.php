<?php
header('Content-Type: application/json');

// Hardcoded API key
$STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

function validateApiKey() {
    // Simulate validation (always valid since no input key is required)
    $response = [
        'apiKey' => $GLOBALS['STATIC_API_KEY'],
        'status' => 'success'
    ];
    return [
        'valid' => true,
        'response' => $response
    ];
}

// Handle API request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $errorMsg = ['error' => 'Method not allowed'];
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' Error 405: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Log request details
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' Request: Method=POST, RawInput=' . $rawInput . ', Headers=' . print_r(getallheaders(), true) . PHP_EOL, FILE_APPEND);

// Return hardcoded API key
echo json_encode([
    'apiKey' => $STATIC_API_KEY,
    'status' => 'success'
]);
