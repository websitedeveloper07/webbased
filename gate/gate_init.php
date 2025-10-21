<?php
// gate_init.php - Blocks unauthorized access

// Your secret server key (store securely, not in public JS)
$SERVER_API_KEY = 'iloveyoupayal';

// Allow internal PHP includes
if (defined('ALLOWED_ACCESS')) {
    return;
}

// Allow HTTP requests only if they provide the correct API key
$headers = getallheaders();
if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== $SERVER_API_KEY) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied: Invalid or missing API key');
}

// If we reach here, the request is authorized
?>
