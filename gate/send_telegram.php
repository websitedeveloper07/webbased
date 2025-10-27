<?php
// send_telegram.php

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// Check if the request is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get the API key from headers
 $headers = getallheaders();
 $apiKey = isset($headers['X-Api-Key']) ? $headers['X-Api-Key'] : '';

// Validate API key (you should implement proper validation)
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'Missing API key']);
    exit;
}

// Get JSON data
 $jsonData = file_get_contents('php://input');
 $data = json_decode($jsonData, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Extract parameters
 $chatId = isset($data['chat_id']) ? $data['chat_id'] : '';
 $text = isset($data['text']) ? $data['text'] : '';
 $parseMode = isset($data['parse_mode']) ? $data['parse_mode'] : 'MarkdownV2';
 $replyMarkup = isset($data['reply_markup']) ? $data['reply_markup'] : '';

// Validate required parameters
if (empty($chatId) || empty($text)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Your bot token (already provided)
 $botToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';

// Your chat ID (already provided)
 $targetChatId = '-1003204998888';

// Override chat ID with the one provided in the request if it's different
if ($chatId !== $targetChatId) {
    // For security, you might want to log this or restrict to only your chat ID
    // For now, we'll use the provided chat ID
    $finalChatId = $chatId;
} else {
    $finalChatId = $targetChatId;
}

// Prepare the API request
 $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

 $postFields = [
    'chat_id' => $finalChatId,
    'text' => $text,
    'parse_mode' => $parseMode,
    'disable_web_page_preview' => true
];

if (!empty($replyMarkup)) {
    $postFields['reply_markup'] = $replyMarkup;
}

// Send the request
 $ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 $curlError = curl_error($ch);
curl_close($ch);

// Log the response for debugging
 $logMessage = date('Y-m-d H:i:s') . " - Telegram API Response: " . $response . "\n";
file_put_contents('telegram_log.txt', $logMessage, FILE_APPEND);

// Check for errors
if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'CURL error: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'HTTP error: ' . $httpCode]);
    exit;
}

// Return the response
echo $response;
?>
