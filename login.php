<?php
ob_start();
session_start();

// Set custom session save path to avoid Render default issues
session_save_path(__DIR__ . '/sessions');
if (!is_dir(session_save_path())) {
    mkdir(session_save_path(), 0777, true);
}

// Hardcoded credentials
$databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
$telegramBotToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';
$baseUrl = 'https://cardxchk.onrender.com';

// Load .env fallback (optional)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        if (trim($key) === 'DATABASE_URL') $databaseUrl = trim($value);
        if (trim($key) === 'TELEGRAM_BOT_TOKEN') $telegramBotToken = trim($value);
        if (trim($key) === 'BASE_URL') $baseUrl = trim($value);
    }
}

// Database connection
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
} catch (PDOException $e) {
    error_log("Non-SSL connection failed: " . $e->getMessage() . " | Attempting SSL");
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    error_log("Database connected with SSL: host=$host, port=$port, dbname=$dbname");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage() . " | Host: $host | Port: $port | URL: $databaseUrl");
    die("Database connection failed. Please try again later.");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, telegram_id BIGINT UNIQUE, name VARCHAR(255), auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
error_log("Users table ready");

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    setcookie('session_id', '', time() - 3600, '/', '', true, true);
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

if (isset($_GET['telegram_auth'])) {
    $telegramData = [
        'id' => $_GET['id'] ?? '',
        'first_name' => $_GET['first_name'] ?? '',
        'auth_date' => $_GET['auth_date'] ?? '',
        'hash' => $_GET['hash'] ?? ''
    ];
    error_log("Received Telegram OAuth data: " . json_encode($_GET)); // Debug full GET params
    if (verifyTelegramData($telegramData, $telegramBotToken)) {
        $telegramId = $telegramData['id'];
        if (checkTelegramAccess($telegramId, $telegramBotToken)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                $stmt->execute([$telegramId, $telegramData['first_name']]);
                error_log("New user created: telegram_id=$telegramId, name={$telegramData['first_name']}");
            } else {
                error_log("User found: telegram_id=$telegramId");
            }
            $_SESSION['user'] = ['telegram_id' => $telegramId, 'name' => $telegramData['first_name'], 'auth_provider' => 'telegram'];
            if (isset($_SESSION['user'])) {
                error_log("Session successfully set for user: telegram_id=$telegramId");
            } else {
                error_log("Failed to set session for user: telegram_id=$telegramId");
            }
            $sessionId = bin2hex(random_bytes(16));
            setcookie('session_id', $sessionId, time() + 30 * 24 * 3600, '/', '', true, true);
            $_SESSION['session_id'] = $sessionId;
            error_log("Attempting redirect to index.php for user: telegram_id=$telegramId");
            header('Location: ' . $baseUrl . '/index.php'); // Full URL redirect
            echo '<script>window.location.href = "' . $baseUrl . '/index.php";</script>'; // JS fallback
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

if (isset($_COOKIE['session_id']) && !isset($_SESSION['user'])) {
    if ($_COOKIE['session_id'] === ($_SESSION['session_id'] ?? '')) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$_SESSION['user']['telegram_id'] ?? '']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($user['auth_provider'] === 'telegram' && !checkTelegramAccess($user['telegram_id'], $telegramBotToken)) {
                error_log("Session invalidated: Telegram access revoked for ID: {$user['telegram_id']}");
                session_unset();
                setcookie('session_id', '', time() - 3600, '/', '', true, true);
            } else {
                $_SESSION['user'] = ['telegram_id' => $user['telegram_id'], 'name' => $user['name'], 'auth_provider' => $user['auth_provider']];
                header('Location: ' . $baseUrl . '/index.php');
                exit;
            }
        }
    }
}

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
    <main class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-xl space-y-6">
            <div class="flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100/60 border border-gray-200/50 grid place-items-center shadow-lg">
                    <img src="/assets/branding/cardxchk-mark.png" alt="Card X Chk" class="w-12 h-12 rounded-xl" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
                </div>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-800">Card X Chk: Secure Sign-in</h1>
            </div>

            <div class="glass card rounded-3xl p-6">
                <div class="flex flex-col items-center gap-4">
                    <span class="text-sm text-gray-600">Sign in with Telegram</span>

                    <div class="w-full flex justify-center">
                        <div class="telegram-login-CARDXCHK_LOGBOT"></div>
                        <script async src="https://telegram.org/js/telegram-widget.js?22"
                                data-telegram-login="CARDXCHK_LOGBOT"
                                data-size="large"
                                data-auth-url="/login.php"
                                data-request-access="write"
                                onload="console.log('Telegram widget loaded')"
                                onerror="console.error('Telegram widget failed to load')"></script>
                    </div>

                    <p class="text-[11px] text-gray-500 text-center">
                        Telegram OAuth is secure. We do not get access to your account.
                    </p>
                </div>
            </div>

            <div class="text-center text-xs text-gray-500">
                By continuing, you agree to our
                <a class="text-teal-500 hover:underline" href="/legal/terms">Terms of Service</a> and
                <a class="text-teal-500 hover:underline" href="/legal/privacy">Privacy Policy</a>.
            </div>
            <div class="flex items-center justify-center gap-2 text-xs text-gray-500">
                <span>Powered by</span>
            </div>
        </div>
    </main>
    <canvas id="particleCanvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></canvas>
    <script>
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

        document.addEventListener('DOMContentLoaded', () => {
            const telegramWidget = document.querySelector('.telegram-login-CARDXCHK_LOGBOT');
            if (!telegramWidget || !telegramWidget.querySelector('iframe')) {
                console.error('Telegram widget not loaded');
                error_log('Telegram widget not loaded in DOM');
                Swal.fire({
                    title: 'Configuration Error',
                    text: 'Telegram Login Widget failed to load. Check bot settings, network, or Render CSP settings.',
                    icon: 'error',
                    confirmButtonColor: '#6ab7d8'
                });
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        });
    </script>
</body>
</html>
