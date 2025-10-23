<?php
// rotate.php - POST-ONLY, KEY-REQUIRED, NO DIRECT ACCESS
// Uses /tmp for keys | Reuses for 1 hour | Denies all else

// === 1. BLOCK DIRECT ACCESS (NO GET, NO BROWSER) ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === 2. LOAD SECRET KEY FROM rotatekey.php ===
$secretKeyFile = __DIR__ . '/rotatekey.php';
if (!file_exists($secretKeyFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Key file missing']);
    exit;
}
$SECRET_KEY = require $secretKeyFile;
if (!is_string($SECRET_KEY) || strlen($SECRET_KEY) < 10) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid key file']);
    exit;
}

// === 3. GET KEY FROM POST BODY ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = $input['key'] ?? '';

if (empty($providedKey) || $providedKey !== $SECRET_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Key denied']);
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

// === 5. CORE LOGIC (YOUR ORIGINAL + ATOMIC) ===
$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$tempKey = '/tmp/api_key_temp.txt';
$tempExpiry = '/tmp/api_expiry_temp.txt';

function generateApiKey() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < 128; $i++) {
        $apiKey .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

header('Content-Type: application/json');
$currentTime = time();

// === AUTO-CREATE FILES IF MISSING ===
if (!file_exists($keyFile)) {
    if (file_put_contents($keyFile, '', LOCK_EX) === false) {
        echo json_encode(['error' => 'Cannot create key file']);
        exit;
    }
    chmod($keyFile, 0644);
}
if (!file_exists($expiryFile)) {
    if (file_put_contents($expiryFile, '0', LOCK_EX) === false) {
        echo json_encode(['error' => 'Cannot create expiry file']);
        exit;
    }
    chmod($expiryFile, 0644);
}

// === READ EXISTING KEY ===
$storedKey = trim(file_get_contents($keyFile));
$storedExpiry = (int) trim(file_get_contents($expiryFile));

if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    echo json_encode([
        'apiKey' => $storedKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused',
        'source' => 'tmp_file'
    ]);
} else {
    // Generate new key
    $apiKey = generateApiKey();
    $expiryTime = $currentTime + 3600; // 1 hour

    // === WRITE TO TEMP + ATOMIC SWAP ===
    file_put_contents($tempKey, $apiKey, LOCK_EX);
    file_put_contents($tempExpiry, $expiryTime, LOCK_EX);

    rename($tempKey, $keyFile);
    rename($tempExpiry, $expiryFile);

    // === SUCCESS ===
    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $expiryTime),
        'status' => 'generated',
        'source' => 'tmp_file'
    ]);
}
?>
