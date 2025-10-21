<?php
// authnet1$.php - Authnet 1$ Gateway Processor

// Disable error reporting for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security check to prevent unauthorized access
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Validate security token
if (!isset($_POST['security_token']) || $_POST['security_token'] !== $_SESSION['security_token']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

// Validate domain
if (!isset($_POST['domain']) || $_POST['domain'] !== $_SERVER['HTTP_HOST']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Domain validation failed']);
    exit;
}

// Validate request ID (optional, for additional security)
if (!isset($_POST['request_id']) || empty($_POST['request_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Add rate limiting check
 $rate_limit_key = 'authnet_rate_limit_' . ($_SESSION['user']['id'] ?? 'unknown');
 $rate_limit_time = 60; // 60 seconds
 $rate_limit_max = 30; // 30 requests per minute

// Check if rate limit data exists
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = [
        'count' => 0,
        'reset_time' => time() + $rate_limit_time
    ];
}

// Check if rate limit reset time has passed
if (time() > $_SESSION[$rate_limit_key]['reset_time']) {
    $_SESSION[$rate_limit_key] = [
        'count' => 1,
        'reset_time' => time() + $rate_limit_time
    ];
} else {
    // Increment count
    $_SESSION[$rate_limit_key]['count']++;
    
    // Check if rate limit exceeded
    if ($_SESSION[$rate_limit_key]['count'] > $rate_limit_max) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
        exit;
    }
}

// Check if POST data is available
if (!isset($_POST['card']['number'], $_POST['card']['exp_month'], $_POST['card']['exp_year'], $_POST['card']['cvc'])) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card parameters']);
    exit;
}

// Extract card details
 $card_number = $_POST['card']['number'];
 $exp_month = str_pad($_POST['card']['exp_month'], 2, '0', STR_PAD_LEFT); // Ensure MM format
 $exp_year = $_POST['card']['exp_year'];
 $cvc = $_POST['card']['cvc'];

// Normalize exp_year to 4 digits if it's 2 digits (YY)
if (strlen($exp_year) === 2) {
    $exp_year = (intval($exp_year) < 50 ? '20' : '19') . $exp_year;
} elseif (strlen($exp_year) !== 4 || !is_numeric($exp_year)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid year format']);
    exit;
}

// Format cc string: number|month|year|cvc
 $cc = $card_number . '|' . $exp_month . '|' . $exp_year . '|' . $cvc;

// API URL
 $api_url_base = 'https://rockyalways.onrender.com/gateway=authnet1$/key=rockysoon?cc=';

// Function to make parallel API request
function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return $error ? false : $response;
}

// Create 3 parallel requests with slight variations (e.g., adding random delay to simulate different attempts)
 $responses = [];
 $multi_handle = curl_multi_init();

 $channels = [];
for ($i = 0; $i < 3; $i++) {
    $url = $api_url_base . urlencode($cc) . '&attempt=' . $i . '&rand=' . mt_rand();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_multi_add_handle($multi_handle, $ch);
    $channels[$i] = $ch;
}

 $active = null;
do {
    curl_multi_exec($multi_handle, $active);
    usleep(10000); // Small delay to prevent CPU overload
} while ($active > 0);

foreach ($channels as $i => $ch) {
    $response = curl_multi_getcontent($ch);
    if ($response !== false) {
        $responses[$i] = $response;
    }
    curl_multi_remove_handle($multi_handle, $ch);
}
curl_multi_close($multi_handle);

// Process responses
 $best_response = ['status' => 'DECLINED', 'message' => 'All attempts failed'];
foreach ($responses as $response) {
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['message'], $data['status'])) {
            $best_response = $data;
            break; // Take the first valid response
        }
    }
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid API response']);
    exit;
}

// Extract message and status
 $message = $best_response['message'] ?? 'Unknown error';
 $api_status = $best_response['status'] ?? 'UNKNOWN';

// Lowercase for case-insensitive comparison
 $message_lower = strtolower($message);

// Define phrase arrays
 $charged_phrases = [
    'transaction approved',
    'payment successful',
    'transaction complete',
    'approved',
    'success',
    'payment processed',
    'thank you for your payment'
];

 $approved_phrases = [
    'cvv',
    'card code',
    'security code',
    'cvv2',
    'cvc',
    'cvv does not match',
    'security code'
];

 $declined_phrases = [
    'failed',
    'declined',
    'transaction declined',
    'card declined',
    'insufficient funds',
    'invalid card',
    'expired card',
    'this transaction has been declined'
];

// Determine status
 $our_status = 'DECLINED';

// Check for CHARGED
foreach ($charged_phrases as $phrase) {
    if (strpos($message_lower, $phrase) !== false) {
        $our_status = 'CHARGED';
        break;
    }
}

// If not CHARGED, check for APPROVED (CVV related)
if ($our_status === 'DECLINED') {
    foreach ($approved_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $our_status = 'APPROVED';
            break;
        }
    }
}

// If still DECLINED, confirm with declined phrases or default
if ($our_status === 'DECLINED') {
    $is_declined = false;
    foreach ($declined_phrases as $phrase) {
        if (strpos($message_lower, $phrase) !== false) {
            $is_declined = true;
            break;
        }
    }
    if (!$is_declined && $api_status !== 'DECLINED') {
        $our_status = 'DECLINED';
    }
}

// Prepare output message
 $our_message = $message . ($api_status ? ' (' . $api_status . ')' : '');

// Output JSON response
echo json_encode(['status' => $our_status, 'message' => $our_message]);
?>
