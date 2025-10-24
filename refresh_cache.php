<?php
// refresh_cache.php
// Simply returns the hardcoded API key

header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Optional internal secret to prevent random access
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedSecret = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'; // Internal secret for refresh

if ($providedSecret !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// === HARDCODED API KEY SENT TO CLIENT ===
$STATIC_API_KEY = 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK';

echo json_encode([
    'apiKey' => $HARDCODED_API_KEY,
    'status' => 'static'
]);
