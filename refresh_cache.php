<?php
// refresh_cache.php
// Secure API key rotation script — cxchk.site
// Generates a new 128-character alphanumeric key every 2 hours (7200s).
// Atomic swap | Safe concurrency | JSON response

header('Content-Type: application/json');

// === 1. ALLOW ONLY POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// === 2. SECRET VALIDATION ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'; // store in env var in production

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === 3. FILE PATHS ===
$keyFile    = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey    = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';
$lockFile   = '/tmp/rotate_generation.lock';

// === 4. LOCK TO PREVENT RACE CONDITIONS ===
$fp = fopen($lockFile, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['error' => 'Lock failed']);
    exit;
}

// === 5. AUTO-CREATE FILES IF MISSING ===
if (!file_exists($keyFile))    file_put_contents($keyFile, '', LOCK_EX);
if (!file_exists($expiryFile)) file_put_contents($expiryFile, '0', LOCK_EX);

// === 6. READ CURRENT STATE ===
$currentTime  = time();
$storedKey    = trim(@file_get_contents($keyFile));
$storedExpiry = (int)trim(@file_get_contents($expiryFile));

// === 7. IF VALID KEY EXISTS → REUSE ===
if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode([
        'apiKey'            => $storedKey,
        'expires_at'        => date('Y-m-d H:i:s', $storedExpiry),
        'status'            => 'reused',
        'remaining_seconds' => $storedExpiry - $currentTime
    ]);
    exit;
}

// === 8. GENERATE NEW KEY ===
function generateApiKey(): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < 128; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $key;
}

$newKey    = generateApiKey();
$newExpiry = $currentTime + 7200; // 2 hours validity

// === 9. ATOMIC SWAP ===
file_put_contents($tempKey, $newKey, LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);
rename($tempKey, $keyFile);
rename($tempExpiry, $expiryFile);

// === 10. RELEASE LOCK ===
flock($fp, LOCK_UN);
fclose($fp);

// === 11. RESPONSE ===
echo json_encode([
    'apiKey'            => $newKey,
    'expires_at'        => date('Y-m-d H:i:s', $newExpiry),
    'status'            => 'generated',
    'valid_for_seconds' => 7200
]);
