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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign in • Card X Chk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/assets/branding/cardxchk-mark.png" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
    <style>
        :root {
            --primary: #0a0e27;
            --secondary: #131937;
            --accent: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-cyan: #06b6d4;
            --glass: rgba(255, 255, 255, 0.05);
            --stroke: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            height: 100%;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial;
            overflow: hidden;
        }
        
        body {
            background: 
                radial-gradient(ellipse at top right, rgba(139, 92, 246, 0.15), transparent 50%),
                radial-gradient(ellipse at bottom left, rgba(59, 130, 246, 0.15), transparent 50%),
                linear-gradient(135deg, #0a0e27, #131937);
            color: var(--text-primary);
        }
        
        .glass {
            backdrop-filter: blur(20px);
            background: var(--glass);
            border: 1px solid var(--stroke);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .card {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }
        
        .brand-title {
            font-family: 'Orbitron', Inter, sans-serif;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
        
        .glow-button {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
        }
        
        .glow-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent-purple));
            opacity: 0;
            z-index: -1;
            transition: opacity 0.3s ease;
        }
        
        .glow-button:hover::before {
            opacity: 0.7;
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }
        
        .shape-1 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent));
            top: -150px;
            right: -100px;
            animation-delay: 0s;
        }
        
        .shape-2 {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent));
            bottom: -100px;
            left: -50px;
            animation-delay: 5s;
        }
        
        .shape-3 {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--accent), var(--accent-cyan));
            top: 50%;
            left: 10%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(10deg);
            }
        }
        
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: var(--text-primary);
            opacity: 0.4;
            animation: particle-float 15s infinite linear;
        }
        
        @keyframes particle-float {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 0.4;
            }
            90% {
                opacity: 0.4;
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
                opacity: 0;
            }
        }
        
        .telegram-button {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .telegram-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        
        .loader {
            width: 30px;
            height: 30px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--accent);
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 1s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        /* Mobile-specific styles */
        @media (max-width: 640px) {
            .brand-title {
                font-size: 1.75rem;
            }
            
            .card {
                padding: 1.5rem !important;
                margin: 0 1rem;
            }
            
            .shape-1 {
                width: 200px;
                height: 200px;
                top: -100px;
                right: -50px;
            }
            
            .shape-2 {
                width: 150px;
                height: 150px;
                bottom: -75px;
                left: -25px;
            }
            
            .shape-3 {
                width: 100px;
                height: 100px;
            }
            
            .particles {
                display: none; /* Disable particles on mobile for performance */
            }
        }
        
        /* Touch-friendly adjustments */
        .telegram-button {
            min-height: 44px; /* Minimum touch target size */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Prevent zoom on input focus */
        input, textarea, select {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        /* Ensure proper viewport scaling */
        @viewport {
            width: device-width;
            initial-scale: 1.0;
            maximum-scale: 1.0;
            user-scalable: 0;
        }
    </style>
</head>
<body class="min-h-full">
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
    
    <div class="particles" id="particles"></div>
    
    <main class="min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-10">
        <div class="w-full max-w-md space-y-6 sm:space-y-8 fade-in">
            <div class="flex flex-col items-center text-center">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl glass flex items-center justify-center shadow-lg pulse">
                    <i class="fas fa-credit-card text-2xl sm:text-3xl text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500"></i>
                </div>
                <h1 class="mt-4 sm:mt-6 text-3xl sm:text-4xl font-bold brand-title tracking-tight">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
                <p class="mt-1 sm:mt-2 text-base sm:text-lg text-gray-300">Secure Authentication Portal</p>
            </div>

            <div class="glass card rounded-2xl p-6 sm:p-8 border border-gray-700/50">
                <div class="flex flex-col items-center gap-4 sm:gap-6">
                    <div class="text-center">
                        <h2 class="text-lg sm:text-xl font-semibold text-white">Welcome Back</h2>
                        <p class="mt-1 text-sm sm:text-base text-gray-400">Sign in with your Telegram account</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="w-full p-3 rounded-lg bg-red-900/20 border border-red-800/50 text-red-300 text-sm">
                            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="w-full flex justify-center telegram-button">
                        <div id="telegram-login-container" class="w-full flex justify-center">
                            <div class="loader" id="telegram-loader"></div>
                        </div>
                        <div id="telegram-widget" style="display: none;">
                            <div class="telegram-login-CARDXCHK_LOGBOT"></div>
                            <script async src="https://telegram.org/js/telegram-widget.js?22"
                                    data-telegram-login="CARDXCHK_LOGBOT"
                                    data-size="large"
                                    data-auth-url="<?= $baseUrl ?>/login.php"
                                    data-request-access="write"
                                    onload="console.log('Telegram widget loaded')"
                                    onerror="console.error('Telegram widget failed to load')"></script>
                        </div>
                    </div>

                    <div class="text-center text-xs text-gray-500 mt-2 sm:mt-4">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Telegram OAuth is secure. We do not get access to your account.
                    </div>
                </div>
            </div>

            <div class="text-center">
                <div class="text-xs text-gray-500 mb-1 sm:mb-2">𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</div>
                <div class="flex items-center justify-center gap-2 text-xs text-gray-600">
                    <span>Powered by</span>
                    <span class="font-medium">kคli liຖนxx</span>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Create floating particles (disabled on mobile)
        function createParticles() {
            // Skip on mobile devices for better performance
            if (window.innerWidth <= 640) return;
            
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random size between 1px and 4px
                const size = Math.random() * 3 + 1;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random animation duration between 10s and 20s
                const duration = Math.random() * 10 + 10;
                particle.style.animationDuration = `${duration}s`;
                
                // Random animation delay
                const delay = Math.random() * 5;
                particle.style.animationDelay = `${delay}s`;
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Initialize particles
        createParticles();
        
        // Handle Telegram widget loading
        document.addEventListener('DOMContentLoaded', () => {
            // Simulate loading delay
            setTimeout(() => {
                const loader = document.getElementById('telegram-loader');
                const widget = document.getElementById('telegram-widget');
                
                if (loader) loader.style.display = 'none';
                if (widget) widget.style.display = 'block';
                
                // Check if widget loaded properly
                setTimeout(() => {
                    const telegramWidget = document.querySelector('.telegram-login-CARDXCHK_LOGBOT');
                    if (!telegramWidget || !telegramWidget.querySelector('iframe')) {
                        console.error('Telegram widget not loaded');
                        Swal.fire({
                            title: 'Configuration Error',
                            text: 'Telegram Login Widget failed to load. Check bot settings, network, or Render CSP settings.',
                            icon: 'error',
                            confirmButtonColor: '#6ab7d8',
                            confirmButtonText: 'Retry'
                        }).then(() => {
                            location.reload();
                        });
                    }
                }, 2000);
            }, 1500);
        });
        
        // Add interactive hover effect to the card (disabled on mobile)
        if (window.innerWidth > 640) {
            const card = document.querySelector('.card');
            if (card) {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const angleX = (y - centerY) / 20;
                    const angleY = (centerX - x) / 20;
                    
                    card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
                });
            }
        }
        
        // Handle orientation changes
        window.addEventListener('orientationchange', () => {
            // Reinitialize particles if needed
            const particlesContainer = document.getElementById('particles');
            if (particlesContainer) {
                particlesContainer.innerHTML = '';
                createParticles();
            }
        });
        
        // Prevent double-tap zoom on iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (event) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>
</html>
