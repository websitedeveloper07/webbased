<?php
// /gate/validkey.php
// Works with ATOMIC rotate.php | Zero downtime | Auto-recovery

header('Content-Type: application/json');

// === FUNCTION: validateApiKey() — RETURNS ARRAY ONLY ===
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === NO KEY PROVIDED ===
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

    $keyFile    = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    // === FILES MISSING OR CORRUPT → AUTO-FIX ===
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        triggerRotation();
        return validateApiKey(); // Retry once
    }

    $storedKey    = trim(file_get_contents($keyFile));
    $storedExpiry = (int)trim(file_get_contents($expiryFile));

    // === KEY CORRUPT OR EXPIRED → AUTO-FIX ===
    if (strlen($storedKey) !== 128 || $storedExpiry <= time()) {
        triggerRotation();
        $storedKey    = trim(file_get_contents($keyFile));
        $storedExpiry = (int)trim(file_get_contents($expiryFile));
    }

    // === FINAL CHECK ===
    if ($apiKey !== $storedKey) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === KEY IS VALID ===
    return ['valid' => true];
}

// === HELPER: TRIGGER ROTATE.PHP SAFELY ===
function triggerRotation() {
    // Only trigger from same server
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header'  => "User-Agent: validkey-recovery\r\n"
        ]
    ]);
    @file_get_contents('http://127.0.0.1/refresh_cache.php', false, $context);
}

// === DIRECT CALL (curl https://cxchk.site/gate/validkey.php) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int)trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid'           => true,
            'expires_at'      => date('Y-m-d H:i:s', $expiry),
            'remaining_seconds' => $expiry - time()
        ]);
    } else {
        echo json_encode($result['response']);
    }
    exit;
}
?>
