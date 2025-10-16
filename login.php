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
    <title>Sign in • Card X Chk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/assets/branding/cardxchk-mark.png" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
    <style>
        :root {
            --glass: rgba(20, 20, 30, 0.7);
            --stroke: rgba(255, 255, 255, 0.08);
            --accent: #6366f1;
            --accent-light: #818cf8;
        }
        html, body {
            height: 100%;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial;
            overflow-x: hidden;
        }
        body {
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.1) 0%, transparent 50%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            position: relative;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            z-index: 0;
        }
        .glass {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: var(--glass);
            border: 1px solid var(--stroke);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .card {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.6), inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.4);
        }
        .logo-container {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (max-width: 640px) {
            .glass {
                padding: 1.5rem !important;
            }
            h1 {
                font-size: 1.5rem !important;
            }
        }
    </style>
</head>
<body class="min-h-full">
    <main class="min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-10">
        <div class="w-full max-w-md sm:max-w-xl space-y-6 sm:space-y-8">
            <div class="flex flex-col items-center text-center">
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl logo-container grid place-items-center shadow-xl">
                    <img src="/assets/branding/cardxchk-mark.png" alt="Card X Chk" class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl" onerror="this.onerror=null; this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
                </div>
                <h1 class="mt-4 text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-100">Card X Chk</h1>
                <p class="mt-1 text-sm sm:text-base text-gray-400">Secure Sign-in Portal</p>
            </div>

            <div class="glass card rounded-3xl p-6 sm:p-8">
                <div class="flex flex-col items-center gap-5 sm:gap-6">
                    <div class="text-center">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-100">Welcome Back</h2>
                        <p class="mt-1 text-sm text-gray-400">Sign in with Telegram to continue</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="w-full p-3 bg-red-900/30 border border-red-800/50 rounded-lg text-red-300 text-sm">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="w-full flex justify-center">
                        <div class="telegram-login-<?= htmlspecialchars($telegramBotUsername) ?>"></div>
                        <script async src="https://telegram.org/js/telegram-widget.js?22"
                                data-telegram-login="<?= htmlspecialchars($telegramBotUsername) ?>"
                                data-size="large"
                                data-auth-url="<?= $baseUrl ?>/login.php"
                                data-request-access="write"
                                onload="console.log('Telegram widget loaded')"
                                onerror="console.error('Telegram widget failed to load')"></script>
                    </div>

                    <div class="mt-2 text-center">
                        <p class="text-xs text-gray-500">
                            <span class="inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Secure authentication with Telegram
                            </span>
                        </p>
                        <p class="mt-1 text-[11px] text-gray-600">
                            We don't store your Telegram credentials
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center text-xs text-gray-600 tracking-widest">
                𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒
            </div>
            <div class="flex items-center justify-center gap-2 text-xs text-gray-600">
                <span>Powered by kคli liຖนxx</span>
            </div>
        </div>
    </main>
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
                        <div class="p-4 bg-red-900/30 border border-red-800/50 rounded-lg text-center">
                            <p class="text-red-300 text-sm">Telegram widget failed to load</p>
                            <p class="text-gray-400 text-xs mt-1">Please check your connection and try again</p>
                            <button onclick="location.reload()" class="mt-3 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition">
                                Retry
                            </button>
                        </div>
                    `;
                }
                
                // Show error notification
                Swal.fire({
                    title: 'Configuration Error',
                    text: 'Telegram Login Widget failed to load. Check bot settings, network, or Render CSP settings.',
                    icon: 'error',
                    confirmButtonColor: '#6ab7d8'
                });
                
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
