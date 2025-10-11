<?php
session_start();

// Load environment variables
require 'vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
try {
    $dbUrl = parse_url($_ENV['DATABASE_URL']);
    $pdo = new PDO(
        "pgsql:host={$dbUrl['host']};port={$dbUrl['port']};dbname=" . ltrim($dbUrl['path'], '/'),
        $dbUrl['user'],
        $dbUrl['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create users table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT UNIQUE,
            name VARCHAR(255),
            auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Telegram Bot Token
$telegramBotToken = $_ENV['TELEGRAM_BOT_TOKEN'];

// Function to verify Telegram data
function verifyTelegramData($data, $botToken) {
    $checkHash = $data['hash'];
    unset($data['hash']);
    ksort($data);
    $dataCheckString = '';
    foreach ($data as $key => $value) {
        $dataCheckString .= "$key=$value\n";
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
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    if ($curl_error) {
        error_log("Telegram API error: $curl_error");
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="YourBotName" data-size="large" data-onauth="onTelegramAuth(user)"></script>
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
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 10px solid rgba(255, 255, 255, 0.7);
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
            max-width: 360px;
            text-align: center;
        }
        .login-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .btn {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .btn-telegram {
            background: linear-gradient(45deg, #0088cc, #00bcd4);
            color: white;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-control:focus {
            outline: none;
            border-color: #f06292;
            box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .login-card {
                padding: 20px;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    <div class="login-card">
        <h2><i class="fas fa-lock"></i> 𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h2>
        <div class="form-group">
            <button onclick="showTelegramInput()" class="btn btn-telegram">
                <i class="fab fa-telegram-plane"></i> Sign up with Telegram
            </button>
        </div>
        <div class="form-group hidden" id="telegramInput">
            <input type="text" id="telegramPhone" class="form-control" placeholder="Enter Telegram phone number (e.g., +1234567890)">
            <button onclick="sendTelegramAuth()" class="btn btn-telegram mt-2">Send Auth Request</button>
        </div>
    </div>

    <script>
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = Math.random() * 10 + 10 + 's';
                particle.style.animationDelay = Math.random() * 5 + 's';
                particle.style.borderBottomColor = ['#0288d1', '#4fc3f7', '#f06292'][Math.floor(Math.random() * 3)];
                particlesContainer.appendChild(particle);
            }
        }
        createParticles();

        function showTelegramInput() {
            document.getElementById('telegramInput').classList.remove('hidden');
        }

        function sendTelegramAuth() {
            const phone = document.getElementById('telegramPhone').value.trim();
            if (!phone.match(/^\+\d{10,15}$/)) {
                Swal.fire({
                    title: 'Invalid Phone Number',
                    text: 'Please enter a valid phone number starting with +',
                    icon: 'error',
                    confirmButtonColor: '#f06292'
                });
                return;
            }

            Swal.fire({
                title: 'Telegram Authentication',
                text: 'Please check your Telegram app for an authentication request.',
                icon: 'info',
                confirmButtonColor: '#f06292'
            });

            // Telegram Login Widget will handle authentication
        }

        function onTelegramAuth(user) {
            const url = `login.php?telegram_auth=1&id=${user.id}&first_name=${encodeURIComponent(user.first_name)}&auth_date=${user.auth_date}&hash=${user.hash}`;
            window.location.href = url;
        }
    </script>
</body>
</html>
