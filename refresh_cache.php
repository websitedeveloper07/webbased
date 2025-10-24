<?php
// refresh_cache.php
// Single API key, lazy generation, 6-hour validity

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

$keyFile    = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';
$lockFile   = '/tmp/rotate_generation.lock';

$fp = fopen($lockFile, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['error' => 'System busy']);
    exit;
}

$currentTime = time();
$storedKey    = trim(@file_get_contents($keyFile));
$storedExpiry = (int)trim(@file_get_contents($expiryFile));

// If key exists and still valid → reuse
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

// Generate new key
$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$newKey = '';
for ($i = 0; $i < 128; $i++) {
    $newKey .= $chars[random_int(0, 61)];
}
$newExpiry = $currentTime + 21600; // 6 hours

// Save atomically
file_put_contents("$keyFile.tmp", $newKey, LOCK_EX);
file_put_contents("$expiryFile.tmp", $newExpiry, LOCK_EX);
rename("$keyFile.tmp", $keyFile);
rename("$expiryFile.tmp", $expiryFile);

flock($fp, LOCK_UN);
fclose($fp);

// Return new key
echo json_encode([
    'apiKey'            => $newKey,
    'expires_at'        => date('Y-m-d H:i:s', $newExpiry),
    'status'            => 'generated',
    'valid_for_seconds' => 21600
]);
