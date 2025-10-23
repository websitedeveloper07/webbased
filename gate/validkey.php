<?php
// /gate/validkey.php
// SAFE VERSION — does not break when included in your website

// === Only send JSON headers when accessed directly ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    header('Content-Type: application/json');
}

/**
 * validateApiKey()
 * ----------------
 * Checks the X-API-KEY header against the stored key and expiry.
 * Returns an associative array with 'valid' => true/false
 */
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === Missing key ===
    if (empty($apiKey)) {
        return [
            'valid' => false,
            'response' => [
                'status' => 'error',
                'message' => 'Missing API key'
            ]
        ];
    }

    // === Invalid format ===
    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
        return [
            'valid' => false,
            'response' => [
                'status' => 'error',
                'message' => 'Invalid API key format'
            ]
        ];
    }

    // === File locations ===
    $keyFile = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    // === Check existence ===
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        return [
            'valid' => false,
            'response' => [
                'status' => 'error',
                'message' => 'API key system not initialized'
            ]
        ];
    }

    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    // === Expired ===
    if (time() >= $storedExpiry) {
        return [
            'valid' => false,
            'response' => [
                'status' => 'error',
                'message' => 'API key expired'
            ]
        ];
    }

    // === Mismatch ===
    if ($apiKey !== $storedKey) {
        return [
            'valid' => false,
            'response' => [
                'status' => 'error',
                'message' => 'Invalid API key'
            ]
        ];
    }

    // === Success ===
    return ['valid' => true];
}

// === Only output if this file is directly accessed (not included) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int) trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid' => true,
            'expires_at' => date('Y-m-d H:i:s', $expiry),
            'remaining_seconds' => $expiry - time()
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode($result['response'], JSON_PRETTY_PRINT);
    }
    exit;
}
?>
