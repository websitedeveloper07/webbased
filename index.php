<?php
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log session state
error_log("Checking session in index.php: " . json_encode($_SESSION));

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    error_log("Redirecting to login.php: Session missing or invalid auth_provider");
    header('Location: https://yourdomain.onrender.com/login.php'); // Replace with your Render domain
    exit;
}

// Load environment variables manually
$envFile = __DIR__ . '/.env';
$_ENV = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
} else {
    error_log("Environment file (.env) not found in " . __DIR__);
}

// Database connection (optional, for future result storage)
try {
    if (!isset($_ENV['DATABASE_URL'])) {
        error_log("DATABASE_URL not set in .env file");
    } else {
        $dbUrl = parse_url($_ENV['DATABASE_URL']);
        if (!$dbUrl || !isset($dbUrl['host'], $dbUrl['port'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
            throw new Exception("Invalid DATABASE_URL format");
        }
        $pdo = new PDO(
            "pgsql:host={$dbUrl['host']};port={$dbUrl['port']};dbname=" . ltrim($dbUrl['path'], '/'),
            $dbUrl['user'],
            $dbUrl['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        error_log("Database connected in index.php");

        // Create results table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS results (
                id SERIAL PRIMARY KEY,
                telegram_id BIGINT REFERENCES users(telegram_id),
                card_number VARCHAR(19),
                status VARCHAR(20),
                response TEXT,
                gateway VARCHAR(50),
                checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        error_log("Results table ready");
    }
} catch (Exception $e) {
    error_log("Database connection failed in index.php: " . $e->getMessage());
    // Continue without DB connection (non-fatal)
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 - 𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            box-sizing: border-box;
            user-select: none;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: 
                radial-gradient(1100px 700px at 8% -10%, rgba(255, 135, 135, 0.20), transparent 60%),
                radial-gradient(900px 500px at 110% 110%, rgba(109, 211, 203, 0.16), transparent 60%),
                linear-gradient(45deg, #f9ecec, #e0f6f5);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #1a1a2e;
            position: relative;
            overflow-x: hidden;
        }
        canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 10px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card:hover {
            transform: scale(1.02);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }
        .title-box {
            background: linear-gradient(45deg, #ff8787, #6ab7d8);
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .title-box h1 {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        .title-box p {
            font-size: 0.95rem;
            color: white;
            opacity: 0.85;
            margin: 5px 0 0;
        }
        .menu-toggle {
            cursor: pointer;
            color: #6ab7d8;
            font-size: 1.6rem;
            padding: 10px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .menu-toggle:hover {
            transform: scale(1.1);
        }
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff8a80, #f06292);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .back-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .sidebar {
            position: fixed;
            top: 0;
            right: -300px;
            width: 300px;
            height: 100%;
            background: linear-gradient(135deg, rgba(249, 236, 236, 0.95), rgba(224, 246, 245, 0.95));
            backdrop-filter: blur(8px);
            box-shadow: -4px 0 10px rgba(0, 0, 0, 0.1);
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .sidebar.show {
            right: 0;
        }
        .sidebar-item {
            padding: 15px;
            font-size: 1rem;
            font-weight: 500;
            color: #1a1a2e;
            cursor: pointer;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item:hover {
            background: rgba(106, 183, 216, 0.2);
            color: #f06292;
        }
        .sidebar-item.active {
            background: rgba(106, 183, 216, 0.3);
            color: #f06292;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            user-select: text;
        }
        .form-control:focus {
            outline: none;
            border-color: #f06292;
            box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
        }
        .form-control::placeholder {
            color: #999;
        }
        select.form-control {
            cursor: pointer;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #2e7d32, #4caf50);
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(76, 175, 80, 0.3);
        }
        .btn-danger {
            background: linear-gradient(45deg, #ff8a80, #f06292);
            color: white;
        }
        .btn-danger:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(255, 138, 128, 0.3);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            background: linear-gradient(135deg, #f06292, #6ab7d8);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-item:hover {
            transform: scale(1.03);
        }
        .stat-item.total {
            background: linear-gradient(135deg, #1976d2, #42a5f5);
        }
        .stat-item.charged {
            background: linear-gradient(135deg, #ff4500, #ffca28);
        }
        .stat-item.approved {
            background: linear-gradient(135deg, #2e7d32, #4caf50);
        }
        .stat-item.declined {
            background: linear-gradient(135deg, #d32f2f, #ef5350);
        }
        .stat-item.checked {
            background: linear-gradient(135deg, #7b1fa2, #ab47bc);
        }
        .stat-item .label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 6px;
        }
        .stat-item .value {
            font-size: 22px;
            font-weight: 700;
        }
        .result-column {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(249, 236, 236, 0.95), rgba(224, 246, 245, 0.95));
            backdrop-filter: blur(8px);
            z-index: 999;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .result-column.show {
            transform: translateX(0);
        }
        .result-column.slide-out {
            transform: translateX(-100%);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
        }
        .result-title {
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .result-content {
            flex: 1;
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            user-select: text;
        }
        .result-content::-webkit-scrollbar {
            width: 6px;
        }
        .result-content::-webkit-scrollbar-thumb {
            background: #6ab7d8;
            border-radius: 3px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .action-btn:hover {
            transform: scale(1.1);
        }
        .copy-btn { background: #2e7d32; }
        .trash-btn { background: #d32f2f; }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6ab7d8;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .card-count {
            font-size: 13px;
            color: #555;
            margin-top: 8px;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: #1a1a2e;
            font-size: 14px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .title-box h1 { font-size: 1.7rem; }
            .title-box p { font-size: 0.9rem; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); }
            .result-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .card { padding: 15px; }
            .sidebar { width: 250px; }
            .result-column { padding: 10px; }
            .result-content { max-height: 70vh; }
            .back-btn { padding: 8px 16px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <canvas id="particleCanvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></canvas>

    <div class="container" id="checkerContainer">
        <div class="header" id="header">
            <div class="title-box">
                <h1><i class="fas fa-credit-card"></i>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
                <p>𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</p>
            </div>
            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-item active" data-view="checkerhub"><i class="fas fa-home" style="color: #6ab7d8;"></i> CheckerHub</div>
            <div class="sidebar-item" data-view="charged"><i class="fas fa-bolt" style="color: #ffca28;"></i> Charged Cards</div>
            <div class="sidebar-item" data-view="approved"><i class="fas fa-check-circle" style="color: #4caf50;"></i> Approved Cards</div>
            <div class="sidebar-item" data-view="declined"><i class="fas fa-times-circle" style="color: #ef5350;"></i> Declined Cards</div>
            <div class="sidebar-item" data-view="logout"><i class="fas fa-sign-out-alt" style="color: #f06292;"></i> Logout</div>
        </div>

        <div class="card" id="inputSection">
            <div class="form-group">
                <label for="cards">Insert the cards here</label>
                <textarea id="cards" class="form-control" rows="7" placeholder="4147768578745265|04|26|168&#10;4242424242424242|12|2025|123"></textarea>
                <div class="card-count" id="card-count">0 valid cards detected</div>
            </div>
            <div class="form-group">
                <label for="gate">Select Gateway</label>
                <select id="gate" class="form-control">
                    <option value="gate/stripeauth.php">Stripe Auth</option>
                    <option value="gate/paypal1$.php">PayPal 1$</option>
                    <option value="gate/shopify1$.php">Shopify 1$</option>
                    <option value="gate/razorpay0.10$.php">Razorpay 0.10$</option>
                </select>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary btn-play" id="startBtn">
                    <i class="fas fa-play"></i> Start Check
                </button>
                <button class="btn btn-danger btn-stop" id="stopBtn" disabled>
                    <i class="fas fa-stop"></i> Stop
                </button>
            </div>
            <div class="loader" id="loader"></div>
        </div>

        <div class="card" id="statsSection">
            <div class="stats-grid">
                <div class="stat-item total">
                    <div class="label">Total</div>
                    <div class="value carregadas">0</div>
                </div>
                <div class="stat-item charged">
                    <div class="label">HIT|CHARGED</div>
                    <div class="value charged">0</div>
                </div>
                <div class="stat-item approved">
                    <div class="label">LIVE|APPROVED</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item declined">
                    <div class="label">DEAD|DECLINED</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item checked">
                    <div class="label">CHECKED</div>
                    <div class="value checked">0 / 0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="result-column hidden" id="resultColumn">
        <div class="header">
            <div class="title-box">
                <h1><i class="fas fa-credit-card"></i>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
                <p>𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</p>
            </div>
            <button class="back-btn" id="backBtn">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
        <div class="result-header">
            <h3 class="result-title" id="resultTitle"><i class="fas fa-bolt" style="color: #ffca28;"></i> Charged Cards</h3>
            <div class="action-buttons">
                <button class="action-btn copy-btn" id="copyResult"><i class="fas fa-copy"></i></button>
                <button class="action-btn trash-btn" id="clearResult"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <div id="resultContent" class="result-content"></div>
    </div>

    <footer id="footer">
        <p><strong>© SINCE 2025 𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 - All rights reserved</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            const sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            let isProcessing = false;
            let isStopping = false;
            let activeRequests = 0;
            let cardQueue = [];
            const MAX_CONCURRENT = 3;
            const MAX_RETRIES = 1;
            let abortControllers = [];
            let totalCards = 0;
            let chargedCards = JSON.parse(sessionStorage.getItem(`chargedCards-${sessionId}`) || '[]');
            let approvedCards = JSON.parse(sessionStorage.getItem(`approvedCards-${sessionId}`) || '[]');
            let declinedCards = JSON.parse(sessionStorage.getItem(`declinedCards-${sessionId}`) || '[]');
            let currentView = 'checkerhub';

            // Particle Animation
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

            // Card validation and counter
            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
                if (validCards.length > 0) {
                    $('.carregadas').text('0');
                    $('.charged').text('0');
                    $('.approved').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                    chargedCards = [];
                    approvedCards = [];
                    declinedCards = [];
                    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                    sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                    $('#resultColumn').addClass('hidden');
                }
            });

            // Sidebar toggle
            function toggleSidebar(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $('#sidebar').toggleClass('show');
            }

            $('#menuToggle').click(toggleSidebar);

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#menuToggle, #sidebar').length && $('#sidebar').hasClass('show')) {
                    $('#sidebar').removeClass('show');
                }
            });

            $('#sidebar').click(function(e) {
                e.stopPropagation();
            });

            // Sidebar item clicks
            $('.sidebar-item').click(function() {
                const view = $(this).data('view');
                if (view === 'logout') {
                    window.location.href = 'login.php?action=logout';
                } else {
                    switchView(view);
                }
            });

            // Back button
            $('#backBtn').click(function(e) {
                e.preventDefault();
                $('#resultColumn').addClass('slide-out');
                $('#checkerContainer').removeClass('hidden');
                setTimeout(() => {
                    $('#resultColumn').removeClass('slide-out').addClass('hidden');
                    $('#footer').removeClass('hidden');
                    switchView('checkerhub');
                }, 300);
            });

            // View switching
            function switchView(view) {
                currentView = view;
                $('.sidebar-item').removeClass('active');
                $(`.sidebar-item[data-view="${view}"]`).addClass('active');
                $('#sidebar').removeClass('show');

                if (view === 'checkerhub') {
                    $('#checkerContainer').removeClass('hidden');
                    $('#footer').removeClass('hidden');
                    $('#resultColumn').removeClass('show').addClass('hidden');
                } else {
                    $('#checkerContainer').addClass('hidden');
                    $('#footer').addClass('hidden');
                    $('#resultColumn').removeClass('hidden').addClass('show');
                    renderResult();
                }
            }

            function renderResult() {
                const viewConfig = {
                    charged: { title: 'Charged Cards', icon: 'fa-bolt', color: '#ffca28', data: chargedCards, clearable: true },
                    approved: { title: 'Approved Cards', icon: 'fa-check-circle', color: '#4caf50', data: approvedCards, clearable: false },
                    declined: { title: 'Declined Cards', icon: 'fa-times-circle', color: '#ef5350', data: declinedCards, clearable: true }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                $('#resultTitle').html(`<i class="fas ${config.icon}" style="color: ${config.color}"></i> ${config.title}`);
                $('#resultContent').empty();
                if (config.data.length === 0) {
                    $('#resultContent').append('<span style="color: #555;">No cards yet</span>');
                } else {
                    config.data.forEach(item => {
                        $('#resultContent').append(`<div class="card-data" style="color: ${config.color}; font-family: 'Inter', sans-serif;">${item}</div>`);
                    });
                }
            }

            $('#copyResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards },
                    approved: { title: 'Approved cards', data: approvedCards },
                    declined: { title: 'Declined cards', data: declinedCards }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                const text = config.data.join('\n');
                if (!text) {
                    Swal.fire({
                        title: 'Nothing to copy!',
                        text: `${config.title} list is empty`,
                        icon: 'info',
                        confirmButtonColor: '#6ab7d8'
                    });
                    return;
                }
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Swal.fire({
                    title: `Copied ${config.title}!`,
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            $('#clearResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards, counter: '.charged' },
                    declined: { title: 'Declined cards', data: declinedCards, counter: '.reprovadas' }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                Swal.fire({
                    title: `Clear ${config.title.toLowerCase()}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, clear!',
                    confirmButtonColor: '#f06292'
                }).then((result) => {
                    if (result.isConfirmed) {
                        config.data.length = 0;
                        sessionStorage.setItem(`${currentView}Cards-${sessionId}`, JSON.stringify(config.data));
                        $(config.counter).text('0');
                        $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);
                        renderResult();
                        Swal.fire('Cleared!', '', 'success');
                    }
                });
            });

            async function processCard(card, controller, retryCount = 0) {
                if (!isProcessing) return null;

                return new Promise((resolve) => {
                    const formData = new FormData();
                    let normalizedYear = card.exp_year;
                    if (normalizedYear.length === 2) {
                        normalizedYear = (parseInt(normalizedYear) < 50 ? '20' : '19') + normalizedYear;
                    }
                    formData.append('card[number]', card.number);
                    formData.append('card[exp_month]', card.exp_month);
                    formData.append('card[exp_year]', normalizedYear);
                    formData.append('card[cvc]', card.cvc);

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000,
                        signal: controller.signal,
                        success: function(response) {
                            const status = response.includes('CHARGED') ? 'CHARGED' : response.includes('APPROVED') ? 'APPROVED' : 'DECLINED';
                            resolve({
                                status: status,
                                response: response.trim(),
                                card: card,
                                displayCard: card.displayCard
                            });
                        },
                        error: function(xhr) {
                            if (xhr.statusText === 'abort') {
                                resolve(null);
                            } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                                setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 1000);
                            } else {
                                resolve({
                                    status: 'DECLINED',
                                    response: `DECLINED [Request failed: ${xhr.statusText} (HTTP ${xhr.status})] ${card.displayCard}`,
                                    card: card,
                                    displayCard: card.displayCard
                                });
                            }
                        }
                    });
                });
            }

            async function processCards() {
                if (isProcessing) {
                    Swal.fire({
                        title: 'Processing in progress',
                        text: 'Please wait until current process completes',
                        icon: 'warning',
                        confirmButtonColor: '#6ab7d8'
                    });
                    return;
                }

                const cardText = $('#cards').val().trim();
                const lines = cardText.split('\n').filter(line => line.trim());
                const validCards = lines
                    .map(line => line.trim())
                    .filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line))
                    .map(line => {
                        const [number, exp_month, exp_year, cvc] = line.split('|');
                        return { number, exp_month, exp_year, cvc, displayCard: `${number}|${exp_month}|${exp_year}|${cvc}` };
                    });

                if (validCards.length === 0) {
                    Swal.fire({
                        title: 'No valid cards!',
                        text: 'Please check your card format',
                        icon: 'error',
                        confirmButtonColor: '#6ab7d8'
                    });
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire({
                        title: 'Limit exceeded!',
                        text: 'Maximum 1000 cards allowed',
                        icon: 'error',
                        confirmButtonColor: '#6ab7d8'
                    });
                    return;
                }

                isProcessing = true;
                isStopping = false;
                activeRequests = 0;
                abortControllers = [];
                cardQueue = [...validCards];
                totalCards = validCards.length;
                chargedCards = [];
                approvedCards = [];
                declinedCards = [];
                sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                $('.carregadas').text(totalCards);
                $('.charged').text('0');
                $('.approved').text('0');
                $('.reprovadas').text('0');
                $('.checked').text(`0 / ${totalCards}`);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                $('#resultColumn').addClass('hidden');

                let requestIndex = 0;

                while (cardQueue.length > 0 && isProcessing) {
                    while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                        const card = cardQueue.shift();
                        activeRequests++;
                        const controller = new AbortController();
                        abortControllers.push(controller);

                        await new Promise(resolve => setTimeout(resolve, requestIndex * 200));
                        requestIndex++;

                        processCard(card, controller).then(result => {
                            if (result === null) return;

                            activeRequests--;
                            if (result.status === 'CHARGED') {
                                chargedCards.push(result.response);
                                sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                                $('.charged').text(chargedCards.length);
                            } else if (result.status === 'APPROVED') {
                                approvedCards.push(result.response);
                                sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                                $('.approved').text(approvedCards.length);
                            } else {
                                declinedCards.push(result.response);
                                sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                                $('.reprovadas').text(declinedCards.length);
                            }

                            $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);

                            if (currentView === result.status.toLowerCase()) {
                                renderResult();
                            }

                            if (chargedCards.length + approvedCards.length + declinedCards.length >= totalCards || !isProcessing) {
                                finishProcessing();
                            }
                        });
                    }
                    if (isProcessing) {
                        await new Promise(resolve => setTimeout(resolve, 5));
                    }
                }
            }

            function finishProcessing() {
                isProcessing = false;
                isStopping = false;
                activeRequests = 0;
                cardQueue = [];
                abortControllers = [];
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                $('#cards').val('');
                $('#card-count').text('0 valid cards detected');
                Swal.fire({
                    title: 'Processing complete!',
                    text: 'All cards have been checked. See the results by clicking the 3 lines in the corner.',
                    icon: 'success',
                    confirmButtonColor: '#6ab7d8'
                });
                if (currentView !== 'checkerhub') {
                    renderResult();
                }
            }

            $('#startBtn').click(processCards);

            $('#stopBtn').click(function() {
                if (!isProcessing || isStopping) return;

                isProcessing = false;
                isStopping = true;
                cardQueue = [];
                abortControllers.forEach(controller => controller.abort());
                abortControllers = [];
                activeRequests = 0;
                $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire({
                    title: 'Stopped!',
                    text: 'Processing has been stopped',
                    icon: 'warning',
                    confirmButtonColor: '#6ab7d8'
                });
                if (currentView !== 'checkerhub') {
                    renderResult();
                }
            });

            $('#gate').change(function() {
                const selected = $(this).val();
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php') && !selected.includes('shopify1$.php') && !selected.includes('razorpay0.10$.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth, PayPal 1$, Shopify 1$, and Razorpay 0.10$ are currently available',
                        icon: 'info',
                        confirmButtonColor: '#6ab7d8'
                    });
                    $(this).val('gate/stripeauth.php');
                }
            });
        });
    </script>
</body>
</html>
