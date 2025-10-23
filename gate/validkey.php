<?php
// /gate/validkey.php
// DO NOT echo when included via require_once

header('Content-Type: application/json');

// Files (match rotate.php)
define('CURRENT_KEY_FILE', '/tmp/api_key_current_webchecker.txt');
define('CURRENT_EXPIRY_FILE', '/tmp/api_expiry_current_webchecker.txt');
define('NEXT_KEY_FILE', '/tmp/api_key_next_webchecker.txt');
define('NEXT_EXPIRY_FILE', '/tmp/api_expiry_next_webchecker.txt');

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

    // === FILE CHECKS ===
    $files = [CURRENT_KEY_FILE, CURRENT_EXPIRY_FILE, NEXT_KEY_FILE, NEXT_EXPIRY_FILE];
    foreach ($files as $f) {
        if (!file_exists($f)) {
            return [
                'valid' => false,
                'response' => ['valid' => false, 'error' => 'Key system not initialized']
            ];
        }
    }

    // === READ KEYS & EXPIRIES ===
    $currentKey = trim(file_get_contents(CURRENT_KEY_FILE));
    $currentExpiry = (int) trim(file_get_contents(CURRENT_EXPIRY_FILE));

    $nextKey = trim(file_get_contents(NEXT_KEY_FILE));
    $nextExpiry = (int) trim(file_get_contents(NEXT_EXPIRY_FILE));

    $now = time();

    // === EXPIRED CHECK ===
    if ($now >= $currentExpiry && ($nextKey === '' || $now >= $nextExpiry)) {
        return [
            'valid' => false,
            'response' => ['valid' => false, 'error' => 'API key expired']
        ];
    }

    // === VALIDATION ===
    if ($apiKey === $currentKey) {
        return ['valid' => true, 'key_type' => 'current'];
    }

    if ($apiKey === $nextKey) {
        return ['valid' => true, 'key_type' => 'next']; // pre-generated key allowed
    }

    // === INVALID KEY ===
    return [
        'valid' => false,
        'response' => ['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']
    ];
}

// === ONLY RUN WHEN CALLED DIRECTLY (curl) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int) trim(file_get_contents(CURRENT_EXPIRY_FILE));
        echo json_encode([
            'valid' => true,
            'expires_at' => date('Y-m-d H:i:s', $expiry),
            'remaining_seconds' => $expiry - time(),
            'key_type' => $result['key_type']
        ]);
    } else {
        echo json_encode($result['response']);
    }
    exit;
}
?>
