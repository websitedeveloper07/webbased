<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Get the API key from the request headers
 $headers = getallheaders();
 $apiKey = isset($headers['X-API-KEY']) ? $headers['X-API-KEY'] : '';

// For demonstration, we'll just check if the key is 128 characters long
// In a real implementation, you would verify against a stored key or database
if (strlen($apiKey) === 128 && ctype_alnum($apiKey)) {
    // Key is valid
    echo json_encode(['valid' => true]);
} else {
    // Key is invalid
    echo json_encode(['valid' => false, 'error' => 'Invalid API key']);
}
?>
