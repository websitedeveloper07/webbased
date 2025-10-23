<?php
// rotate.php - ULTIMATE ROOT-FIXED VERSION
// Uses /tmp for guaranteed writability
// One key per hour, reused until expiry

$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';

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

$storedKey = '';
$storedExpiry = 0;
$keyIsValid = false;

// === AUTO-CREATE FILES IF MISSING ===
if (!file_exists($keyFile)) {
    if (file_put_contents($keyFile, '') === false) {
        echo json_encode(['error' => 'Cannot create key file in /tmp']);
        exit;
    }
    chmod($keyFile, 0644);
}
if (!file_exists($expiryFile)) {
    if (file_put_contents($expiryFile, '') === false) {
        echo json_encode(['error' => 'Cannot create expiry file in /tmp']);
        exit;
    }
    chmod($expiryFile, 0644);
}

// === READ EXISTING KEY ===
if (file_exists($keyFile) && file_exists($expiryFile)) {
    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
        $keyIsValid = true;
    }
}

// === REUSE OR GENERATE NEW ===
if ($keyIsValid) {
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

    // === SAVE WITH FORCE ===
    if (
        file_put_contents($keyFile, $apiKey) === false ||
        file_put_contents($expiryFile, $expiryTime) === false
    ) {
        // Last resort: fallback to a single file
        $singleFile = '/tmp/api_key_single_webchecker.txt';
        $data = base64_encode($apiKey . '|' . $expiryTime);
        if (file_put_contents($singleFile, $data) === false) {
            http_response_code(500);
            echo json_encode([
                'error' => 'ALL file writes failed - check server disk',
                'disk_free' => disk_free_space('/'),
                'tmp_writable' => is_writable('/tmp') ? 'yes' : 'no'
            ]);
            exit;
        }
        echo json_encode([
            'apiKey' => $apiKey,
            'expires_at' => date('Y-m-d H:i:s', $expiryTime),
            'status' => 'generated_fallback_single',
            'source' => 'single_tmp_file'
        ]);
        exit;
    }

    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $expiryTime),
        'status' => 'generated',
        'source' => 'tmp_file'
    ]);
}
?>
