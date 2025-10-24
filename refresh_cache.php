<?php
header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// Optional: simple authorization to prevent random requests
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['RESPONSE' => 'SAJAG MADRCHOD HAI']);
    exit;
}

// === STATIC API KEY ===
$STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK'; // Replace with your 128-char key

echo json_encode([
    'apiKey' => $STATIC_API_KEY,
    'status' => 'static'
]);

