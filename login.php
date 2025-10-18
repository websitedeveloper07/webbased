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
    <title>Card X Chk • Portal</title>
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

        /* Subtle Background */
        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(40, 40, 60, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(60, 60, 80, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(50, 50, 70, 0.2) 0%, transparent 70%),
                linear-gradient(135deg, #000 0%, #0a0a0a 50%, #121212 100%);
        }

        /* Energy Grid */
        .energy-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: gridPulse 8s ease-in-out infinite;
            opacity: 0.3;
        }

        @keyframes gridPulse {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.02); }
        }

        /* Energy Waves */
        .energy-wave {
            position: absolute;
            border-radius: 50%;
            border: 2px solid;
            animation: waveExpand 4s linear infinite;
        }

        .wave-1 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-color: rgba(255, 255, 255, 0.1);
            animation-delay: 0s;
        }

        .wave-2 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-color: rgba(255, 255, 255, 0.08);
            animation-delay: 1s;
        }

        .wave-3 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-color: rgba(255, 255, 255, 0.06);
            animation-delay: 2s;
        }

        @keyframes waveExpand {
            0% {
                width: 0;
                height: 0;
                opacity: 1;
            }
            100% {
                width: 600px;
                height: 600px;
                opacity: 0;
            }
        }

        /* Floating Particles */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .particle:nth-child(1) { top: 15%; left: 25%; animation: float1 10s infinite; }
        .particle:nth-child(2) { top: 65%; left: 75%; animation: float2 12s infinite; }
        .particle:nth-child(3) { top: 35%; left: 55%; animation: float3 15s infinite; }
        .particle:nth-child(4) { top: 75%; left: 35%; animation: float4 11s infinite; }
        .particle:nth-child(5) { top: 25%; left: 65%; animation: float5 14s infinite; }
        .particle:nth-child(6) { top: 45%; left: 15%; animation: float6 13s infinite; }
        .particle:nth-child(7) { top: 85%; left: 85%; animation: float7 16s infinite; }
        .particle:nth-child(8) { top: 5%; left: 45%; animation: float8 9s infinite; }

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

        /* Main Container - Compact */
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
            background: rgba(255, 255, 255, 0.1);
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
            border: 2px solid rgba(255, 255, 255, 0.2);
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
        }

        @keyframes textGlow {
            0% { filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.3)); }
            100% { filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5)); }
        }

        /* Login Card */
        .login-card {
            background: rgba(10, 10, 20, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.6),
                0 0 120px rgba(255, 255, 255, 0.05),
                inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.1));
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
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        /* Telegram Widget - Using exact logic from provided script */
        .telegram-section {
            display: flex;
            justify-content: center;
            margin: 18px 0;
            min-height: 40px;
            position: relative;
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

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 18px;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 3px;
            text-transform: uppercase;
            font-family: 'Orbitron', monospace;
        }

        /* Retry Button */
        .retry-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            background: rgba(255, 255, 255, 0.2);
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
        }
    </style>
</head>
<body>
    <div class="bg-container">
        <div class="energy-grid"></div>
        <div class="energy-wave wave-1"></div>
        <div class="energy-wave wave-2"></div>
        <div class="energy-wave wave-3"></div>
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
    </div>

    <div class="auth-container">
        <div class="logo-section">
            <div class="logo-wrapper">
                <div class="logo-glow"></div>
                <div class="logo">
                    <img src="https://cxchk.site/assets/branding/cardxchk-mark.png" alt="Card X Chk">
                </div>
            </div>
            <div class="brand-text">Card X Chk</div>
        </div>

        <div class="login-card">
            <div class="welcome">
                <h2>System Access</h2>
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
                <!-- Using exact Telegram widget logic from provided script -->
                <div class="telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>"></div>
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="<?= htmlspecialchars($telegramBotUsername) ?>"
                        data-size="large"
                        data-auth-url="<?= $baseUrl ?>/login.php"
                        data-request-access="write"
                        onload="console.log('Telegram widget loaded')"
                        onerror="console.error('Telegram widget failed to load')"></script>
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
                <div class="security-item">
                    <svg class="security-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    <span>API</span>
                </div>
            </div>
        </div>

        <div class="footer">
            THE NEW ERA BEGINS
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const telegramWidget = document.querySelector('.telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>');
            if (!telegramWidget || !telegramWidget.querySelector('iframe')) {
                console.error('Telegram widget not loaded');
                error_log('Telegram widget not loaded in DOM');
                
                // Fallback UI if widget fails to load
                const widgetContainer = document.querySelector('.telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>');
                if (widgetContainer) {
                    widgetContainer.innerHTML = `
                        <div style="padding: 16px; background: rgba(255, 0, 0, 0.1); border: 1px solid rgba(255, 0, 0, 0.3); border-radius: 12px; text-align: center;">
                            <p style="color: #ff6b6b; font-size: 12px; margin-bottom: 6px;">Telegram widget failed to load</p>
                            <p style="color: rgba(255, 255, 255, 0.6); font-size: 10px; margin-bottom: 10px;">Please check your connection and try again</p>
                            <button onclick="location.reload()" class="retry-btn">Retry</button>
                        </div>
                    `;
                }
                
                // Auto-retry after delay
                setTimeout(() => {
                    location.reload();
                }, 5000);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
