<?php
// -------------------------------
// SESSION & INITIAL CONFIG
// -------------------------------
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
session_start();

// -------------------------------
// CONFIGURATION
// -------------------------------
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';
 $telegramBotToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';
 $telegramBotUsername = 'CardXchk_LOGBOT';
 $baseUrl = 'http://cxchk.site';

// -------------------------------
// DATABASE CONNECTION
// -------------------------------
try {
    $dbUrl = parse_url($databaseUrl);
    if (!$dbUrl) throw new Exception("Invalid DATABASE_URL format");

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

    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        telegram_id BIGINT UNIQUE,
        name VARCHAR(255),
        auth_provider VARCHAR(20) NOT NULL CHECK (auth_provider = 'telegram'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// -------------------------------
// TELEGRAM AUTH HELPER
// -------------------------------
function verifyTelegramData(array $data, string $botToken): bool {
    if (!isset($data['hash'])) return false;
    $hash = $data['hash'];
    unset($data['hash']);

    ksort($data);

    $dataCheckString = implode("\n", array_map(
        fn($k, $v) => "$k=$v",
        array_keys($data),
        array_values($data)
    ));

    $secretKey = hash('sha256', $botToken, true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    return hash_equals($calculatedHash, $hash);
}

// -------------------------------
// LOGOUT
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

// -------------------------------
// TELEGRAM LOGIN CALLBACK
// -------------------------------
if (isset($_GET['id']) && isset($_GET['hash'])) {
    try {
        if (!verifyTelegramData($_GET, $telegramBotToken)) {
            throw new Exception("Invalid Telegram authentication data");
        }

        $telegramId = $_GET['id'];
        $firstName = $_GET['first_name'] ?? 'User';
        $lastName = $_GET['last_name'] ?? '';
        $username = $_GET['username'] ?? '';
        $photoUrl = $_GET['photo_url'] ?? '';

        // Save or update user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
            $insert->execute([$telegramId, $firstName]);
        }

        // Set session
        $_SESSION['user'] = [
            'telegram_id' => $telegramId,
            'name' => "$firstName $lastName",
            'username' => $username,
            'photo_url' => $photoUrl,
            'auth_provider' => 'telegram'
        ];

        // Redirect to index
        echo '<script>
            if (window.top !== window.self) {
                window.top.location.href = "' . $baseUrl . '/index.php";
            } else {
                window.location.href = "' . $baseUrl . '/index.php";
            }
        </script>';
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// -------------------------------
// AUTO-REDIRECT IF LOGGED IN
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
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 • Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="icon" href="https://cxchk.site/assets/branding/cardxchk-mark.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Futuristic Background */
        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(120, 20, 180, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(20, 120, 220, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(180, 20, 120, 0.2) 0%, transparent 70%),
                linear-gradient(135deg, #000 0%, #0a0a0a 50%, #121212 100%);
        }

        /* Animated Grid */
        .grid-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 200%;
            height: 200%;
            background-image: 
                linear-gradient(rgba(120, 20, 180, 0.2) 1px, transparent 1px),
                linear-gradient(90deg, rgba(120, 20, 180, 0.2) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
            transform: translate(-25%, -25%);
        }

        @keyframes gridMove {
            0% { transform: translate(-25%, -25%); }
            100% { transform: translate(0%, 0%); }
        }

        /* Hexagon Pattern */
        .hex-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 2;
            opacity: 0.1;
        }

        .hex {
            position: absolute;
            width: 60px;
            height: 34.64px;
            background-color: transparent;
            margin: 17.32px 0;
            border-left: solid 2px rgba(120, 20, 180, 0.5);
            border-right: solid 2px rgba(120, 20, 180, 0.5);
        }

        .hex:before,
        .hex:after {
            content: "";
            position: absolute;
            width: 0;
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
        }

        .hex:before {
            bottom: 100%;
            border-bottom: 17.32px solid rgba(120, 20, 180, 0.5);
        }

        .hex:after {
            top: 100%;
            border-top: 17.32px solid rgba(120, 20, 180, 0.5);
        }

        /* Particles */
        .particles-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 4;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        /* Particle animations */
        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(80px, -120px) scale(1.5); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(-100px, -90px) scale(1.3); }
        }

        @keyframes float3 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(-60px, -140px) scale(1.8); }
        }

        @keyframes float4 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(90px, -100px) scale(1.4); }
        }

        @keyframes float5 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(-80px, -110px) scale(1.6); }
        }

        @keyframes float6 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(70px, -130px) scale(1.2); }
        }

        @keyframes float7 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(-90px, -80px) scale(1.7); }
        }

        @keyframes float8 {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            50% { transform: translate(60px, -150px) scale(1.5); }
        }

        /* Scanlines */
        .scanlines {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            background: linear-gradient(
                to bottom,
                transparent 50%,
                rgba(255, 255, 255, 0.03) 50%
            );
            background-size: 100% 4px;
            animation: scanlines 8s linear infinite;
        }

        @keyframes scanlines {
            0% { background-position: 0 0; }
            100% { background-position: 0 10px; }
        }

        /* Glitch Effect */
        .glitch {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 6;
            pointer-events: none;
            animation: glitch 5s infinite;
        }

        @keyframes glitch {
            0%, 100% { opacity: 0; }
            5% { opacity: 0.1; transform: translateX(-5px); }
            5.1% { transform: translateX(5px); }
            5.2% { transform: translateX(-5px); }
            5.3% { transform: translateX(0); }
            10% { opacity: 0; }
        }

        /* Binary Rain */
        .binary-rain {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 7;
            overflow: hidden;
            opacity: 0.2;
        }

        .binary-column {
            position: absolute;
            top: -100%;
            font-family: monospace;
            font-size: 14px;
            color: rgba(120, 20, 180, 0.8);
            animation: binaryFall linear infinite;
        }

        @keyframes binaryFall {
            0% { top: -100%; }
            100% { top: 100%; }
        }

        /* Lightning Effect */
        .lightning {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 8;
            pointer-events: none;
        }

        .lightning-flash {
            position: absolute;
            width: 2px;
            height: 100px;
            background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.8), transparent);
            transform: rotate(45deg);
            opacity: 0;
            filter: blur(1px);
        }

        /* Main Container */
        .auth-container {
            position: relative;
            z-index: 100;
            width: 340px;
            max-width: 90vw;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo-wrapper {
            position: relative;
            width: 45px;
            height: 45px;
        }

        .logo-glow {
            position: absolute;
            top: -8px;
            left: -8px;
            right: -8px;
            bottom: -8px;
            background: rgba(120, 20, 180, 0.3);
            border-radius: 50%;
            animation: logoGlow 3s linear infinite;
            filter: blur(12px);
            opacity: 0.8;
        }

        @keyframes logoGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo {
            position: relative;
            z-index: 1;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border: 2px solid rgba(120, 20, 180, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo img {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 50%;
        }

        .brand-text {
            font-family: 'Orbitron', monospace;
            font-weight: 900;
            font-size: 22px;
            color: #fff;
            letter-spacing: 1px;
            animation: textGlow 2s ease-in-out infinite alternate;
            text-shadow: 0 0 10px rgba(120, 20, 180, 0.8);
        }

        @keyframes textGlow {
            0% { filter: drop-shadow(0 0 5px rgba(120, 20, 180, 0.5)); }
            100% { filter: drop-shadow(0 0 15px rgba(120, 20, 180, 0.8)); }
        }

        /* Login Card */
        .login-card {
            background: rgba(10, 10, 20, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(120, 20, 180, 0.3);
            border-radius: 24px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.6),
                0 0 120px rgba(120, 20, 180, 0.1),
                inset 0 0 0 1px rgba(120, 20, 180, 0.1);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, rgba(120, 20, 180, 0.3), rgba(20, 120, 220, 0.2), rgba(180, 20, 120, 0.3));
            border-radius: 24px;
            opacity: 0.6;
            z-index: -1;
            animation: borderGlow 4s linear infinite;
        }

        @keyframes borderGlow {
            0% { opacity: 0.3; }
            50% { opacity: 0.6; }
            100% { opacity: 0.3; }
        }

        /* Welcome Text */
        .welcome {
            text-align: center;
            margin-bottom: 22px;
        }

        .welcome h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: linear-gradient(90deg, #fff, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradientShift 3s ease infinite;
            position: relative;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .welcome p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        /* Error Message */
        .error {
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid rgba(255, 0, 0, 0.4);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 18px;
            font-size: 12px;
            color: #ff6b6b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Telegram Widget Container */
        .telegram-section {
            display: flex;
            justify-content: center;
            margin: 18px 0;
            min-height: 40px;
            position: relative;
        }

        .telegram-widget-container {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 40px;
        }

        .telegram-widget {
            display: block;
            width: 100%;
            text-align: center;
        }

        /* Security Badges */
        .security {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }

        .security-icon {
            width: 12px;
            height: 12px;
            color: #00ff88;
            flex-shrink: 0;
        }

        /* Footer - Enhanced */
        .footer {
            text-align: center;
            margin-top: 25px;
            position: relative;
            padding: 15px 0;
        }

        .footer-text {
            font-family: 'Orbitron', monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.3),
                rgba(255, 255, 255, 0.8),
                rgba(255, 255, 255, 0.3)
            );
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: footerGlow 3s ease-in-out infinite;
            position: relative;
            display: inline-block;
        }

        @keyframes footerGlow {
            0%, 100% { 
                filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.3));
                transform: scale(1);
            }
            50% { 
                filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.6));
                transform: scale(1.05);
            }
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: footerLine 4s ease-in-out infinite;
        }

        @keyframes footerLine {
            0%, 100% { opacity: 0.3; width: 60%; }
            50% { opacity: 0.8; width: 80%; }
        }

        .footer::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: footerLine 4s ease-in-out infinite reverse;
        }

        /* Retry Button */
        .retry-btn {
            background: rgba(120, 20, 180, 0.3);
            color: #fff;
            border: 1px solid rgba(120, 20, 180, 0.5);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .retry-btn:hover {
            background: rgba(120, 20, 180, 0.5);
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 380px) {
            .auth-container {
                width: 300px;
            }
            
            .login-card {
                padding: 24px 20px;
            }
            
            .brand-text {
                font-size: 18px;
            }
            
            .footer-text {
                font-size: 9px;
                letter-spacing: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-container"></div>
    
    <div class="grid-container">
        <div class="grid"></div>
    </div>
    
    <div class="hex-container" id="hexContainer"></div>
    
    <div class="particles-container" id="particlesContainer"></div>
    
    <div class="scanlines"></div>
    
    <div class="glitch"></div>
    
    <div class="binary-rain" id="binaryRain"></div>
    
    <div class="lightning" id="lightning"></div>

    <div class="auth-container">
        <div class="logo-section">
            <div class="logo-wrapper">
                <div class="logo-glow"></div>
                <div class="logo">
                    <img src="https://cxchk.site/assets/branding/cardxchk-mark.png" alt="Card X Chk">
                </div>
            </div>
            <div class="brand-text">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</div>
        </div>

        <div class="login-card">
            <div class="welcome">
                <h2>Secure Sign In</h2>
                <p>Authenticate via Telegram</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="telegram-section">
                <div class="telegram-widget-container">
                    <div class="telegram-widget">
                        <div class="telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>"></div>
                        <script async src="https://telegram.org/js/telegram-widget.js?22"
                                data-telegram-login="<?= htmlspecialchars($telegramBotUsername) ?>"
                                data-size="large"
                                data-auth-url="<?= $baseUrl ?>/login.php"
                                data-request-access="write"
                                onload="console.log('Telegram widget loaded')"
                                onerror="console.error('Telegram widget failed to load')"></script>
                    </div>
                </div>
            </div>

            <div class="security">
                <div class="security-item">
                    <svg class="security-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Secure</span>
                </div>
                <div class="security-item">
                    <svg class="security-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                    </svg>
                    <span>Private</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="footer-text">THE NEW ERA BEGINS</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Create hexagons
            const hexContainer = document.getElementById('hexContainer');
            for (let i = 0; i < 20; i++) {
                const hex = document.createElement('div');
                hex.className = 'hex';
                hex.style.left = `${Math.random() * 100}%`;
                hex.style.top = `${Math.random() * 100}%`;
                hexContainer.appendChild(hex);
            }
            
            // Create particles
            const particlesContainer = document.getElementById('particlesContainer');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random animation
                const duration = 10 + Math.random() * 20;
                const delay = Math.random() * 5;
                particle.style.animation = `float${(i % 8) + 1} ${duration}s ${delay}s infinite`;
                
                particlesContainer.appendChild(particle);
            }
            
            // Create binary rain
            const binaryRain = document.getElementById('binaryRain');
            for (let i = 0; i < 10; i++) {
                const column = document.createElement('div');
                column.className = 'binary-column';
                column.style.left = `${Math.random() * 100}%`;
                column.style.animationDuration = `${5 + Math.random() * 10}s`;
                column.style.animationDelay = `${Math.random() * 5}s`;
                
                // Generate random binary string
                let binary = '';
                for (let j = 0; j < 20; j++) {
                    binary += Math.random() > 0.5 ? '1' : '0';
                }
                column.textContent = binary;
                
                binaryRain.appendChild(column);
            }
            
            // Create lightning effect
            const lightning = document.getElementById('lightning');
            setInterval(() => {
                if (Math.random() > 0.7) {
                    const flash = document.createElement('div');
                    flash.className = 'lightning-flash';
                    flash.style.left = `${Math.random() * 100}%`;
                    flash.style.top = `${Math.random() * 100}%`;
                    flash.style.height = `${50 + Math.random() * 150}px`;
                    flash.style.transform = `rotate(${Math.random() * 360}deg)`;
                    flash.style.opacity = '0';
                    flash.style.transition = 'opacity 0.2s';
                    
                    lightning.appendChild(flash);
                    
                    setTimeout(() => {
                        flash.style.opacity = '0.8';
                    }, 10);
                    
                    setTimeout(() => {
                        flash.style.opacity = '0';
                    }, 200);
                    
                    setTimeout(() => {
                        lightning.removeChild(flash);
                    }, 400);
                }
            }, 2000);
            
            // Handle Telegram Widget Loading - No fallback, no auto-retry, no error notification
            // Just console error if widget fails to load
            const telegramWidget = document.querySelector('.telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>');
            
            // Check if widget loaded after a delay
            setTimeout(() => {
                if (!telegramWidget || !telegramWidget.querySelector('iframe')) {
                    console.error('Telegram widget not loaded');
                }
            }, 3000);
        });
    </script>
</body>
</html>
