<?php
// rotate.php - ONLY 1 REQUEST GENERATES NEW KEY
// Uses file lock + atomic check

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

// === 3. IP-BASED RATE LIMIT (30 sec) ===
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockDir = '/tmp/rotate_locks';
$ipLockFile = "$lockDir/$ip.lock";

if (!is_dir($lockDir)) mkdir($lockDir, 0755, true);

if (file_exists($ipLockFile)) {
    $last = (int)file_get_contents($ipLockFile);
    if (time() - $last < 30) {
        http_response_code(429);
        echo json_encode(['error' => 'Wait 30 sec']);
        exit;
    }
}
file_put_contents($ipLockFile, time());

// === 4. CORE: ONLY 1 REQUEST GENERATES NEW KEY ===
$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';
$rotationLock = '/tmp/rotate_generation.lock'; // GLOBAL LOCK

$fp = fopen($rotationLock, 'c+') or exit('Lock error');
if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    exit('Lock failed');
}

$currentTime = time();

// === AUTO-CREATE FILES ===
if (!file_exists($keyFile)) file_put_contents($keyFile, '', LOCK_EX);
if (!file_exists($expiryFile)) file_put_contents($expiryFile, '0', LOCK_EX);

// === READ CURRENT KEY ===
$storedKey = trim(file_get_contents($keyFile));
$storedExpiry = (int)trim(file_get_contents($expiryFile));

if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    // REUSE — even if expired, wait for 1st to generate
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

header('Content-Type: application/json');
echo json_encode([
    'apiKey' => $newKey,
    'expires_at' => date('Y-m-d H:i:s', $newExpiry),
    'status' => 'generated',
    'source' => 'tmp_file',
    'valid_for_seconds' => 3600
]);
?>
