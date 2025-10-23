<?php
// Generate a 128-character alphanumeric API key
function generateApiKey() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < 128; $i++) {
        $apiKey .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

// Set the content type to JSON
header('Content-Type: application/json');

// Generate the API key
 $apiKey = generateApiKey();

// Return the API key as JSON
echo json_encode(['apiKey' => $apiKey]);
?>
