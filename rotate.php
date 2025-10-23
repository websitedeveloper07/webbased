<?php
// Updated rotate.php - Generates 128-char key valid for 1 hour
// Uses file storage for persistence across requests
// Files: api_key.txt (stores key), api_expiry.txt (stores expiry timestamp)

function generateApiKey() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < 128; $i++) {
        $apiKey .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

// File paths (adjust if needed for your server)
$keyFile = 'api_key.txt';
$expiryFile = 'api_expiry.txt';

// Current time
$currentTime = time();

// Check if files exist and key is still valid
$keyIsValid = false;
$storedKey = '';
$storedExpiry = 0;

if (file_exists($keyFile) && file_exists($expiryFile)) {
    $storedKey = trim(file_get_contents($keyFile));
    $storedExpiry = (int) trim(file_get_contents($expiryFile));
    
    if ($storedKey && $storedExpiry > $currentTime) {
        $keyIsValid = true;
    }
}

if ($keyIsValid) {
    // Return the existing key
    $apiKey = $storedKey;
    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $storedExpiry),
        'status' => 'reused'
    ]);
} else {
    // Generate new key
    $apiKey = generateApiKey();
    $expiryTime = $currentTime + 3600; // 1 hour from now
    
    // Save to files
    file_put_contents($keyFile, $apiKey);
    file_put_contents($expiryFile, $expiryTime);
    
    echo json_encode([
        'apiKey' => $apiKey,
        'expires_at' => date('Y-m-d H:i:s', $expiryTime),
        'status' => 'generated'
    ]);
}

// Set content type (moved to top for best practice, but kept here for compatibility)
header('Content-Type: application/json');
?>
