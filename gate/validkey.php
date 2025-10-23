<?php
// /gate/validkey.php
// DO NOT echo when included via require_once

header('Content-Type: application/json');

// === FUNCTION: validateApiKey() — RETURNS ARRAY ONLY ===
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === NO KEY ===
    if (empty($apiKey)) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === INVALID FORMAT ===
    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
        return [
            'valid' => false,
            'response' => ['valid' => false, 'error' => 'Invalid key format']
        ];
    }

    $keyFile = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        return [
            'valid' => false,
            'response' => ['valid' => false, 'error' => 'Key system not initialized']
        ];
    }

    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    if (time() >= $storedExpiry) {
        return [
            'valid' => false,
            'response' => ['valid' => false, 'error' => 'API key expired']
        ];
    }

    if ($apiKey !== $storedKey) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === VALID KEY ===
    return ['valid' => true];
}

// === ONLY RUN WHEN CALLED DIRECTLY (curl) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int) trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid' => true,
            'expires_at' => date('Y-m-d H:i:s', $expiry),
            'remaining_seconds' => $expiry - time()
        ]);
    } else {
        echo json_encode($result['response']);
    }
    exit;
}
?>
