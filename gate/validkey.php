<?php
header('Content-Type: application/json');

// Restrict to POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse input (try JSON first, then fall back to POST)
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST; // Fallback to form data
}

// Check if key exists
$providedKey = trim($input['lund'] ?? '');
if (empty($providedKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or empty key']);
    exit;
}

// Authorization
$SECRET_KEY = getenv('SECRET_KEY') ?: 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';
if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

// Static API key (ideally, fetch from a secure source)
$STATIC_API_KEY = getenv('STATIC_API_KEY') ?: 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

echo json_encode([
    'apiKey' => $STATIC_API_KEY,
    'status' => 'success'
]);
