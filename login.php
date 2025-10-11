<?php
// -------------------------------
// SESSION & INITIAL CONFIG
// -------------------------------
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log'); // Render: also check platform logs
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ob_start();
session_start();

// -------------------------------
// CONFIG
// -------------------------------
$databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
$telegramBotToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A'; // ensure exact token
$baseUrl = 'https://cardxchk.onrender.com';
$debugMode = (isset($_GET['dbg']) && $_GET['dbg'] == '1') ? true : false;

// -------------------------------
// LOAD .env (optional)
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
// DATABASE CONNECTION (kept same)
// -------------------------------
try {
    $dbUrlString = str_replace('postgresql://', 'pgsql://', $databaseUrl);
    $dbUrl = parse_url($dbUrlString);
    if ($dbUrl && isset($dbUrl['host'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
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
    } else {
        // If DATABASE_URL malformed, skip DB but continue for testing.
        error_log("[login.php] DATABASE_URL malformed or missing; skipping DB creation.");
        $pdo = null;
    }
} catch (Exception $e) {
    error_log("[login.php] Database connection failed: " . $e->getMessage());
    // Do not reveal DB errors to user
}

// -------------------------------
// SECURITY TOKENS
// -------------------------------
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------
// VERIFY (instrumented) - Telegram Login Widget (official method)
// -------------------------------
function verifyTelegramWidgetData(array $auth_data, string $botToken, bool $log = false) : bool {
    if (!isset($auth_data['hash'])) {
        if ($log) error_log("[verify] Missing hash in auth_data: " . json_encode($auth_data));
        return false;
    }

    $provided_hash = $auth_data['hash'];
    unset($auth_data['hash']);

    // Build array of "key=value" strings (use raw values as received)
    $pairArr = [];
    foreach ($auth_data as $k => $v) {
        // trim values to avoid accidental whitespace problems
        $pairArr[] = $k . '=' . trim((string)$v);
    }

    // Sort lexicographically as required by Telegram docs
    sort($pairArr, SORT_STRING);

    // Join with newline
    $data_check_string = implode("\n", $pairArr);

    // Secret key is SHA256(bot_token) in raw binary
    $secret_key = hash('sha256', $botToken, true);

    // Compute HMAC-SHA256 hex string
    $computed_hash = hash_hmac('sha256', $data_check_string, $secret_key);

    if ($log) {
        // Log the important pieces (no secrets). secret_key (binary) is not logged.
        error_log("[verify] incoming params: " . json_encode($auth_data));
        error_log("[verify] data_check_string: " . $data_check_string);
        error_log("[verify] provided_hash: " . $provided_hash);
        error_log("[verify] computed_hash: " . $computed_hash);
        // Also log server time vs auth_date if present
        if (isset($auth_data['auth_date'])) {
            error_log("[verify] auth_date: " . $auth_data['auth_date'] . " | now: " . time() . " | age_sec: " . (time() - (int)$auth_data['auth_date']));
        }
    }

    // constant-time compare (both should be lowercase hex)
    $ok = hash_equals($computed_hash, $provided_hash);

    // expiry check if supplied
    if ($ok && isset($auth_data['auth_date'])) {
        if ((time() - (int)$auth_data['auth_date']) > 86400) {
            if ($log) error_log("[verify] AUTH_DATE expired");
            return false;
        }
    }

    return $ok;
}

// -------------------------------
// checkTelegramAccess helper (unchanged)
// -------------------------------
function checkTelegramAccess($telegramId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/getChat?chat_id=$telegramId";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlErr) {
        error_log("[checkTelegramAccess] CURL error: $curlErr (HTTP $httpCode)");
        return false;
    }
    $result = json_decode($response, true);
    if ($result === null) {
        error_log("[checkTelegramAccess] Invalid JSON response: " . substr($response, 0, 500));
        return false;
    }
    return isset($result['ok']) && $result['ok'] === true;
}

// -------------------------------
// LOGOUT HANDLER
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie('session_id', '', time() - 3600, '/', '', true, true);
    setcookie('tg_user', '', time() - 3600, '/', '', true, true);
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

// -------------------------------
// TELEGRAM AUTH CALLBACK (instrumented)
// -------------------------------
if ((isset($_GET['telegram_auth']) && isset($_GET['id']) && isset($_GET['hash'])) || (isset($_GET['id']) && isset($_GET['hash']))) {
    // Accept both /login.php?telegram_auth=1... and /login.php?id=... (widget may call without extra param)
    $incoming = $_GET;

    // If auth params are URL-encoded in cookie or different, this captures raw array.
    $logNow = true; // change to false after debugging
    if ($logNow) error_log("[login.php] Received Telegram callback: " . json_encode($incoming));

    $ok = verifyTelegramWidgetData($incoming, $telegramBotToken, $logNow);

    if (! $ok) {
        // Helpful debug message in browser (non-secret) and log telling user what to check.
        error_log("[login.php] Telegram verification FAILED. See previous logs for data_check_string/computed_hash/provided_hash.");
        // If debugMode requested, show basic debug page (do not reveal secret or computed secret)
        if ($debugMode) {
            echo "<h2>Telegram verification FAILED</h2>";
            echo "<p>Check server logs (php-error.log) for detailed data_check_string, computed_hash and provided_hash.</p>";
            echo "<p>Common causes:</p><ul>
                    <li>Incorrect bot token configured (must match your bot token exactly).</li>
                    <li>Server time skew — ensure server clock is correct (NTP).</li>
                    <li>Missing or altered GET parameters (proxy or rewrites).</li>
                    <li>Using the WebApp verification method by mistake (this file uses Login Widget method).</li>
                  </ul>";
            exit;
        }
        die("Invalid Telegram authentication data. Please try again.");
    }

    // Verified — create session + optional DB entry
    $telegramId = $incoming['id'];
    $firstName = $incoming['first_name'] ?? 'User';

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            if ($stmt->rowCount() === 0) {
                $insert = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                $insert->execute([$telegramId, $firstName]);
            }
        } catch (Exception $e) {
            error_log("[login.php] DB error when inserting user: " . $e->getMessage());
        }
    }

    $_SESSION['user'] = [
        'telegram_id' => $telegramId,
        'name' => $firstName,
        'auth_provider' => 'telegram'
    ];

    $sessionId = bin2hex(random_bytes(16));
    $_SESSION['session_id'] = $sessionId;
    setcookie('session_id', $sessionId, time() + 30 * 24 * 3600, '/', '', true, true);

    // Also set a lightweight cookie for client-side examples (no secrets)
    setcookie('tg_user', json_encode([
        'id' => $telegramId,
        'first_name' => $firstName,
        'username' => $incoming['username'] ?? null,
        'photo_url' => $incoming['photo_url'] ?? null
    ]), time() + 86400, '/', '', true, true);

    // Redirect safely (handles iframe)
    echo '<script>
            if (window.top !== window.self) {
                window.top.location.href = "' . $baseUrl . '/index.php";
            } else {
                window.location.href = "' . $baseUrl . '/index.php";
            }
          </script>';
    exit;
}

// -------------------------------
// AUTO LOGIN CHECK
// -------------------------------
if (isset($_SESSION['user'])) {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// -------------------------------
// If user visited with ?dbg=1 show hint to check logs
// -------------------------------
if ($debugMode) {
    // Show non-sensitive guidance for debugging
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Login debug</title></head><body style='font-family:Inter,Arial,sans-serif;padding:30px;'>";
    echo "<h2>Telegram Login Debug Mode</h2>";
    echo "<p>Server logs are being written to <code>php-error.log</code> and to platform logs. Steps to debug:</p>";
    echo "<ol>
            <li>Click the Telegram login button below.</li>
            <li>After returning, open Render/host logs and inspect entries starting with <code>[verify]</code> and <code>[login.php]</code>.</li>
            <li>Compare <em>data_check_string</em>, <em>computed_hash</em>, and <em>provided_hash</em>.</li>
          </ol>";
    echo "<p>Do NOT expose or paste the bot token anywhere publicly.</p>";
    echo "<p><a href='/login.php'>Return to normal login (no dbg)</a></p>";
    echo "</body></html>";
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
                    <!-- NOTE: do NOT include comments inside the tag attributes -->
                    <script async
                        src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="CARDXCHK_LOGBOT"
                        data-size="large"
                        data-userpic="false"
                        data-request-access="write"
                        data-auth-url="https://cardxchk.onrender.com/login.php">
                    </script>
                </div>
                <p class="text-[11px] text-gray-500 text-center">
                    Telegram OAuth is secure. We do not access your Telegram data.
                </p>
                <p class="text-xs text-gray-500">Need debugging? <a href="/login.php?dbg=1" class="text-teal-500">Open debug mode</a> (temporary)</p>
            </div>
        </div>

        <div class="text-center text-xs text-gray-500">
            By continuing, you agree to our
            <a class="text-teal-500 hover:underline" href="/legal/terms">Terms of Service</a> and
            <a class="text-teal-500 hover:underline" href="/legal/privacy">Privacy Policy</a>.
        </div>
    </div>
</main>
<!-- particle canvas (unchanged) -->
<canvas id="particleCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;"></canvas>
<script>
const canvas = document.getElementById('particleCanvas');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;
let particles = [];
class Particle { constructor(){ this.x=Math.random()*canvas.width; this.y=Math.random()*canvas.height; this.size=Math.random()*15+5; this.speedX=Math.random()*1.5-0.75; this.speedY=Math.random()*1.5-0.75; this.color=['#ff8787','#6dd3cb','#6ab7d8'][Math.floor(Math.random()*3)]; this.text='𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲'; } update(){ this.x+=this.speedX; this.y+=this.speedY; if(this.x<0||this.x>canvas.width) this.speedX*=-0.8; if(this.y<0||this.y>canvas.height) this.speedY*=-0.8; } draw(){ ctx.font=`${this.size}px Inter`; ctx.fillStyle=this.color; ctx.textAlign='center'; ctx.fillText(this.text,this.x,this.y); } }
for(let i=0;i<10;i++) particles.push(new Particle());
function animate(){ ctx.clearRect(0,0,canvas.width,canvas.height); particles.forEach(p=>{p.update();p.draw();}); requestAnimationFrame(animate); }
animate();
window.addEventListener('resize',()=>{ canvas.width=window.innerWidth; canvas.height=window.innerHeight; });
</script>
</body>
</html>
