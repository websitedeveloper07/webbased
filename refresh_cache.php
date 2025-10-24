<?php
// refresh_cache.php
// Simply returns the static API key

header('Content-Type: application/json');

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Optional authorization to prevent random requests
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'; // Your internal secret

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// === STATIC API KEY FROM validkey.php ===
require_once __DIR__ . '/gate/validkey.php';

echo json_encode([
    'apiKey' => STATIC_API_KEY,
    'status' => 'static'
]);
