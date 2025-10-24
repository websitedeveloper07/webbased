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

    // === 3. AUTO-RECOVERY: Missing Files ===
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        triggerRotation();
        return validateApiKey(); // Retry once after rotation
    }

    $storedKey    = trim(file_get_contents($keyFile));
    $storedExpiry = (int)trim(file_get_contents($expiryFile));

    // === 4. AUTO-RECOVERY: Corrupt or Expired Key ===
    if (strlen($storedKey) !== 128 || $storedExpiry <= time()) {
        triggerRotation();
        $storedKey    = trim(file_get_contents($keyFile));
        $storedExpiry = (int)trim(file_get_contents($expiryFile));
    }

    // === 5. FINAL CHECK ===
    if ($apiKey !== $storedKey) {
        return [
            'valid' => false,
            'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
        ];
    }

    // === 6. VALID KEY ===
    return ['valid' => true];
}

// === HELPER: Trigger refresh_cache.php Internally (No HTTP) ===
function triggerRotation() {
    $rotatePath = __DIR__ . '/../refresh_cache.php';

    if (!file_exists($rotatePath)) {
        error_log("Rotation script missing at $rotatePath");
        return false;
    }

    // Execute securely in the same PHP runtime
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['lund' => 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'];
    ob_start();
    include $rotatePath;
    ob_end_clean();

    return true;
}

// === DIRECT CALL (e.g. curl https://cxchk.site/gate/validkey.php) ===
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
?>
