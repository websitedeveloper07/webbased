<?php
// refresh_cache.php
// Lazy API key generation — only creates new key when expired
// Key validity: 6 hours (21600 seconds)

header('Content-Type: application/json');

// === 1. BLOCK GET → 403 ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 2. GET KEY FROM POST ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');

$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';
if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 3. FILE PATHS ===
$keyFile    = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey    = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';
$lockFile   = '/tmp/rotate_generation.lock';

// === 4. LOCK FOR SAFE CONCURRENCY ===
$fp = fopen($lockFile, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['error' => 'System busy']);
    exit;
}

$currentTime = time();

// === 5. AUTO-CREATE FILES IF MISSING ===
if (!file_exists($keyFile)) file_put_contents($keyFile, '', LOCK_EX);
if (!file_exists($expiryFile)) file_put_contents($expiryFile, '0', LOCK_EX);

// === 6. READ CURRENT KEY ===
$storedKey    = trim(@file_get_contents($keyFile));
$storedExpiry = (int)trim(@file_get_contents($expiryFile));

// === 7. IF KEY STILL VALID → REUSE ===
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

// === 8. GENERATE NEW KEY ONLY WHEN EXPIRED ===
function generateApiKey(): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < 128; $i++) {
        $key .= $chars[random_int(0, 61)];
    }
    return $key;
}

$newKey = generateApiKey();
$newExpiry = $currentTime + 21600; // 6 hours

// === 9. ATOMIC SWAP ===
file_put_contents($tempKey, $newKey, LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);
rename($tempKey, $keyFile);
rename($tempExpiry, $expiryFile);

// === 10. RELEASE LOCK ===
flock($fp, LOCK_UN);
fclose($fp);

// === 11. SUCCESS RESPONSE ===
echo json_encode([
    'apiKey'            => $newKey,
    'expires_at'        => date('Y-m-d H:i:s', $newExpiry),
    'status'            => 'generated',
    'valid_for_seconds' => 21600
]);
?>
