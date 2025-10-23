<?php
// rotate.php - BEAST MODE ROOT-FIXED VERSION
// Uses /tmp for guaranteed writability
// Pre-generates next key 10 min before expiry to avoid downtime

header('Content-Type: application/json');

$currentTime = time();

// Files
$currentKeyFile = '/tmp/api_key_current_webchecker.txt';
$currentExpiryFile = '/tmp/api_expiry_current_webchecker.txt';
$nextKeyFile = '/tmp/api_key_next_webchecker.txt';
$nextExpiryFile = '/tmp/api_expiry_next_webchecker.txt';

// Key length & pre-gen window
define('KEY_LENGTH', 128);
define('PREGEN_SECONDS', 600); // 10 minutes

// === Generate secure API key ===
function generateApiKey($length = KEY_LENGTH) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < $length; $i++) {
        $apiKey .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

// === Ensure files exist ===
$files = [
    $currentKeyFile, $currentExpiryFile, 
    $nextKeyFile, $nextExpiryFile
];
foreach ($files as $f) {
    if (!file_exists($f)) {
        file_put_contents($f, '');
        chmod($f, 0600);
    }
}

// === Atomic read/write using flock ===
$fp = fopen($currentKeyFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot open key file']);
    exit;
}

if (flock($fp, LOCK_EX)) {

    // Read current key/expiry
    $currentKey = trim(file_get_contents($currentKeyFile));
    $currentExpiry = (int) trim(file_get_contents($currentExpiryFile));

    // Read next key/expiry
    $nextKey = trim(file_get_contents($nextKeyFile));
    $nextExpiry = (int) trim(file_get_contents($nextExpiryFile));

    // === Pre-generate next key if within PREGEN_SECONDS ===
    if ($currentExpiry - $currentTime <= PREGEN_SECONDS && empty($nextKey)) {
        $nextKey = generateApiKey();
        $nextExpiry = $currentExpiry + 3600; // valid 1 hour after current
        file_put_contents($nextKeyFile, $nextKey, LOCK_EX);
        file_put_contents($nextExpiryFile, $nextExpiry, LOCK_EX);
    }

    // === Swap to next key if current expired ===
    if ($currentExpiry <= $currentTime && !empty($nextKey)) {
        $currentKey = $nextKey;
        $currentExpiry = $nextExpiry;

        file_put_contents($currentKeyFile, $currentKey, LOCK_EX);
        file_put_contents($currentExpiryFile, $currentExpiry, LOCK_EX);

        // Clear next key
        file_put_contents($nextKeyFile, '', LOCK_EX);
        file_put_contents($nextExpiryFile, '', LOCK_EX);
    }

    // If no valid current key, generate fresh one
    if (strlen($currentKey) !== KEY_LENGTH || $currentExpiry <= $currentTime) {
        $currentKey = generateApiKey();
        $currentExpiry = $currentTime + 3600;
        file_put_contents($currentKeyFile, $currentKey, LOCK_EX);
        file_put_contents($currentExpiryFile, $currentExpiry, LOCK_EX);
    }

    flock($fp, LOCK_UN);
}
fclose($fp);

// === Output API info ===
echo json_encode([
    'apiKey' => $currentKey,
    'expires_at' => date('Y-m-d H:i:s', $currentExpiry),
    'status' => 'active',
    'nextKeyExists' => !empty($nextKey) ? 'yes' : 'no'
]);
?>
