<?php
// /gate/validkey.php
// Validates API key from /tmp + format + expiry

header('Content-Type: application/json');

// === FUNCTION: validateApiKey() — FOR require_once ===
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'No API key provided']);
        return false;
    }

    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Invalid key format']);
        return false;
    }

    $keyFile = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        http_response_code(500);
        echo json_encode(['valid' => false, 'error' => 'Key system not initialized']);
        return false;
    }

    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    if (time() >= $storedExpiry) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'API key expired']);
        return false;
    }

    if ($apiKey !== $storedKey) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Invalid API key']);
        return false;
    }

    // === KEY IS VALID ===
    return true;
}

// === DIRECT CALL (for testing via curl) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    if (validateApiKey()) {
        $storedExpiry = (int) trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid' => true,
            'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
            'remaining_seconds' => $storedExpiry - time()
        ]);
    }
    // validateApiKey() already outputs error + exits
    exit;
}
?>
