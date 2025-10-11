<?php
ob_start();
session_start();

// Configuration
$CONFIG = [
    'DOMAIN' => $_ENV['DOMAIN'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'APP_NAME' => 'Card X Chk',
    'BOT_USERNAME' => 'CARDXCHK_LOGBOT'
];

// Set CSP header to allow Telegram widget and dependencies
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://telegram.org https://cdn.jsdelivr.net; frame-src https://oauth.telegram.org; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; connect-src 'self' https://api.telegram.org; img-src 'self' data: https:;");

// Database and Telegram Bot configuration (prefer environment variables)
$databaseUrl = $_ENV['DATABASE_URL'] ?? 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
$telegramBotToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';

// Load .env fallback
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        if (trim($key) === 'DATABASE_URL') $databaseUrl = trim($value);
        if (trim($key) === 'TELEGRAM_BOT_TOKEN') $telegramBotToken = trim($value);
        if (trim($key) === 'DOMAIN') $CONFIG['DOMAIN'] = trim($value);
    }
}

// Database connection (non-fatal)
try {
    $dbUrlString = str_replace('postgresql://', 'pgsql://', $databaseUrl);
    $dbUrl = parse_url($dbUrlString);
    if (!$dbUrl || !isset($dbUrl['host'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
        throw new Exception("Invalid DATABASE_URL format: " . $databaseUrl);
    }
    $host = $dbUrl['host'];
    $port = $dbUrl['port'] ?? 5432;
    $dbname = ltrim($dbUrl['path'], '/');
    $user = $dbUrl['user'];
    $pass = $dbUrl['pass'];

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    error_log("Database connected without SSL: host=$host, port=$port, dbname=$dbname");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, telegram_id BIGINT UNIQUE, name VARCHAR(255), auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
    error_log("Users table ready");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage() . " | Host: $host | Port: $port | URL: $databaseUrl");
    // Continue without DB
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("CSRF token generated: " . $_SESSION['csrf_token']);
}

if (empty($telegramBotToken)) {
    error_log("TELEGRAM_BOT_TOKEN not set");
    die("Configuration error: Telegram bot token missing");
}

function verifyTelegramData($data, $botToken) {
    $checkHash = $data['hash'] ?? '';
    unset($data['hash']);
    ksort($data);
    $dataCheckString = '';
    foreach ($data as $key => $value) {
        if ($value !== '') $dataCheckString .= "$key=$value\n";
    }
    $dataCheckString = rtrim($dataCheckString, "\n");
    $secretKey = hash('sha256', $botToken, true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
    $result = hash_equals($hash, $checkHash);
    $authDate = (int)($data['auth_date'] ?? 0);
    if ($result && (time() - $authDate > 86400)) {
        error_log("Auth date too old: $authDate");
        $result = false;
    }
    error_log("Telegram data verification: " . ($result ? "Success" : "Failed") . " | Data: " . json_encode($data) . " | Calculated Hash: $hash | Provided Hash: $checkHash");
    return $result;
}

function checkTelegramAccess($telegramId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/getChat?chat_id=$telegramId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curl_error) {
        error_log("Telegram API error: $curl_error (HTTP $http_code) for chat_id $telegramId");
        return false;
    }
    $result = json_decode($response, true);
    $success = isset($result['ok']) && $result['ok'] === true;
    error_log("Telegram access check: " . ($success ? "Success" : "Failed") . " | Response: " . json_encode($result));
    return $success;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: {$CONFIG['DOMAIN']}/login.php");
    ob_end_flush();
    exit;
}

// Handle Telegram OAuth callback
if (isset($_GET['id']) && isset($_GET['hash'])) {
    error_log("Received Telegram OAuth data: " . json_encode($_GET));
    $telegramData = [
        'id' => $_GET['id'] ?? '',
        'first_name' => $_GET['first_name'] ?? '',
        'auth_date' => $_GET['auth_date'] ?? '',
        'hash' => $_GET['hash'] ?? ''
    ];
    if (verifyTelegramData($telegramData, $telegramBotToken)) {
        $telegramId = $telegramData['id'];
        if (checkTelegramAccess($telegramId, $telegramBotToken)) {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
                $stmt->execute([$telegramId]);
                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                    $stmt->execute([$telegramId, $telegramData['first_name']]);
                    error_log("New user created: telegram_id=$telegramId, name={$telegramData['first_name']}");
                } else {
                    error_log("User found: telegram_id=$telegramId");
                }
            }
            $_SESSION['user'] = ['telegram_id' => $telegramId, 'name' => $telegramData['first_name'], 'auth_provider' => 'telegram'];
            error_log("Session set for user: " . json_encode($_SESSION['user']));
            header("Location: {$CONFIG['DOMAIN']}/index.php");
            ob_end_flush();
            exit;
        } else {
            error_log("Telegram access revoked for ID: $telegramId");
            die("Telegram access revoked or invalid. Please re-authenticate.");
        }
    } else {
        error_log("Invalid Telegram OAuth data: " . json_encode($telegramData));
        die("Invalid Telegram authentication data. Please try again.");
    }
}

// Redirect authenticated users
if (isset($_SESSION['user'])) {
    error_log("Session exists, redirecting to index.php: " . json_encode($_SESSION['user']));
    header("Location: {$CONFIG['DOMAIN']}/index.php");
    ob_end_flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in • <?php echo htmlspecialchars($CONFIG['APP_NAME']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/assets/branding/cardxchk-mark.png" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
    <style>
        :root {
            --glass: rgba(255, 255, 255, 0.06);
            --stroke: rgba(255, 255, 255, 0.12);
        }
        html, body {
            height: 100%;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial;
        }
        body {
            background:
                radial-gradient(1100px 700px at 8% -10%, rgba(255, 135, 135, 0.20), transparent 60%),
                radial-gradient(900px 500px at 110% 110%, rgba(109, 211, 203, 0.16), transparent 60%),
                linear-gradient(45deg, #f9ecec, #e0f6f5);
            color: #1a1a2e;
        }
        .glass {
            backdrop-filter: blur(14px);
            background: var(--glass);
            border: 1px solid var(--stroke);
        }
        .card {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15), inset 0 0 0 1px rgba(255, 255, 255, 0.03);
        }
    </style>
</head>
<body class="min-h-full">
    <main class="min-h-screen flex flex-col items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md sm:max-w-lg space-y-8">
            <!-- Header Section -->
            <div class="flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100/60 border border-gray-200/50 grid place-items-center shadow-lg">
                    <img src="/assets/branding/cardxchk-mark.png" alt="<?php echo htmlspecialchars($CONFIG['APP_NAME']); ?>" class="w-12 h-12 rounded-xl" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
                </div>
                <h1 class="mt-4 text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-800"><?php echo htmlspecialchars($CONFIG['APP_NAME']); ?>: Secure Sign-in</h1>
                <p class="mt-2 text-sm text-gray-600">Sign in securely using your Telegram account</p>
            </div>

            <!-- Login Card -->
            <div class="glass card rounded-3xl p-6 sm:p-8">
                <div class="flex flex-col items-center gap-6">
                    <span class="text-sm font-medium text-gray-600">Sign in with Telegram</span>
                    <div class="w-full flex justify-center">
                        <div class="telegram-login-<?php echo htmlspecialchars($CONFIG['BOT_USERNAME']); ?>"></div>
                        <script async src="https://telegram.org/js/telegram-widget.js?22"
                                data-telegram-login="<?php echo htmlspecialchars($CONFIG['BOT_USERNAME']); ?>"
                                data-size="large"
                                data-auth-url="<?php echo htmlspecialchars($CONFIG['DOMAIN']); ?>/login.php"
                                data-request-access="write"
                                onload="console.log('Telegram widget script loaded'); document.querySelector('.telegram-login-<?php echo htmlspecialchars($CONFIG['BOT_USERNAME']); ?>').dataset.loaded = 'true';"
                                onerror="console.error('Failed to load Telegram widget script'); Swal.fire({title: 'Widget Load Error', text: 'Telegram widget script failed to load. Check network or bot settings.', icon: 'error', confirmButtonColor: '#6ab7d8'});"></script>
                    </div>
                    <p class="text-xs text-gray-500 text-center max-w-xs">
                        Telegram OAuth is secure. We do not access your Telegram account or personal data.
                    </p>
                </div>
            </div>

            <!-- Footer Links -->
            <div class="text-center text-xs text-gray-500 space-y-2">
                <p>By continuing, you agree to our</p>
                <div class="flex justify-center gap-2">
                    <a class="text-teal-500 hover:underline" href="/legal/terms">Terms of Service</a>
                    <span>•</span>
                    <a class="text-teal-500 hover:underline" href="/legal/privacy">Privacy Policy</a>
                </div>
                <p>Powered by <span class="font-medium"><?php echo htmlspecialchars($CONFIG['APP_NAME']); ?></span></p>
            </div>
        </div>
    </main>
    <canvas id="particleCanvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></canvas>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('particleCanvas');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            let particles = [];
            const particleCount = 10;

            class Particle {
                constructor() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 15 + 5;
                    this.speedX = Math.random() * 1.5 - 0.75;
                    this.speedY = Math.random() * 1.5 - 0.75;
                    this.color = ['#ff8787', '#6dd3cb', '#6ab7d8'][Math.floor(Math.random() * 3)];
                    this.text = '𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲';
                }
                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;
                    if (this.x < 0 || this.x > canvas.width) this.speedX = -this.speedX * 0.8;
                    if (this.y < 0 || this.y > canvas.height) this.speedY = -this.speedY * 0.8;
                }
                draw() {
                    ctx.font = `${this.size}px Inter`;
                    ctx.fillStyle = this.color;
                    ctx.textAlign = 'center';
                    ctx.fillText(this.text, this.x, this.y);
                }
            }

            function init() {
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle());
                }
            }

            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let i = 0; i < particles.length; i++) {
                    particles[i].update();
                    particles[i].draw();
                }
                requestAnimationFrame(animate);
            }

            init();
            animate();

            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            });

            // Enhanced widget debugging
            setTimeout(() => {
                const telegramWidget = document.querySelector('.telegram-login-<?php echo htmlspecialchars($CONFIG['BOT_USERNAME']); ?>');
                if (!telegramWidget || !telegramWidget.querySelector('iframe') || telegramWidget.dataset.loaded !== 'true') {
                    console.error('Telegram widget failed to initialize - check CSP, domain, or bot settings');
                    console.log('Widget element:', telegramWidget);
                    Swal.fire({
                        title: 'Widget Load Error',
                        html: 'Telegram Login Widget failed to initialize. Ensure:<br>1. Domain is set in @BotFather (<code><?php echo htmlspecialchars($CONFIG['DOMAIN']); ?></code>).<br>2. CSP allows oauth.telegram.org.<br>3. Bot username is correct (@<?php echo htmlspecialchars($CONFIG['BOT_USERNAME']); ?>).<br>4. No ad blockers or browser restrictions.',
                        icon: 'error',
                        confirmButtonColor: '#6ab7d8'
                    });
                } else {
                    console.log('Telegram widget fully loaded with iframe');
                }
            }, 3000);
        });
    </script>
</body>
</html>
