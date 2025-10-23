<?php
// rotate.php - POST-ONLY, KEY FROM POST BODY, NO FILE
// Reuses key for 1 hour | Atomic | Secure

// === 1. BLOCK NON-POST REQUESTS ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 2. GET KEY FROM POST BODY (JSON or form) ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['key'] ?? '');

// === 3. VALIDATE KEY (HARD-CODED HERE) ===
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'; // CHANGE THIS

if (empty($providedKey) || $providedKey !== $SECRET_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 4. RATE LIMIT (1 call per 30 sec) ===
$lockFile = '/tmp/rotate_lock.txt';
if (file_exists($lockFile)) {
    $lastRun = (int)file_get_contents($lockFile);
    if (time() - $lastRun < 30) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limited (30 sec)']);
        exit;
    }
}
file_put_contents($lockFile, time());

// === 5. CORE KEY LOGIC (ATOMIC) ===
$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';

function generateApiKey() {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < 128; $i++) {
        $key .= $chars[random_int(0, 61)];
    }
    return $key;
}

header('Content-Type: application/json');
$currentTime = time();

// === AUTO-CREATE FILES IF MISSING ===
if (!file_exists($keyFile)) {
    file_put_contents($keyFile, '', LOCK_EX);
    chmod($keyFile, 0644);
}
if (!file_exists($expiryFile)) {
    file_put_contents($expiryFile, '0', LOCK_EX);
    chmod($expiryFile, 0644);
}

// === READ CURRENT KEY ===
$storedKey = trim(file_get_contents($keyFile));
$storedExpiry = (int)trim(file_get_contents($expiryFile));

if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    echo json_encode([
        'apiKey' => $storedKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused',
        'source' => 'tmp_file'
    ]);
    exit;
}

// === GENERATE & SWAP ATOMICALLY ===
$newKey = generateApiKey();
$newExpiry = $currentTime + 3600;

file_put_contents($tempKey, $newKey, LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);

rename($tempKey, $keyFile);
rename($tempExpiry, $expiryFile);

echo json_encode([
    'apiKey' => $newKey,
    'expires_at' => date('Y-m-d H:i:s', $newExpiry),
    'status' => 'generated',
    'source' => 'tmp_file'
]);
?>
