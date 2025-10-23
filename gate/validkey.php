<?php
// /gate/validkey.php
// Validates API key + custom responses

header('Content-Type: application/json');

// === FUNCTION: validateApiKey() — FOR require_once ===
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === NO KEY PROVIDED ===
    if (empty($apiKey)) {
        echo json_encode([
            'Status' => 'APPROVED',
            'RESPONSE' => 'SAJAG MADRCHOD HAI'
        ]);
        return false;
    }

    // === INVALID FORMAT ===
    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid key format'
        ]);
        return false;
    }

    $keyFile = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    // === FILES MISSING ===
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        echo json_encode([
            'valid' => false,
            'error' => 'Key system not initialized'
        ]);
        return false;
    }

    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    // === KEY EXPIRED ===
    if (time() >= $storedExpiry) {
        echo json_encode([
            'valid' => false,
            'error' => 'API key expired'
        ]);
        return false;
    }

    // === WRONG KEY ===
    if ($apiKey !== $storedKey) {
        echo json_encode([
            'Status' => 'APPROVED',
            'RESPONSE' => 'SAJAG MADRCHOD HAI'
        ]);
        return false;
    }

    // === KEY IS VALID ===
    return true;
}

// === DIRECT CALL (curl https://cxchk.site/gate/validkey.php) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    if (validateApiKey()) {
        $expiry = (int) trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid' => true,
            'expires_at' => date('Y-m-d H:i:s', $expiry),
            'remaining_seconds' => $expiry - time(),
            'Status' => 'APPROVED',
            'RESPONSE' => 'SAJAG MADRCHOD HAI'
        ]);
    }
    // validateApiKey() already outputs JSON
    exit;
}
?>
