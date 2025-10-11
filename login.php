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

    try {
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
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, telegram_id BIGINT UNIQUE, name VARCHAR(255), auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
    error_log("Users table ready");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage() . " | Host: $host | Port: $port | URL: $databaseUrl");
    die("Database connection failed. Please try again later.");
}

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
    return hash_equals($hash, $checkHash);
}

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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie('session_id', '', time() - 3600, '/', '', true, true);
    header('Location: login.php');
    exit;
}

if (isset($_GET['telegram_auth'])) {
    $telegramData = ['id' => $_GET['id'] ?? '', 'first_name' => $_GET['first_name'] ?? '', 'auth_date' => $_GET['auth_date'] ?? '', 'hash' => $_GET['hash'] ?? ''];
    error_log("Received Telegram OAuth data: " . json_encode($telegramData));
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
            $sessionId = bin2hex(random_bytes(16));
            setcookie('session_id', $sessionId, time() + 30 * 24 * 3600, '/', '', true, true);
            $_SESSION['session_id'] = $sessionId;
            error_log("Session set for user: telegram_id=$telegramId");
            header('Location: index.php');
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
                header('Location: index.php');
                exit;
            }
        }
    }
}

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #1a237e, #4a148c, #6a1b9a, #ab47bc);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        @keyframes gradientFlow {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        .particle {
            position: absolute;
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5), 0 0 20px rgba(171, 71, 188, 0.7);
            animation: floatDiagonal 12s infinite linear;
            white-space: nowrap;
        }
        @keyframes floatDiagonal {
            0% { transform: translate(-50vw, 100vh) rotate(-45deg); opacity: 0.8; }
            100% { transform: translate(150vw, -50vh) rotate(-45deg); opacity: 0; }
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2), 0 0 60px rgba(171, 71, 188, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            transform: scale(1);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(171, 71, 188, 0.5);
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: glowPulse 8s infinite;
            z-index: 0;
        }
        @keyframes glowPulse {
            0% { transform: scale(0.5); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.2; }
            100% { transform: scale(0.5); opacity: 0.5; }
        }
        .login-card:hover {
            transform: scale(1.05);
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.3), 0 0 80px rgba(171, 71, 188, 0.5);
        }
        .login-card h2 {
            font-size: 2.5rem;
            font-weight: 900;
            color: #1a237e;
            margin-bottom: 15px;
            text-shadow: 0 0 10px rgba(26, 35, 126, 0.7);
            position: relative;
            z-index: 1;
        }
        .login-card h2 i {
            margin-right: 10px;
            color: #ab47bc;
        }
        .login-card p {
            font-size: 1.1rem;
            color: #4a148c;
            margin-bottom: 25px;
            font-weight: 500;
            text-shadow: 0 0 5px rgba(74, 20, 140, 0.3);
            position: relative;
            z-index: 1;
        }
        @media (max-width: 768px) {
            .login-card {
                max-width: 90%;
                padding: 25px;
            }
            .login-card h2 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    <div class="login-card">
        <h2><i class="fas fa-credit-card"></i> 𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h2>
        <p>Unlock the power of card checking</p>
        <iframe src="https://oauth.telegram.org/embed/CARDXCHK_LOGBOT?origin=https%3A%2F%2Fcardxchk.onrender.com&return_to=https%3A%2F%2Fcardxchk.onrender.com%2Flogin.php&size=large" width="100%" height="50" frameborder="0" scrolling="no" style="border-radius: 12px; overflow: hidden;"></iframe>
    </div>
    <script>
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const diagonalCount = 10;
            for (let i = 0; i < diagonalCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.textContent = 'Card ✘ CHK';
                particle.style.left = `${i * (100 / (diagonalCount - 1))}%`;
                particle.style.top = `${i * (100 / (diagonalCount - 1))}%`;
                particle.style.animationDuration = `${12 + i * 2}s`;
                particle.style.animationDelay = `${i * 0.5}s`;
                particle.style.fontSize = `${1.5 - i * 0.1}rem`;
                particle.style.color = ['#ab47bc', '#6a1b9a', '#4a148c'][i % 3];
                particlesContainer.appendChild(particle);
            }
        }
        createParticles();
        document.addEventListener('DOMContentLoaded', () => {
            const telegramWidget = document.querySelector('iframe[src*="oauth.telegram.org"]');
            if (!telegramWidget) {
                console.error('Telegram OAuth iframe not loaded');
                error_log('Telegram OAuth iframe not loaded in DOM');
                Swal.fire({
                    title: 'Configuration Error',
                    text: 'Telegram OAuth iframe failed to load. Check network or URL.',
                    icon: 'error',
                    confirmButtonColor: '#ab47bc'
                });
            }
        });
    </script>
</body>
</html>
