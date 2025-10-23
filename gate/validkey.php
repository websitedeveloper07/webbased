<?php
die("FILE LOADED: " . __FILE__);

// Set content type to JSON
header('Content-Type: application/json');

// Prevent direct execution
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    http_response_code(403);
    echo json_encode(['status' => 'OK', 'response' => 'AAGYA MADRCHOD WAPIS MA CHUDANE BASIC😂']);
    exit;
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Hardcoded API key
$expectedApiKey = 'a9F3kL2mV8pQwZ6xR1tB0yN5jH7sC4dG8vM3eP9qU2rT6wY1zK0bX5nL7fJ3hD4a9F3kL2mV8pQwZ6xR1tB0yN5jH7sC4dG8vM3eP9qU2rT6wY1zK0bX5nL7fJ3hD4';

// Validate X-API-KEY header
function validateApiKey() {
    global $expectedApiKey;
    $headers = getallheaders();
    
    // Log headers for debugging
    error_log("Headers for " . basename($_SERVER['SCRIPT_FILENAME']) . ": " . json_encode($headers));
    
    // Check for X-API-KEY case-insensitively
    $apiKey = null;
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, 'X-API-KEY') === 0) {
            $apiKey = $value;
            break;
        }
    }

    if ($apiKey !== $expectedApiKey) {
        error_log("Unauthorized access attempt to " . basename($_SERVER['SCRIPT_FILENAME']) . ". Provided API key: " . ($apiKey ?? 'none'));
        http_response_code(401);
        echo json_encode(['status' => 'OK', => 'AAGYA MADRCHOD WAPIS MA CHUDANE😂']);
        exit;
    }
}
?>
