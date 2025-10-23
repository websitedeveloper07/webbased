<?php
// rotate.php - FINAL VERSION
// One key per hour, reused until expiry
// Uses absolute paths + error logging

// === CONFIGURATION ===
$baseDir = __DIR__; // Current directory of this file
$keyFile = $baseDir . '/api_key.txt';
$expiryFile = $baseDir . '/api_expiry.txt';

// Optional: Log path for debugging
error_log("rotate.php called. Key file: $keyFile");

// === HELPER: Generate 128-char key ===
function generateApiKey() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < 128; $i++) {
        $apiKey .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

// === MAIN LOGIC ===
header('Content-Type: application/json');
$currentTime = time();

$storedKey = '';
$storedExpiry = 0;
$keyIsValid = false;

// === READ EXISTING KEY ===
if (file_exists($keyFile) && file_exists($expiryFile)) {
    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    if ($storedKey && strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
        $keyIsValid = true;
    }
}

// === REUSE OR GENERATE ===
if ($keyIsValid) {
    // Reuse existing key
    echo json_encode([
        'apiKey' => $storedKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused',
        'source' => 'file'
    ]);
    error_log("Key reused: " . substr($storedKey, 0, 8) . "...");
} else {
    // Generate new key
    $apiKey = generateApiKey();
    $expiryTime = $currentTime + 3600; // 1 hour

    // === SAVE TO FILES (WITH ERROR CHECK) ===
    $saveKey = file_put_contents($keyFile, $apiKey);
    $saveExpiry = file_put_contents($expiryFile, $expiryTime);

    if ($saveKey === false || $saveExpiry === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to save API key files',
            'keyFile' => $keyFile,
            'expiryFile' => $expiryFile,
            'writable' => is_writable($baseDir) ? 'yes' : 'no',
            'php_user' => get_current_user()
        ]);
        error_log("FAILED to save key files!");
        exit;
    }

    // Success
    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $expiryTime),
        'status' => 'generated',
        'source' => 'new'
    ]);
    error_log("New key generated and saved.");
}
?>
