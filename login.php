<?php
session_start();

// Hardcoded credentials
$databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
$telegramBotToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';

// Load .env fallback (optional)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        if (trim($key) === 'DATABASE_URL') $databaseUrl = trim($value);
        if (trim($key) === 'TELEGRAM_BOT_TOKEN') $telegramBotToken = trim($value);
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

    // Prioritize non-SSL (matches psql command), fallback to SSL
    try {
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$dbname",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        error_log("Database connected without SSL: host=$host, port=$port, dbname=$dbname");
    } catch (PDOException $e) {
        error_log("Non-SSL connection failed: " . $e->getMessage() . " | Attempting SSL");
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        error_log("Database connected with SSL: host=$host, port=$port, dbname=$dbname");
    }

    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT UNIQUE,
            name VARCHAR(255),
            auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    error_log("Users table ready");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage() . " | Host: $host | Port: $port | URL: $databaseUrl");
    die("Database connection failed. Please try again later.");
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify Telegram Bot Token
if (empty($telegramBotToken)) {
    error_log("TELEGRAM_BOT_TOKEN not set");
    die("Configuration error: Telegram bot token missing");
}

// Function to verify Telegram data
function verifyTelegramData($data, $botToken) {
    $checkHash = $data['hash'] ?? '';
    unset($data['hash']);
    ksort($data);
    $dataCheckString = '';
    foreach ($data as $key => $value) {
        if ($value !== '') {
            $dataCheckString .= "$key=$value\n";
        }
    }
    $dataCheckString = rtrim($dataCheckString, "\n");
    $secretKey = hash('sha256', $botToken, true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
    return hash_equals($hash, $checkHash);
}

// Function to check Telegram access revocation
function checkTelegramAccess($telegramId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/getChat?chat_id=$telegramId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curl_error) {
        error_log("Telegram API error: $curl_error (HTTP $http_code)");
        return false;
    }
    $result = json_decode($response, true);
    return isset($result['ok']) && $result['ok'] === true;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie('session_id', '', time() - 3600, '/', '', true, true);
    header('Location: login.php');
    exit;
}

// Handle Telegram callback
if (isset($_GET['telegram_auth'])) {
    $telegramData = [
        'id' => $_GET['id'] ?? '',
        'first_name' => $_GET['first_name'] ?? '',
        'auth_date' => $_GET['auth_date'] ?? '',
        'hash' => $_GET['hash'] ?? ''
    ];

    if (verifyTelegramData($telegramData, $telegramBotToken)) {
        $telegramId = $telegramData['id'];
        if (checkTelegramAccess($telegramId, $telegramBotToken)) {
            // Check if user exists or create new
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                $stmt->execute([$telegramId, $telegramData['first_name']]);
            }

            // Set session
            $_SESSION['user'] = [
                'telegram_id' => $telegramId,
                'name' => $telegramData['first_name'],
                'auth_provider' => 'telegram'
            ];
            $sessionId = bin2hex(random_bytes(16));
            setcookie('session_id', $sessionId, time() + 30 * 24 * 3600, '/', '', true, true);
            $_SESSION['session_id'] = $sessionId;

            header('Location: index.php');
            exit;
        } else {
            error_log("Telegram access revoked for ID: $telegramId");
            die("Telegram access revoked or invalid");
        }
    } else {
        error_log("Invalid Telegram authentication data");
        die("Invalid Telegram authentication data");
    }
}

// Check persistent session
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
                $_SESSION['user'] = [
                    'telegram_id' => $user['telegram_id'],
                    'name' => $user['name'],
                    'auth_provider' => $user['auth_provider']
                ];
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="@CARDXCHK_LOGBOT" data-size="large" data-onauth="onTelegramAuth(user)"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #0288d1, #4fc3f7, #f06292, #bbdefb);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        .particle {
            position: absolute;
            width: auto;
            height: auto;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.2rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            animation: floatDiagonal 15s infinite linear;
        }
        @keyframes floatDiagonal {
            0% {
                transform: translate(0, 100vh) rotate(45deg);
                opacity: 0.8;
            }
            100% {
                transform: translate(100vw, -100vh) rotate(45deg);
                opacity: 0;
            }
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .login-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
        }
        .login-card h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .login-card p {
            font-size: 1rem;
            color: #555;
            margin-bottom: 20px;
        }
        .btn-telegram {
            background: linear-gradient(45deg, #0088cc, #00bcd4);
            color: white;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-telegram:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 136, 204, 0.3);
        }
        .telegram-icon {
            font-size: 1.5rem;
        }
        .telegram-login-CARDXCHK_LOGBOT {
            display: none; /* Hide the default Telegram widget button */
        }
        @media (max-width: 768px) {
            .login-card {
                max-width: 90%;
                padding: 20px;
            }
            .login-card h2 {
                font-size: 1.7rem;
            }
            .btn-telegram {
                font-size: 1rem;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Particle Animation -->
    <div class="particles" id="particles"></div>

    <!-- Login Card -->
    <div class="login-card">
        <h2><i class="fas fa-credit-card"></i> 𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h2>
        <p>Sign in to start checking cards</p>
        <button class="btn-telegram" onclick="document.querySelector('.telegram-login-CARDXCHK_LOGBOT').click()">
            <i class="fab fa-telegram-plane telegram-icon"></i> Continue with Telegram
        </button>
        <div class="telegram-login-CARDXCHK_LOGBOT"></div> <!-- Telegram Widget Container -->
    </div>

    <script>
        // Particle Animation with "Card ✘ CHK" text
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const texts = ['Card ✘ CHK', 'Card CHK', '✘ CHK'];
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.textContent = texts[Math.floor(Math.random() * texts.length)];
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = Math.random() * 10 + 10 + 's';
                particle.style.animationDelay = Math.random() * 5 + 's';
                particle.style.color = ['#0288d1', '#4fc3f7', '#f06292'][Math.floor(Math.random() * 3)];
                particlesContainer.appendChild(particle);
            }
        }
        createParticles();

        // Telegram Authentication Callback
        function onTelegramAuth(user) {
            const url = `login.php?telegram_auth=1&id=${user.id}&first_name=${encodeURIComponent(user.first_name)}&auth_date=${user.auth_date}&hash=${user.hash}`;
            window.location.href = url;
        }

        // Error Handling for Telegram Widget
        document.addEventListener('DOMContentLoaded', () => {
            const telegramWidget = document.querySelector('.telegram-login-CARDXCHK_LOGBOT');
            if (!telegramWidget || !telegramWidget.querySelector('iframe')) {
                Swal.fire({
                    title: 'Configuration Error',
                    text: 'Telegram Login Widget not loaded. Please check the bot configuration.',
                    icon: 'error',
                    confirmButtonColor: '#f06292'
                });
            }
        });
    </script>
</body>
</html>
