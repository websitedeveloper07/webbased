<?php
// rotate.php - FIXED VERSION (No 500 errors)
// Handles /tmp issues, random_int, locks, and logs errors

ini_set('display_errors', 1); // TEMP: Show errors for debug
error_reporting(E_ALL);

$keyFile = '/tmp/api_key_webchecker.txt';
$expiryFile = '/tmp/api_expiry_webchecker.txt';

function generateApiKey() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    try {
        for ($i = 0; $i < 128; $i++) {
            $apiKey .= $characters[random_int(0, strlen($characters) - 1)];
        }
    } catch (Exception $e) {
        // Fallback if random_int fails
        for ($i = 0; $i < 128; $i++) {
            $apiKey .= $characters[rand(0, strlen($characters) - 1)];
        }
    }
    return $apiKey;
}

header('Content-Type: application/json');
$currentTime = time();

try {
    // === CHECK /TMP WRITABLE ===
    if (!is_writable('/tmp')) {
        throw new Exception('/tmp is not writable');
    }

    $storedKey = '';
    $storedExpiry = 0;
    $keyIsValid = false;

    // === AUTO-CREATE FILES IF MISSING ===
    if (!file_exists($keyFile)) {
        if (file_put_contents($keyFile, '', LOCK_EX) === false) {
            throw new Exception('Cannot create key file');
        }
        chmod($keyFile, 0644);
    }
    if (!file_exists($expiryFile)) {
        if (file_put_contents($expiryFile, '0', LOCK_EX) === false) {
            throw new Exception('Cannot create expiry file');
        }
        chmod($expiryFile, 0644);
    }

    // === READ EXISTING KEY ===
    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));

    if (strlen($storedKey) === 128 && $storedExpiry > $currentTime) {
        $keyIsValid = true;
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

        // === SAVE WITH LOCK ===
        if (
            file_put_contents($keyFile, $apiKey, LOCK_EX) === false ||
            file_put_contents($expiryFile, $expiryTime, LOCK_EX) === false
        ) {
            // Fallback single file
            $singleFile = '/tmp/api_key_single_webchecker.txt';
            $data = base64_encode($apiKey . '|' . $expiryTime);
            if (file_put_contents($singleFile, $data, LOCK_EX) === false) {
                throw new Exception('All writes failed');
            }
            echo json_encode([
                'apiKey' => $apiKey,
                'expires_at' => date('Y-m-d H:i:s', $expiryTime),
                'status' => 'generated_fallback_single',
                'source' => 'single_tmp_file'
            ]);
        } else {
            echo json_encode([
                'apiKey' => $apiKey,
                'expires_at' => date('Y-m-d H:i:s', $expiryTime),
                'status' => 'generated',
                'source' => 'tmp_file'
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'disk_free' => disk_free_space('/'),
        'tmp_writable' => is_writable('/tmp') ? 'yes' : 'no'
    ]);
}
?>
