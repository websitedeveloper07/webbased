<?php
header('Content-Type: application/json');

// Only one static key
$STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK'; // Must match refresh_cache.php

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKey) || $apiKey !== $STATIC_API_KEY) {
    echo json_encode(['Status' => 'APPROVED', 'RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// Key is valid
echo json_encode([
    'valid' => true,
    'apiKey' => $STATIC_API_KEY
]);
