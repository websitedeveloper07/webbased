<?php
// rotate.php - NO 429 ON RELOAD | ONLY 1 REQUEST GENERATES KEY

// === 1. BLOCK GET → 403 ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 2. GET KEY FROM POST (lund) ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');

$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';
if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 3. GLOBAL LOCK: ONLY 1 REQUEST GENERATES KEY ===
$lockFile = '/tmp/rotate_generation.lock';
$fp = fopen($lockFile, 'c+') or die('Lock error');

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['error' => 'System busy']);
    exit;
}

$currentTime = time();
$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';

// === AUTO-CREATE FILES ===
if (!file_exists($keyFile)) file_put_contents($keyFile, '', LOCK_EX);
if (!file_exists($expiryFile)) file_put_contents($expiryFile, '0', LOCK_EX);

// === READ CURRENT KEY ===
$storedKey = trim(file_get_contents($keyFile));
$storedExpiry = (int)trim(file_get_contents($expiryFile));

// === IF KEY VALID → REUSE (ALL REQUESTS) ===
if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    flock($fp, LOCK_UN);
    fclose($fp);
    header('Content-Type: application/json');
    echo json_encode([
        'apiKey' => $storedKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused',
        'source' => 'tmp_file',
        'remaining_seconds' => $storedExpiry - $currentTime
    ]);
    exit;
}

// === ONLY 1ST REQUEST GENERATES NEW KEY ===
function generateApiKey() {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < 128; $i++) {
        $key .= $chars[random_int(0, 61)];
    }
    return $key;
}

$newKey = generateApiKey();
$newExpiry = $currentTime + 3600;

// === ATOMIC SWAP ===
file_put_contents($tempKey, $newKey, LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);
rename($tempKey, $keyFile);
rename($tempExpiry, $expiryFile);

// === RELEASE LOCK ===
flock($fp, LOCK_UN);
fclose($fp);

// === SUCCESS RESPONSE ===
header('Content-Type: application/json');
echo json_encode([
    'apiKey' => $newKey,
    'expires_at' => date('Y-m-d H:i:s', $newExpiry),
    'status' => 'generated',
    'source' => 'tmp_file',
    'valid_for_seconds' => 3600
]);
?>
