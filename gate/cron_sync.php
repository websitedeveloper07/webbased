<?php
// Hardcoded API key
 $STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

function validateApiKey($inputKey) {
    global $STATIC_API_KEY;
    return $inputKey === $STATIC_API_KEY;
}

// Only process if this file is accessed directly (not included)
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden acess']);
        exit;
    }

    // Log request details
    $rawInput = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' Request: Method=POST, RawInput=' . $rawInput . ', Headers=' . print_r(getallheaders(), true) . PHP_EOL, FILE_APPEND);

    // Return hardcoded API key only when accessed directly
    echo json_encode([
        'apiKey' => $STATIC_API_KEY,
        'status' => 'success'
    ]);
}
