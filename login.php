<?php
// -------------------------------
// SESSION & INITIAL CONFIG
// -------------------------------
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ob_start();
session_start();

// Default hardcoded credentials (will be overridden by .env if available)
$databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
// NOTE: This is the critical token. Verify it is 100% correct for CARDXCHK_LOGBOT
$telegramBotToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A'; 
$baseUrl = 'https://cardxchk.onrender.com';

// -------------------------------
// LOAD .ENV (optional)
// -------------------------------
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === 'DATABASE_URL') $databaseUrl = $value;
        if ($key === 'TELEGRAM_BOT_TOKEN') $telegramBotToken = $value;
        if ($key === 'BASE_URL') $baseUrl = $value;
    }
}

// -------------------------------
// DATABASE CONNECTION
// -------------------------------
try {
    $dbUrlString = str_replace('postgresql://', 'pgsql://', $databaseUrl);
    $dbUrl = parse_url($dbUrlString);
    if (!$dbUrl || !isset($dbUrl['host'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
        throw new Exception("Invalid DATABASE_URL format");
    }

    $host = $dbUrl['host'];
    $port = $dbUrl['port'] ?? 5432;
    $dbname = ltrim($dbUrl['path'], '/');
    $user = $dbUrl['user'];
    $pass = $dbUrl['pass'];

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        telegram_id BIGINT UNIQUE,
        name VARCHAR(255),
        auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// -------------------------------
// SECURITY TOKENS
// -------------------------------
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------
// HELPER FUNCTIONS
// -------------------------------
function verifyTelegramData($auth_data, $botToken) {
    if (!isset($auth_data['hash'])) {
        error_log("Verification failed: 'hash' parameter missing.");
        return false;
    }

    $check_hash = $auth_data['hash'];
    unset($auth_data['hash']);

    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);

    $secret_key = hash('sha256', $botToken, true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);

    // --- DEBUGGING OUTPUT (CHECK YOUR PHP ERROR LOGS) ---
    error_log("--- Telegram Auth Debug Start ---");
    error_log("Auth Bot Token (Partial): " . substr($botToken, 0, 10) . "...");
    error_log("Data Check String: " . $data_check_string);
    error_log("Expected Hash (Generated): " . $hash);
    error_log("Received Hash: " . $check_hash);
    error_log("Hash Comparison Result: " . (hash_equals($hash, $check_hash) ? "MATCH" : "MISMATCH"));
    error_log("--- Telegram Auth Debug End ---");
    // ---------------------------------------------------


    if (!hash_equals($hash, $check_hash)) {
        return false; // Hash mismatch (Token is most likely wrong or data was modified)
    }

    // Check expiration (24h)
    if (!isset($auth_data['auth_date']) || (time() - $auth_data['auth_date']) > 86400) {
        error_log("Verification failed: Authentication data expired or 'auth_date' missing. Current server time: " . time() . ", Auth date: " . ($auth_data['auth_date'] ?? 'N/A'));
        return false;
    }
    
    return true;
}

function checkTelegramAccess($telegramId, $botToken) {
    // This function checks if the user is a valid Telegram account, which is a good
    // check but usually redundant if the hash check passes.
    $url = "https://api.telegram.org/bot$botToken/getChat?chat_id=$telegramId";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("Telegram API curl error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    $result = json_decode($response, true);
    
    // Note: getChat usually works for any valid Telegram ID. 
    // If you intended to check if they are a member of a *group*, this function is incorrect.
    // It's kept here as per your original logic.
    return isset($result['ok']) && $result['ok'] === true;
}

// -------------------------------
// LOGOUT HANDLER
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    // Use the correct cookie path and security settings
    if (isset($_COOKIE['session_id'])) {
        setcookie('session_id', '', time() - 3600, '/', '', true, true);
    }
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

// -------------------------------
// TELEGRAM AUTH CALLBACK
// -------------------------------
if (isset($_GET['telegram_auth']) && isset($_GET['id']) && isset($_GET['hash'])) {
    // Sanitize and filter the GET array to only include known Telegram auth parameters
    $telegramDataKeys = ['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash'];
    $telegramData = array_intersect_key($_GET, array_flip($telegramDataKeys));

    if (verifyTelegramData($telegramData, $telegramBotToken)) {
        $telegramId = $telegramData['id'];
        $firstName = $telegramData['first_name'] ?? 'User';

        if (checkTelegramAccess($telegramId, $telegramBotToken)) {
            // Check or insert user
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
                $stmt->execute([$telegramId]);
                if ($stmt->rowCount() === 0) {
                    $insert = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                    $insert->execute([$telegramId, $firstName]);
                }
            } catch (PDOException $e) {
                error_log("Database error during user check/insert: " . $e->getMessage());
                die("An internal database error occurred.");
            }

            $_SESSION['user'] = [
                'telegram_id' => $telegramId,
                'name' => $firstName,
                'auth_provider' => 'telegram'
            ];

            // Regenerate session ID for security
            session_regenerate_id(true);

            $sessionId = bin2hex(random_bytes(16));
            $_SESSION['session_id'] = $sessionId;
            // Set cookie with security flags
            $cookie_secure = parse_url($baseUrl, PHP_URL_SCHEME) === 'https';
            setcookie('session_id', $sessionId, [
                'expires' => time() + 30 * 24 * 3600,
                'path' => '/',
                'domain' => '', // Set to empty string for current host only
                'secure' => $cookie_secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);


            // Redirect logic (JavaScript for iframe compatibility)
            $redirectUrl = $baseUrl . '/index.php';
            echo '<script>
                if (window.top !== window.self) {
                    window.top.location.href = "' . $redirectUrl . '";
                } else {
                    window.location.href = "' . $redirectUrl . '";
                }
            </script>';
            exit;
        } else {
            die("Telegram access invalid or revoked. Please try again.");
        }
    } else {
        die("Invalid Telegram authentication data. Please try again. (Check server logs for details)");
    }
}

// -------------------------------
// AUTO LOGIN CHECK
// -------------------------------
if (isset($_SESSION['user'])) {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in • Card X Chk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/assets/branding/cardxchk-mark.png">
    <style>
        :root { --glass: rgba(255,255,255,0.06); --stroke: rgba(255,255,255,0.12); }
        html, body { height:100%; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; }
        body {
            background:
                radial-gradient(1100px 700px at 8% -10%, rgba(255,135,135,0.20), transparent 60%),
                radial-gradient(900px 500px at 110% 110%, rgba(109,211,203,0.16), transparent 60%),
                linear-gradient(45deg, #f9ecec, #e0f6f5);
            color:#1a1a2e;
        }
        .glass { backdrop-filter: blur(14px); background: var(--glass); border:1px solid var(--stroke); }
        .card { box-shadow:0 25px 70px rgba(0,0,0,0.15), inset 0 0 0 1px rgba(255,255,255,0.03); }
    </style>
</head>
<body class="min-h-full">
<main class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-xl space-y-6">
        <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100/60 border border-gray-200/50 grid place-items-center shadow-lg">
                <img src="/assets/branding/cardxchk-mark.png" alt="Card X Chk" class="w-12 h-12 rounded-xl">
            </div>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-800">Card X Chk: Secure Sign-in</h1>
        </div>

        <div class="glass card rounded-3xl p-6">
            <div class="flex flex-col items-center gap-4">
                <span class="text-sm text-gray-600">Sign in with Telegram</span>
                <div class="w-full flex justify-center">
                    <script async
                        src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="CARDXCHK_LOGBOT"
                        data-size="large"
                        data-userpic="false"
                        data-request-access="write"
                        data-auth-url="https://cardxchk.onrender.com/login.php?telegram_auth=1">
                    </script>
                </div>
                <p class="text-[11px] text-gray-500 text-center">
                    Telegram OAuth is secure. We only verify your Telegram ID.
                </p>
            </div>
        </div>

        <div class="text-center text-xs text-gray-500">
            By continuing, you agree to our
            <a class="text-teal-500 hover:underline" href="/legal/terms">Terms of Service</a> and
            <a class="text-teal-500 hover:underline" href="/legal/privacy">Privacy Policy</a>.
        </div>
    </div>
</main>

<canvas id="particleCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;"></canvas>
<script>
const canvas = document.getElementById('particleCanvas');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;
let particles = [];
class Particle {
    constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 15 + 5;
        this.speedX = Math.random() * 1.5 - 0.75;
        this.speedY = Math.random() * 1.5 - 0.75;
        this.color = ['#ff8787', '#6dd3cb', '#6ab7d8'][Math.floor(Math.random()*3)];
        this.text = '𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲';
    }
    update() {
        this.x += this.speedX;
        this.y += this.speedY;
        if (this.x < 0 || this.x > canvas.width) this.speedX *= -0.8;
        if (this.y < 0 || this.y > canvas.height) this.speedY *= -0.8;
    }
    draw() {
        ctx.font = `${this.size}px Inter`;
        ctx.fillStyle = this.color;
        ctx.textAlign = 'center';
        ctx.fillText(this.text, this.x, this.y);
    }
}
for (let i=0;i<10;i++) particles.push(new Particle());
function animate(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    particles.forEach(p=>{p.update();p.draw();});
    requestAnimationFrame(animate);
}
animate();
window.addEventListener('resize',()=>{
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});
</script>
</body>
</html>
