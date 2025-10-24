<?php
header('Content-Type: application/json');

// Restrict to POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $errorMsg = ['error' => 'Method not allowed'];
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Error 405: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Parse input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST; // Fallback to form data
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Non-JSON input: ' . print_r($_POST, true) . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' JSON input: ' . $rawInput . PHP_EOL, FILE_APPEND);
}

// Log headers and server info for debugging
file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Headers: ' . print_r(getallheaders(), true) . PHP_EOL, FILE_APPEND);
file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Server: ' . print_r($_SERVER, true) . PHP_EOL, FILE_APPEND);

// Check for key (try 'lund', 'key', or 'api_key')
$providedKey = trim($input['lund'] ?? $input['key'] ?? $input['api_key'] ?? '');
if (empty($providedKey)) {
    http_response_code(400);
    $errorMsg = [
        'error' => 'Missing or empty key',
        'received_input' => $input,
        'raw_input' => $rawInput,
        'post_data' => $_POST
    ];
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Error 400: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Authorization
$SECRET_KEY = getenv('SECRET_KEY') ?: 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';
if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    $errorMsg = ['error' => 'Invalid key', 'provided_key' => $providedKey];
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Static API key
$STATIC_API_KEY = getenv('STATIC_API_KEY') ?: 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

echo json_encode([
    'apiKey' => $STATIC_API_KEY,
    'status' => 'success'
]);
