<?php
// rotate.php - WORKS UNDER ROOT
// One key per hour, reused until expiry
// Auto-creates files, forces permissions

$baseDir = __DIR__;
$keyFile = $baseDir . '/api_key.txt';
$expiryFile = $baseDir . '/api_expiry.txt';

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
    file_put_contents($keyFile, '');
    chmod($keyFile, 0644);
}
if (!file_exists($expiryFile)) {
    file_put_contents($expiryFile, '');
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
        'source' => 'file'
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
        // Even root can't fail — but just in case
        echo json_encode([
            'error' => 'Failed to save key files (impossible under root?)',
            'keyFile' => $keyFile,
            'disk_free' => disk_free_space($baseDir)
        ]);
        exit;
    }

    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $expiryTime),
        'status' => 'generated',
        'source' => 'new'
    ]);
}
?>
