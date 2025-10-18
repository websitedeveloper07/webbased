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
    error_log("Database connection failed: " . $e->getMessage());
    $dbError = true;
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
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            if ($stmt->rowCount() === 0) {
                $insert = $pdo->prepare("INSERT INTO users (telegram_id, name, auth_provider) VALUES (?, ?, 'telegram')");
                $insert->execute([$telegramId, $firstName]);
            }
        }

        // Set session with the exact structure expected by index.php
        $_SESSION['user'] = [
            'telegram_id' => $telegramId,
            'name' => trim("$firstName $lastName"),
            'username' => $username,
            'photo_url' => $photoUrl,
            'auth_provider' => 'telegram'
        ];

        // Log successful authentication
        error_log("User authenticated: " . json_encode($_SESSION['user']));

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
        error_log("Authentication error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// -------------------------------
// AUTO-REDIRECT IF LOGGED IN
// -------------------------------
if (isset($_SESSION['user']) && $_SESSION['user']['auth_provider'] === 'telegram') {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;600;700&family=Caveat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Enhanced Background */
        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a0033 50%, #001122 100%);
        }

        /* Animated gradient overlay */
        .gradient-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(0, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(0, 128, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 50% 50%, rgba(138, 43, 226, 0.05) 0%, transparent 70%);
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { opacity: 0.7; transform: scale(1) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.1) rotate(5deg); }
        }

        /* Circuit grid pattern */
        .circuit-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(40px, 40px); }
        }

        /* Moving Text Background */
        .moving-text {
            position: absolute;
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            font-size: 120px;
            color: rgba(0, 255, 255, 0.03);
            white-space: nowrap;
            user-select: none;
            pointer-events: none;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
        }

        .text-1 {
            top: 10%;
            left: -50%;
            animation: moveRight 30s linear infinite;
        }

        .text-2 {
            top: 30%;
            right: -50%;
            animation: moveLeft 35s linear infinite;
        }

        .text-3 {
            bottom: 30%;
            left: -50%;
            animation: moveRight 40s linear infinite;
        }

        .text-4 {
            bottom: 10%;
            right: -50%;
            animation: moveLeft 45s linear infinite;
        }

        @keyframes moveRight {
            0% { transform: translateX(0); }
            100% { transform: translateX(200%); }
        }

        @keyframes moveLeft {
            0% { transform: translateX(0); }
            100% { transform: translateX(-200%); }
        }

        /* Floating particles */
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
            background: rgba(0, 255, 255, 0.5);
            border-radius: 50%;
            filter: blur(2px);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.8);
        }

        .particle:nth-child(1) {
            width: 5px;
            height: 5px;
            top: 20%;
            left: 10%;
            animation: float 15s infinite linear;
        }

        .particle:nth-child(2) {
            width: 8px;
            height: 8px;
            top: 60%;
            left: 70%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(3) {
            width: 4px;
            height: 4px;
            top: 40%;
            left: 30%;
            animation: float 18s infinite linear;
        }

        .particle:nth-child(4) {
            width: 6px;
            height: 6px;
            top: 80%;
            left: 50%;
            animation: float 22s infinite linear;
        }

        .particle:nth-child(5) {
            width: 3px;
            height: 3px;
            top: 30%;
            left: 80%;
            animation: float 25s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) translateX(100px); opacity: 0; }
        }

        /* Glowing orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.4;
        }

        .orb-1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.4) 0%, transparent 70%);
            top: -150px;
            left: -150px;
            animation: orbFloat 20s infinite alternate ease-in-out;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(138, 43, 226, 0.3) 0%, transparent 70%);
            bottom: -200px;
            right: -200px;
            animation: orbFloat 25s infinite alternate-reverse ease-in-out;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(0, 128, 255, 0.3) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: orbPulse 15s infinite ease-in-out;
        }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 50px) scale(1.1); }
        }

        @keyframes orbPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.3; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.5; }
        }

        /* Main Container */
        .auth-container {
            position: relative;
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* Header with Logo and Text */
        .auth-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 5px;
        }

        /* Logo - Enhanced with glow */
        .logo-container {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a0a0a, #1a1a2e);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .logo-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(0, 255, 255, 0.1),
                transparent
            );
            transform: rotate(45deg);
            animation: logoShine 3s infinite;
        }

        @keyframes logoShine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .logo-container img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            position: relative;
            z-index: 1;
        }

        /* Secure Sign In Text - Enhanced with glow */
        .secure-signin {
            font-family: 'Caveat', cursive;
            font-size: 24px;
            font-weight: 600;
            color: #00ffff;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            animation: textGlow 2s infinite alternate;
        }

        @keyframes textGlow {
            0% { text-shadow: 0 0 10px rgba(0, 255, 255, 0.5); }
            100% { text-shadow: 0 0 20px rgba(0, 255, 255, 0.8), 0 0 30px rgba(0, 255, 255, 0.4); }
        }

        /* Compact Login Box - Enhanced with glassmorphism */
        .login-box {
            width: 280px;
            background: rgba(10, 10, 20, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 12px;
            padding: 25px 20px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            overflow: hidden;
        }

        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.5), transparent);
            animation: scanLine 3s infinite;
        }

        @keyframes scanLine {
            0% { transform: translateY(0); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(300px); opacity: 0; }
        }

        /* Title */
        .brand-title {
            font-family: 'Orbitron', monospace;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 4px;
            background: linear-gradient(135deg, #00ffff, #0080ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .brand-subtitle {
            font-size: 10px;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Telegram Widget */
        .telegram-section {
            display: flex;
            justify-content: center;
            margin: 15px 0;
            position: relative;
            min-height: 40px;
        }

        /* Error Messages */
        .error-box {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 11px;
            color: #ff6b6b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 11px;
            color: #ffc107;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Fallback Button */
        .fallback-btn {
            width: 100%;
            background: linear-gradient(135deg, #0088cc, #005580);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .fallback-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 136, 204, 0.3);
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
            font-size: 9px;
            color: rgba(0, 255, 0, 0.7);
        }

        /* Footer */
        .footer-text {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.4);
            text-align: center;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Loading State */
        .widget-loader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .loader {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 255, 255, 0.2);
            border-radius: 50%;
            border-top-color: #00ffff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loader-text {
            margin-top: 4px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Error State */
        .error-state {
            display: none;
            text-align: center;
            padding: 12px;
            background: rgba(255, 0, 0, 0.05);
            border-radius: 6px;
            margin: 8px 0;
        }

        .error-icon {
            font-size: 18px;
            color: #ff4444;
            margin-bottom: 4px;
        }

        .error-message {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 6px;
        }

        .retry-btn {
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .retry-btn:hover {
            background: #00cccc;
        }

        /* Responsive */
        @media (max-width: 380px) {
            .login-box {
                width: 260px;
                padding: 20px 15px;
            }
            
            .secure-signin {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-container">
        <div class="gradient-overlay"></div>
        <div class="circuit-grid"></div>
        
        <!-- Glowing Orbs -->
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        
        <!-- Moving Text -->
        <div class="moving-text text-1">CARD X CHK</div>
        <div class="moving-text text-2">CARD X CHK</div>
        <div class="moving-text text-3">CARD X CHK</div>
        <div class="moving-text text-4">CARD X CHK</div>
        
        <!-- Floating Particles -->
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
    </div>
    
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo-container">
                <img src="https://cxchk.site/assets/branding/cardxchk-mark.png" alt="Card X Chk">
            </div>
            <div class="secure-signin">Secure Sign In</div>
        </div>
        
        <div class="login-box">
            <h1 class="brand-title">Card X Chk</h1>
            <p class="brand-subtitle">The New Era Begins</p>
            
            <?php if (!empty($error)): ?>
                <div class="error-box">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($dbError)): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Database issue. You can still sign in.</span>
                </div>
            <?php endif; ?>
            
            <div class="telegram-section">
                <div id="telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>"></div>
                <div class="widget-loader" id="widgetLoader">
                    <div class="loader"></div>
                    <div class="loader-text">Loading...</div>
                </div>
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="<?= htmlspecialchars($telegramBotUsername) ?>"
                        data-size="medium"
                        data-auth-url="<?= $baseUrl ?>/login.php"
                        data-request-access="write"
                        onload="document.getElementById('widgetLoader').style.display='none'"
                        onerror="console.error('Telegram widget failed to load')"></script>
            </div>
            
            <div class="error-state" id="errorState">
                <i class="fas fa-exclamation-triangle error-icon"></i>
                <p class="error-message">Telegram login failed</p>
                <button class="retry-btn" onclick="location.reload()">
                    <i class="fas fa-redo mr-1"></i> Retry
                </button>
            </div>
            
            <div class="fallback-section" id="fallbackSection" style="display: none;">
                <a href="https://t.me/<?= htmlspecialchars($telegramBotUsername) ?>?start=auth" target="_blank" class="fallback-btn">
                    <i class="fab fa-telegram"></i>
                    <span>Open Telegram</span>
                </a>
            </div>
            
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Secure • Telegram Auth</span>
            </div>
        </div>
        
        <div class="footer-text">𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Check Telegram widget
        setTimeout(() => {
            const telegramWidget = document.querySelector('#telegram-login-<?= htmlspecialchars($telegramBotUsername) ?> iframe');
            if (!telegramWidget) {
                document.getElementById('errorState').style.display = 'block';
                document.getElementById('fallbackSection').style.display = 'block';
                document.getElementById('widgetLoader').style.display = 'none';
            }
        }, 4000);
        
        // Handle widget errors
        window.addEventListener('error', (e) => {
            if (e.message.includes('telegram-widget.js')) {
                document.getElementById('errorState').style.display = 'block';
                document.getElementById('fallbackSection').style.display = 'block';
                document.getElementById('widgetLoader').style.display = 'none';
            }
        });
        
        // Add interactive particles on mouse move
        document.addEventListener('mousemove', (e) => {
            if (Math.random() > 0.98) {
                createParticle(e.clientX, e.clientY);
            }
        });
        
        function createParticle(x, y) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = x + 'px';
            particle.style.top = y + 'px';
            particle.style.width = Math.random() * 5 + 2 + 'px';
            particle.style.height = particle.style.width;
            
            document.querySelector('.particles').appendChild(particle);
            
            const angle = Math.random() * Math.PI * 2;
            const velocity = Math.random() * 2 + 1;
            const lifetime = Math.random() * 1000 + 500;
            
            let opacity = 1;
            let posX = x;
            let posY = y;
            
            const animation = setInterval(() => {
                posX += Math.cos(angle) * velocity;
                posY += Math.sin(angle) * velocity;
                opacity -= 0.01;
                
                particle.style.left = posX + 'px';
                particle.style.top = posY + 'px';
                particle.style.opacity = opacity;
                
                if (opacity <= 0) {
                    clearInterval(animation);
                    particle.remove();
                }
            }, 20);
        }
    </script>
</body>
</html>
