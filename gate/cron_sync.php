<?php
// Hardcoded API key
 $STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

function validateApiKey($inputKey) {
    global $STATIC_API_KEY;
    
    // Log validation attempt (first 10 chars for security)
    $logMessage = sprintf(
        "API Key Validation: Received (first 10): %s, Expected (first 10): %s",
        substr($inputKey, 0, 10),
        substr($STATIC_API_KEY, 0, 10)
    );
    file_put_contents(__DIR__ . '/auth_debug.log', date('Y-m-d H:i:s') . ' ' . $logMessage . PHP_EOL, FILE_APPEND);
    
    // Trim and compare
    return trim($inputKey) === trim($STATIC_API_KEY);
}

// Only process if this file is accessed directly (not included)
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
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
