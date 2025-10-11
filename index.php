<?php
session_start();

// Load environment variables
require 'vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Location: login.php');
    exit;
}

// Database connection (optional, for future result storage)
try {
    $dbUrl = parse_url($_ENV['DATABASE_URL']);
    $pdo = new PDO(
        "pgsql:host={$dbUrl['host']};port={$dbUrl['port']};dbname=" . ltrim($dbUrl['path'], '/'),
        $dbUrl['user'],
        $dbUrl['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create results table if it doesn't exist (for future use)
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
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Continue without database for now
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
            user-select: none; /* Prevent text selection for non-card elements */
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #0288d1, #4fc3f7, #f06292, #bbdefb);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
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
        @keyframes slideOutLeft {
            0% { transform: translateX(0); opacity: 1; }
            100% { transform: translateX(-100%); opacity: 0; }
        }
        @keyframes slideInRight {
            0% { transform: translateX(100%); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
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
            background: linear-gradient(45deg, #ff6f00, #ffca28);
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
            color: #ffca28;
            font-size: 1.6rem;
            padding: 10px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1008;
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
            z-index: 1008;
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
            background: linear-gradient(135deg, rgba(227, 242, 253, 0.95), rgba(187, 222, 251, 0.95));
            backdrop-filter: blur(8px);
            box-shadow: -4px 0 10px rgba(0, 0, 0, 0.1);
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1009;
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
            color: #333;
            cursor: pointer;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item:hover {
            background: rgba(79, 195, 247, 0.2);
            color: #f06292;
        }
        .sidebar-item.active {
            background: rgba(79, 195, 247, 0.3);
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
            box-shadow: 0 8px 16px rgba(46, 125, 50, 0.3);
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
            background: linear-gradient(135deg, #f06292, #4fc3f7);
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
            background: linear-gradient(135deg, #ff4500, #ffb300);
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
            background: linear-gradient(135deg, rgba(227, 242, 253, 0.95), rgba(187, 222, 251, 0.95));
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
        .container.slide-in {
            transform: translateX(0);
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
            color: #333;
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
            background: #f06292;
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
            border-top: 3px solid #f06292;
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
            color: white;
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
    <!-- Particle Animation -->
    <div class="particles" id="particles"></div>

    <!-- Main Checker UI -->
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
            <div class="sidebar-item active" data-view="checkerhub"><i class="fas fa-home" style="color: #4fc3f7;"></i> CheckerHub</div>
            <div class="sidebar-item" data-view="charged"><i class="fas fa-bolt" style="color: #ffca28;"></i> Charged Cards</div>
            <div class="sidebar-item" data-view="approved"><i class="fas fa-check-circle" style="color: #2e7d32;"></i> Approved Cards</div>
            <div class="sidebar-item" data-view="declined"><i class="fas fa-times-circle" style="color: #d32f2f;"></i> Declined Cards</div>
            <div class="sidebar-item" data-view="logout"><i class="fas fa-sign-out-alt" style="color: #d32f2f;"></i> Logout</div>
        </div>

        <!-- Input Section -->
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

        <!-- Stats -->
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

    <!-- Results -->
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
            // Session-based storage for user isolation
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
            function createParticles() {
                const particlesContainer = $('#particles');
                for (let i = 0; i < 20; i++) {
                    const particle = $('<div class="particle"></div>');
                    particle.css({
                        left: Math.random() * 100 + '%',
                        animationDuration: Math.random() * 10 + 10 + 's',
                        animationDelay: Math.random() * 5 + 's',
                        borderBottomColor: ['#0288d1', '#4fc3f7', '#f06292'][Math.floor(Math.random() * 3)]
                    });
                    particlesContainer.append(particle);
                }
            }
            createParticles();

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
                console.log(`Sidebar state: ${$('#sidebar').hasClass('show') ? 'Open' : 'Closed'}`);
            }

            $('#menuToggle').click(function(e) {
                console.log('Click detected on menuToggle');
                toggleSidebar(e);
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#menuToggle, #sidebar').length && $('#sidebar').hasClass('show')) {
                    console.log('Closing sidebar due to outside click');
                    $('#sidebar').removeClass('show');
                }
            });

            $('#sidebar').click(function(e) {
                e.stopPropagation();
                console.log('Click inside sidebar, preventing close');
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
                console.log('Click detected on backBtn, switching to checkerhub');
                $('#resultColumn').addClass('slide-out');
                $('#checkerContainer').removeClass('hidden').addClass('slide-in');
                setTimeout(() => {
                    $('#resultColumn').removeClass('slide-out').addClass('hidden');
                    $('#checkerContainer').removeClass('slide-in');
                    $('#footer').removeClass('hidden');
                    console.log('Animation complete, switched to checkerhub');
                    switchView('checkerhub');
                }, 300);
            });

            // View switching
            function switchView(view) {
                console.log(`Switching to view: ${view}`);
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
                    approved: { title: 'Approved Cards', icon: 'fa-check-circle', color: '#2e7d32', data: approvedCards, clearable: false },
                    declined: { title: 'Declined Cards', icon: 'fa-times-circle', color: '#d32f2f', data: declinedCards, clearable: true }
                };
                if (!viewConfig[currentView]) return;
                const config = viewConfig[currentView];
                $('#resultTitle').html(`<i class="fas ${config.icon}" style="color: ${config.color}"></i> ${config.title}`);
                $('#resultContent').empty();
                if (config.data.length === 0) {
                    $('#resultContent').append('<span style="color: #555;">No cards yet</span>');
                } else {
                    config.data.forEach(item => {
                        $('#resultContent').append(`<div class="card-data" style="color: ${config.color}; font-family: 'Inter', sans-serif;">${item}</div>`);
                    });
                }
                $('#resultColumn').removeClass('hidden').addClass('show');
                $('#checkerContainer').addClass('hidden');
                $('#footer').addClass('hidden');
                $('#clearResult').toggle(config.clearable);
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
                        confirmButtonColor: '#f06292'
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
                    confirmButtonColor: '#d32f2f'
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
                if (!isProcessing) {
                    console.log(`Skipping card ${card.displayCard} due to stop`);
                    return null;
                }

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

                    console.log(`Sending card ${card.displayCard} to ${$('#gate').val()}`);

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000,
                        signal: controller.signal,
                        success: function(response) {
                            console.log(`Success for ${card.displayCard}: ${response}`);
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
                                console.log(`Aborted card ${card.displayCard}`);
                                resolve(null);
                            } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                                console.warn(`Retrying card ${card.displayCard} (Attempt ${retryCount + 2}) due to error: ${xhr.statusText} (${xhr.status})`);
                                setTimeout(() => {
                                    processCard(card, controller, retryCount + 1).then(resolve);
                                }, 1000);
                            } else {
                                const errorMsg = xhr.responseText ? xhr.responseText.substring(0, 100) : 'Request failed';
                                console.error(`Failed card ${card.displayCard}: ${xhr.statusText} (HTTP ${xhr.status}) - ${errorMsg}`);
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
                    console.warn('Processing already in progress');
                    Swal.fire({
                        title: 'Processing in progress',
                        text: 'Please wait until current process completes',
                        icon: 'warning',
                        confirmButtonColor: '#f06292'
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
                        confirmButtonColor: '#f06292'
                    });
                    console.error('No valid cards provided');
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire({
                        title: 'Limit exceeded!',
                        text: 'Maximum 1000 cards allowed',
                        icon: 'error',
                        confirmButtonColor: '#f06292'
                    });
                    console.error('Card limit exceeded');
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
                console.log(`Starting processing for ${totalCards} cards`);
                $('#resultColumn').addClass('hidden');

                let requestIndex = 0;

                while (cardQueue.length > 0 && isProcessing) {
                    while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                        const card = cardQueue.shift();
                        if (!card) break;
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
                            console.log(`Completed ${chargedCards.length + approvedCards.length + declinedCards.length}/${totalCards}: ${result.response}`);

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
                    confirmButtonColor: '#f06292'
                });
                console.log('Processing completed');
                if (currentView !== 'checkerhub') {
                    renderResult();
                }
            }

            $('#startBtn').click(() => {
                console.log('Start Check clicked');
                processCards();
            });

            $('#stopBtn').click(() => {
                if (!isProcessing || isStopping) {
                    console.warn('Stop clicked but not processing or already stopping');
                    return;
                }

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
                    confirmButtonColor: '#f06292',
                    allowOutsideClick: false
                });
                console.log('Processing stopped');
                if (currentView !== 'checkerhub') {
                    renderResult();
                }
            });

            $('#gate').change(function() {
                const selected = $(this).val();
                console.log(`Gateway changed to: ${selected}`);
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php') && !selected.includes('shopify1$.php') && !selected.includes('razorpay0.10$.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth, PayPal 1$, Shopify 1$, and Razorpay 0.10$ are currently available',
                        icon: 'info',
                        confirmButtonColor: '#f06292'
                    });
                    $(this).val('gate/stripeauth.php');
                    console.log('Reverted to stripeauth.php');
                }
            });
        });
    </script>
</body>
</html>
