<?php
session_start();
$ALLOWED_DOMAIN = 'https://cxchk.site';
$ALLOWED_ENDPOINTS = [
    'stripeauth',
    'paypal0.1$',
    'stripe1$',
    'shopify1$',
    'authnet1$',
    'razorpay0.10$'
];

// Validate Referer
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!str_starts_with($referer, $ALLOWED_DOMAIN)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid referer']);
    exit;
}

// Get target endpoint from query parameter
$endpoint = $_GET['endpoint'] ?? '';
if (!in_array($endpoint, $ALLOWED_ENDPOINTS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint']);
    exit;
}

// Generate or retrieve API key for the session
if (!isset($_SESSION['api_key'])) {
    $_SESSION['api_key'] = bin2hex(random_bytes(16)); // 32-char random key
}
$api_key = $_SESSION['api_key'];

// Proxy request to the target endpoint
$data = file_get_contents('php://input');
$target_url = 'file://' . __DIR__ . '/' . $endpoint . '.php'; // Internal file path
$ch = curl_init($target_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Forward response to client
http_response_code($http_code);
header('Content-Type: application/json');
echo $response;
?>
