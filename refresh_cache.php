<?php
header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $errorMsg = ['error' => 'Method not allowed'];
    file_put_contents('refresh_cache.log', date('Y-m-d H:i:s') . ' Error 405: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Optional: simple authorization to prevent random requests
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    $errorMsg = ['error' => 'Unauthorized'];
    file_put_contents('refresh_cache.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Send request to validkey.php
$ch = curl_init('https://your-server/gate/validkey.php'); // Replace with your server URL
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['lund' => $SECRET_KEY]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log request details
file_put_contents('refresh_cache.log', date('Y-m-d H:i:s') . ' Request to validkey.php: ' . $response . ' (HTTP ' . $httpCode . ')' . PHP_EOL, FILE_APPEND);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Failed to validate key', 'validkey_response' => $response]);
    exit;
}

$responseData = json_decode($response, true);
if (!isset($responseData['apiKey'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from validkey.php', 'validkey_response' => $response]);
    exit;
}

// Use the API key (e.g., to refresh cache)
$apiKey = $responseData['apiKey'];
// Add cache refresh logic here (example placeholder)
$cacheRefreshed = true; // Replace with actual cache refresh code

echo json_encode([
    'status' => 'success',
    'apiKey' => $apiKey,
    'cache_refreshed' => $cacheRefreshed
]);
