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

// Authorization
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedKey = trim($input['lund'] ?? '');
$SECRET_KEY = getenv('SECRET_KEY') ?: 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR';

if ($providedKey !== $SECRET_KEY) {
    http_response_code(403);
    $errorMsg = ['error' => 'Unauthorized', 'provided_key' => $providedKey];
    file_put_contents('refresh_cache.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Send request to validkey.php
$apiUrl = 'https://your-server/gate/validkey.php'; // REPLACE WITH YOUR SERVER URL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['lund' => $SECRET_KEY]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log request details
$logMsg = date('Y-m-d H:i:s') . ' Request to validkey.php: URL=' . $apiUrl . ', Response=' . $response . ', HTTP=' . $httpCode;
if ($curlError) {
    $logMsg .= ', cURL Error=' . $curlError;
}
file_put_contents('refresh_cache.log', $logMsg . PHP_EOL, FILE_APPEND);

if ($httpCode !== 200) {
    http_response_code($httpCode ?: 500);
    echo json_encode(['error' => 'Failed to validate key', 'validkey_response' => $response, 'curl_error' => $curlError]);
    exit;
}

$responseData = json_decode($response, true);
if (!isset($responseData['apiKey']) || $responseData['status'] !== 'success') {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from validkey.php', 'validkey_response' => $response]);
    exit;
}

// Use the API key (e.g., to refresh cache)
$apiKey = $responseData['apiKey'];
$cacheRefreshed = true; // Replace with actual cache refresh code

echo json_encode([
    'status' => 'success',
    'apiKey' => $apiKey,
    'cache_refreshed' => $cacheRefreshed
]);
