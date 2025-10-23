<?php
// rotate.php - POST-ONLY + SECRET KEY FROM POST
// Key: vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR

// === 1. ALLOW POST ONLY ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 2. SECRET KEY (HARD-CODED) ===
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';

// === 3. GET KEY FROM POST BODY ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['key'] ?? '');

// === 4. VALIDATE KEY ===
if ($providedKey !== $SECRET_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 5. RATE LIMIT (1 call per 30 sec) ===
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

// === 6. CORE: GENERATE OR REUSE API KEY ===
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

// === AUTO-CREATE FILES ===
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
    // REUSE
    echo json_encode([
        'apiKey' => $storedKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused',
        'source' => 'tmp_file',
        'remaining_seconds' => $storedExpiry - $currentTime
    ]);
    exit;
}

// === GENERATE NEW KEY (ATOMIC SWAP) ===
$newKey = generateApiKey();
$newExpiry = $currentTime + 3600; // 1 hour

file_put_contents($tempKey, $newKey, LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);

rename($tempKey, $keyFile);
rename($tempExpiry, $expiryFile);

echo json_encode([
    'apiKey' => $newKey,
    'expires_at' => date('Y-m-d H:i:s', $newExpiry),
    'status' => 'generated',
    'source' => 'tmp_file',
    'valid_for_seconds' => 3600
]);
?>
