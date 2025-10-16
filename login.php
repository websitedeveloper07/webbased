<?php
// -------------------------------
// SESSION & INITIAL CONFIG
// -------------------------------
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
session_start();

// Set CSP headers to allow Telegram widget
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://telegram.org https://cdn.jsdelivr.net; frame-src 'self' https://oauth.telegram.org; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign in • Card X Chk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Enhanced Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #0f172a 50%, #1e293b 75%, #0f172a 100%),
                radial-gradient(ellipse at top right, rgba(139, 92, 246, 0.2) 0%, transparent 40%),
                radial-gradient(ellipse at bottom left, rgba(59, 130, 246, 0.2) 0%, transparent 40%);
            z-index: -2;
        }
        
        /* Animated Grid Pattern */
        .grid-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            animation: grid-move 20s linear infinite;
        }
        
        @keyframes grid-move {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* Floating Orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.6;
            z-index: -1;
        }
        
        .orb-1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #3b82f6, transparent);
            top: -150px;
            right: -100px;
            animation: float-1 20s ease-in-out infinite;
        }
        
        .orb-2 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, #8b5cf6, transparent);
            bottom: -100px;
            left: -50px;
            animation: float-2 15s ease-in-out infinite;
        }
        
        .orb-3 {
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, #06b6d4, transparent);
            top: 50%;
            left: 10%;
            animation: float-3 25s ease-in-out infinite;
        }
        
        @keyframes float-1 {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-30px) translateX(20px); }
        }
        
        @keyframes float-2 {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(30px) translateX(-20px); }
        }
        
        @keyframes float-3 {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-20px) translateX(30px); }
        }
        
        /* Login Container */
        .login-container {
            width: 90%;
            max-width: 360px;
            margin: 20px;
            padding: 30px 25px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                inset 0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10;
        }
        
        /* Logo Section */
        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 14px;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);
            transition: transform 0.3s ease;
        }
        
        .logo-icon:hover {
            transform: translateY(-3px);
        }
        
        .brand-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 900;
            text-align: center;
            margin-bottom: 6px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
            letter-spacing: 1px;
        }
        
        .tagline {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 30px;
        }
        
        /* Telegram Widget Container */
        .telegram-widget-container {
            margin: 25px 0;
            display: flex;
            justify-content: center;
            position: relative;
            min-height: 50px;
            align-items: center;
        }
        
        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
        }
        
        .error-message i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 11px;
            color: #64748b;
        }
        
        .footer-divider {
            margin: 16px 0;
            color: #475569;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .footer-brand {
            font-weight: 600;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
        
        /* Loader */
        .loader {
            width: 24px;
            height: 24px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            body {
                padding: 0;
            }
            
            .login-container {
                width: 95%;
                max-width: 320px;
                margin: 15px;
                padding: 25px 20px;
            }
            
            .brand-name {
                font-size: 22px;
            }
            
            .logo-icon {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
            
            .tagline {
                font-size: 12px;
            }
            
            .orb-1 {
                width: 180px;
                height: 180px;
                top: -90px;
                right: -50px;
            }
            
            .orb-2 {
                width: 120px;
                height: 120px;
                bottom: -60px;
                left: -30px;
            }
            
            .orb-3 {
                width: 80px;
                height: 80px;
            }
            
            .grid-pattern {
                background-size: 30px 30px;
            }
            
            .error-message {
                font-size: 12px;
                padding: 10px;
            }
            
            .footer {
                font-size: 10px;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 360px) {
            .login-container {
                width: 98%;
                max-width: 300px;
                padding: 20px 15px;
            }
            
            .brand-name {
                font-size: 20px;
            }
            
            .logo-icon {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Background Layers -->
    <div class="background"></div>
    <div class="grid-pattern"></div>
    
    <!-- Floating Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    
    <!-- Main Login Container -->
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <h1 class="brand-name">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
            <p class="tagline">Secure Authentication Portal</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Telegram Widget Container -->
        <div class="telegram-widget-container">
            <div class="loader" id="telegram-loader"></div>
            <div id="telegram-widget" style="display: none;">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="<?= $telegramBotUsername ?>"
                        data-size="large"
                        data-auth-url="<?= $baseUrl ?>/login.php"
                        data-request-access="write"
                        data-userpic="false"
                        onload="widgetLoaded()"
                        onerror="widgetError()"></script>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-divider">𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</div>
            <div>Powered by <span class="footer-brand">kคli liຖนxx</span></div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function widgetLoaded() {
            // Hide loader and show widget
            document.getElementById('telegram-loader').style.display = 'none';
            document.getElementById('telegram-widget').style.display = 'block';
        }
        
        function widgetError() {
            // Hide loader and show error
            document.getElementById('telegram-loader').style.display = 'none';
            
            Swal.fire({
                title: 'Login Issue',
                text: 'Telegram login failed to load. Please check your connection and try again.',
                icon: 'warning',
                confirmButtonText: 'Retry',
                confirmButtonColor: '#3b82f6'
            }).then(() => {
                location.reload();
            });
        }
        
        // Check if widget loaded after timeout
        setTimeout(() => {
            const loader = document.getElementById('telegram-loader');
            const widget = document.querySelector('#telegram-widget iframe');
            
            // If loader is still visible and no widget found, show error
            if (loader && loader.style.display !== 'none' && !widget) {
                widgetError();
            }
        }, 5000);
        
        // Add subtle parallax effect on mouse move (desktop only)
        if (window.innerWidth > 768) {
            document.addEventListener('mousemove', (e) => {
                const orbs = document.querySelectorAll('.orb');
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                orbs.forEach((orb, index) => {
                    const speed = (index + 1) * 10;
                    const xPos = (x - 0.5) * speed;
                    const yPos = (y - 0.5) * speed;
                    
                    orb.style.transform = `translate(${xPos}px, ${yPos}px)`;
                });
            });
        }
    </script>
</body>
</html>
