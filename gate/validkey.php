<?php
// /gate/validkey.php
// Secure API key validator — cxchk.site
// Works with refresh_cache.php | No HTTP calls | 2-hour validity | Self-healing

header('Content-Type: application/json');

// === FUNCTION: validateApiKey() — RETURNS ARRAY ONLY ===
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === 1. NO KEY PROVIDED ===
    if (empty($apiKey)) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === 2. INVALID FORMAT ===
    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
        return [
            'valid' => false,
            'response' => ['valid' => false, 'error' => 'Invalid key format']
        ];
    }

    $keyFile    = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    // === 3. FILE MISSING OR CORRUPT ===
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'API KEY NOT GENERATED YET']
        ];
    }

    $storedKey    = trim(@file_get_contents($keyFile));
    $storedExpiry = (int)trim(@file_get_contents($expiryFile));

    // === 4. KEY EXPIRED OR INVALID ===
    if (strlen($storedKey) !== 128 || $storedExpiry <= time()) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'API KEY EXPIRED']
        ];
    }

    // === 5. FINAL CHECK ===
    if ($apiKey !== $storedKey) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === 6. KEY IS VALID ===
    return ['valid' => true];
}

// === DIRECT CALL (curl https://cxchk.site/gate/validkey.php) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int)trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
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
