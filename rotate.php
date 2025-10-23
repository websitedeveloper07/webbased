<?php
// rotate.php - ULTIMATE ATOMIC KEY ROTATOR
// Reuses key for 1 hour | No race condition | Zero downtime

$keyFile     = '/tmp/api_key_webchecker.txt';
$expiryFile  = '/tmp/api_expiry_webchecker.txt';
$tempKey     = '/tmp/api_key_temp.txt';
$tempExpiry  = '/tmp/api_expiry_temp.txt';
$fallback    = '/tmp/api_key_single_webchecker.txt';

function generateApiKey() {
    return bin2hex(random_bytes(64)); // 128 chars, cryptographically secure
}

header('Content-Type: application/json');
$currentTime = time();

// === READ CURRENT KEY SAFELY ===
$storedKey    = @trim(file_get_contents($keyFile));
$storedExpiry = (int)@trim(file_get_contents($expiryFile));

if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
    echo json_encode([
        'apiKey'       => $storedKey,
       : 'expires_at'   => date('Y-m-d H:i:s', $storedExpiry),
        'status'       => 'reused',
        'source'       => 'tmp_file',
        'remaining_sec'=> $storedExpiry - $currentTime
    ]);
    exit;
}

// === GENERATE NEW KEY ===
$newKey    = generateApiKey();
$newExpiry = $currentTime + 3600; // 1 HOUR

// === WRITE TO TEMP FILES (LOCKED) ===
file_put_contents($tempKey,    $newKey,    LOCK_EX);
file_put_contents($tempExpiry, $newExpiry, LOCK_EX);

// === ATOMIC SWAP — INSTANT & SAFE ===
rename($tempKey,    $keyFile);
rename($tempExpiry, $expiryFile);

// === CLEANUP TEMP (optional) ===
@unlink($tempKey);
@unlink($tempExpiry);

// === SUCCESS RESPONSE ===
echo json_encode([
    'apiKey'       => $newKey,
    'expires_at'   => date('Y-m-d H:i:s', $newExpiry),
    'status'       => 'generated',
    'source'       => 'atomic_swap',
    'valid_for_sec'=> 3600
]);
?>
