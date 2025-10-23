<?php
// /gate/validkey.php
// Validates API key from /rotate.php (stored in /tmp)
// Exact match + format + expiry check

header('Content-Type: application/json');

// === 1. Get API key from header ===
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

// === 2. Basic checks ===
if (empty($apiKey)) {
    echo json_encode([
        'valid' => false,
        'error' => 'No API key provided in X-API-KEY header'
    ]);
    exit;
}

if (strlen($apiKey) !== 128) {
    echo json_encode([
        'valid' => false,
        'error' => 'Invalid key length: must be exactly 128 characters'
    ]);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9]{128}$/', $apiKey)) {
    echo json_encode([
        'valid' => false,
        'error' => 'Invalid characters: only alphanumeric allowed'
    ]);
    exit;
}

// === 3. Read stored key and expiry from /tmp ===
$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';

if (!file_exists($keyFile) || !file_exists($expiryFile)) {
    echo json_encode([
        'valid' => false,
        'error' => 'Key storage not initialized. Contact admin.'
    ]);
    exit;
}

$storedKey = trim(file_get_contents($keyFile));
$storedExpiry = (int) trim(file_get_contents($expiryFile));

// === 4. Validate expiry ===
$currentTime = time();
if ($currentTime >= $storedExpiry) {
    echo json_encode([
        'valid' => false,
        'error' => 'API key has expired',
        'expired_at' => date('Y-m-d H:i:s', $storedExpiry)
    ]);
    exit;
}

// === 5. Exact key match ===
if ($apiKey !== $storedKey) {
    echo json_encode([
        'valid' => false,
        'error' => 'Invalid API key: does not match current key'
    ]);
    exit;
}

// === 6. SUCCESS ===
echo json_encode([
    'valid' => true,
    'key_id' => substr($apiKey, 0, 8) . '...',
    'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
    'remaining_seconds' => $storedExpiry - $currentTime,
    'source' => 'rotate.php via /tmp'
]);
?>
