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
    header('Location: https://cardxchk.onrender.com/login.php');
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial;
            background: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
            margin: 0;
            overflow-x: hidden;
            color: #1e293b;
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1rem;
        }
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .btn { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn:hover:not(:disabled) { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .sidebar { transition: transform 0.3s ease; }
        .sidebar.show { transform: translateX(0); }
        .result-column { transition: transform 0.3s ease; }
        .result-column.show { transform: translateX(0); }
        .result-column.slide-out { transform: translateX(-100%); }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ec4899;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .result-content::-webkit-scrollbar { width: 6px; }
        .result-content::-webkit-scrollbar-thumb { background: #ec4899; border-radius: 3px; }
    </style>
</head>
<body>
    <canvas id="particleCanvas"></canvas>

    <div class="container mx-auto px-4 py-6 max-w-5xl" id="checkerContainer">
        <header class="flex justify-between items-center mb-6 glass p-4 rounded-xl">
            <div class="text-white">
                <h1 class="text-2xl md:text-3xl font-bold"><i class="fas fa-credit-card mr-2"></i>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
                <p class="text-sm opacity-80">𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</p>
            </div>
            <div class="menu-toggle cursor-pointer text-white text-2xl p-2 hover:scale-110 transition-transform" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>
        </header>

        <div class="sidebar fixed top-0 right-0 h-full w-64 md:w-80 glass p-4 transform translate-x-full" id="sidebar">
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium active" data-view="checkerhub">
                <i class="fas fa-home text-indigo-500"></i> CheckerHub
            </div>
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium" data-view="charged">
                <i class="fas fa-bolt text-yellow-500"></i> Charged Cards
            </div>
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium" data-view="approved">
                <i class="fas fa-check-circle text-green-500"></i> Approved Cards
            </div>
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium" data-view="ccn">
                <i class="fas fa-exclamation-circle text-orange-500"></i> CCN Cards
            </div>
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium" data-view="declined">
                <i class="fas fa-times-circle text-red-500"></i> Declined Cards
            </div>
            <div class="sidebar-item flex items-center gap-2 p-3 rounded-lg cursor-pointer hover:bg-indigo-100/50 active:bg-indigo-200/50 text-indigo-900 font-medium" data-view="logout">
                <i class="fas fa-sign-out-alt text-pink-500"></i> Logout
            </div>
        </div>

        <div class="card glass p-6 mb-6">
            <div class="mb-6">
                <label for="cards" class="block text-sm font-semibold text-gray-700 mb-2">Insert the cards here</label>
                <textarea id="cards" class="w-full p-4 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-200 text-sm" rows="7" placeholder="4147768578745265|04|26|168&#10;4242424242424242|12|2025|123"></textarea>
                <div class="card-count text-xs text-gray-500 mt-2" id="card-count">0 valid cards detected</div>
            </div>
            <div class="mb-6">
                <label for="gate" class="block text-sm font-semibold text-gray-700 mb-2">Select Gateway</label>
                <select id="gate" class="w-full p-4 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-200 text-sm cursor-pointer">
                    <option value="gate/stripeauth.php">Stripe Auth</option>
                    <option value="gate/stripe1$.php">Stripe 1$</option>
                    <option value="gate/paypal1$.php">PayPal 1$</option>
                    <option value="gate/shopify1$.php">Shopify 1$</option>
                    <option value="gate/razorpay0.10$.php">Razorpay 0.10$</option>
                </select>
            </div>
            <div class="flex justify-center gap-4">
                <button class="btn bg-gradient-to-r from-green-600 to-green-400 text-white px-6 py-3 rounded-lg font-semibold uppercase tracking-wide flex items-center gap-2" id="startBtn">
                    <i class="fas fa-play"></i> Start Check
                </button>
                <button class="btn bg-gradient-to-r from-red-600 to-pink-500 text-white px-6 py-3 rounded-lg font-semibold uppercase tracking-wide flex items-center gap-2" id="stopBtn" disabled>
                    <i class="fas fa-stop"></i> Stop
                </button>
            </div>
            <div class="loader" id="loader"></div>
        </div>

        <div class="card glass p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="stat-item bg-gradient-to-br from-blue-600 to-blue-400 text-white p-4 rounded-lg text-center">
                    <div class="text-xs opacity-90">Total</div>
                    <div class="text-xl font-bold carregadas">0</div>
                </div>
                <div class="stat-item bg-gradient-to-br from-yellow-600 to-yellow-400 text-white p-4 rounded-lg text-center">
                    <div class="text-xs opacity-90">HIT|CHARGED</div>
                    <div class="text-xl font-bold charged">0</div>
                </div>
                <div class="stat-item bg-gradient-to-br from-green-600 to-green-400 text-white p-4 rounded-lg text-center">
                    <div class="text-xs opacity-90">LIVE|APPROVED</div>
                    <div class="text-xl font-bold approved">0</div>
                </div>
                <div class="stat-item bg-gradient-to-br from-orange-600 to-orange-400 text-white p-4 rounded-lg text-center">
                    <div class="text-xs opacity-90">CCN</div>
                    <div class="text-xl font-bold ccn">0</div>
                </div>
                <div class="stat-item bg-gradient-to-br from-red-600 to-red-400 text-white p-4 rounded-lg text-center">
                    <div class="text-xs opacity-90">DEAD|DECLINED</div>
                    <div class="text-xl font-bold reprovadas">0</div>
                </div>
                <div class="stat-item bg-gradient-to-br from-purple-600 to-purple-400 text-white p-4 rounded-lg text-center md:col-span-5">
                    <div class="text-xs opacity-90">CHECKED</div>
                    <div class="text-xl font-bold checked">0 / 0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="result-column fixed inset-0 glass p-4 flex flex-col gap-4 hidden" id="resultColumn">
        <div class="flex justify-between items-center glass p-4 rounded-xl">
            <div class="text-white">
                <h1 class="text-xl md:text-2xl font-bold"><i class="fas fa-credit-card mr-2"></i>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
                <p class="text-sm opacity-80">𝐓𝐇𝐄 𝐍𝐄𝐖 𝐄𝐑𝐀 𝐁𝐄𝐆𝐈𝐍𝐒</p>
            </div>
            <button class="back-btn bg-gradient-to-r from-red-600 to-pink-500 text-white px-4 py-2 rounded-lg flex items-center gap-2 font-semibold" id="backBtn">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
        <div class="flex justify-between items-center glass p-4 rounded-xl">
            <h3 class="result-title font-semibold text-lg flex items-center gap-2" id="resultTitle">
                <i class="fas fa-bolt text-yellow-500"></i> Charged Cards
            </h3>
            <div class="flex gap-2">
                <button class="action-btn bg-green-600 text-white px-3 py-2 rounded-lg hover:scale-110 transition-transform" id="copyResult">
                    <i class="fas fa-copy"></i>
                </button>
                <button class="action-btn bg-red-600 text-white px-3 py-2 rounded-lg hover:scale-110 transition-transform" id="clearResult">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div id="resultContent" class="result-content flex-1 overflow-y-auto p-4 bg-white rounded-lg shadow-sm text-sm font-mono"></div>
    </div>

    <footer class="text-center py-4 text-white text-sm">
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
            let ccnCards = JSON.parse(sessionStorage.getItem(`ccnCards-${sessionId}`) || '[]');
            let declinedCards = JSON.parse(sessionStorage.getItem(`declinedCards-${sessionId}`) || '[]');
            let currentView = 'checkerhub';

            // Particle Animation
            const canvas = document.getElementById('particleCanvas');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            let particles = [];
            const particleCount = 50;

            class Particle {
                constructor() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 10 + 5;
                    this.speedX = Math.random() * 2 - 1;
                    this.speedY = Math.random() * 2 - 1;
                    this.color = ['#4f46e5', '#ec4899', '#10b981', '#f59e0b'][Math.floor(Math.random() * 4)];
                    this.opacity = Math.random() * 0.5 + 0.3;
                }
                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;
                    if (this.x < 0 || this.x > canvas.width) this.speedX *= -0.9;
                    if (this.y < 0 || this.y > canvas.height) this.speedY *= -0.9;
                    this.opacity -= 0.005;
                    if (this.opacity <= 0) {
                        this.x = Math.random() * canvas.width;
                        this.y = Math.random() * canvas.height;
                        this.opacity = Math.random() * 0.5 + 0.3;
                    }
                }
                draw() {
                    ctx.globalAlpha = this.opacity;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = this.color;
                    ctx.fill();
                    ctx.globalAlpha = 1;
                }
            }

            function init() {
                particles = [];
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle());
                }
            }

            function animate() {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                particles.forEach(particle => {
                    particle.update();
                    particle.draw();
                });
                requestAnimationFrame(animate);
            }

            init();
            animate();

            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                init();
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
                    $('.ccn').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                    chargedCards = [];
                    approvedCards = [];
                    ccnCards = [];
                    declinedCards = [];
                    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                    sessionStorage.setItem(`ccnCards-${sessionId}`, JSON.stringify(ccnCards));
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
                    $('footer').removeClass('hidden');
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
                    $('footer').removeClass('hidden');
                    $('#resultColumn').removeClass('show').addClass('hidden');
                } else {
                    $('#checkerContainer').addClass('hidden');
                    $('footer').addClass('hidden');
                    $('#resultColumn').removeClass('hidden').addClass('show');
                    renderResult();
                }
            }

            function renderResult() {
                const viewConfig = {
                    charged: { title: 'Charged Cards', icon: 'fa-bolt', color: '#f59e0b', data: chargedCards, clearable: true },
                    approved: { title: 'Approved Cards', icon: 'fa-check-circle', color: '#10b981', data: approvedCards, clearable: false },
                    ccn: { title: 'CCN Cards', icon: 'fa-exclamation-circle', color: '#f97316', data: ccnCards, clearable: true },
                    declined: { title: 'Declined Cards', icon: 'fa-times-circle', color: '#ef4444', data: declinedCards, clearable: true }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                $('#resultTitle').html(`<i class="fas ${config.icon}" style="color: ${config.color}"></i> ${config.title}`);
                $('#resultContent').empty();
                if (config.data.length === 0) {
                    $('#resultContent').append('<span style="color: #6b7280;">No cards yet</span>');
                } else {
                    config.data.forEach(item => {
                        $('#resultContent').append(`<div class="card-data text-${config.color.replace('#', '')}">${item}</div>`);
                    });
                }
            }

            $('#copyResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards },
                    approved: { title: 'Approved cards', data: approvedCards },
                    ccn: { title: 'CCN cards', data: ccnCards },
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
                        confirmButtonColor: '#ec4899'
                    });
                    return;
                }
                navigator.clipboard.writeText(text).then(() => {
                    Swal.fire({
                        title: `Copied ${config.title}!`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            });

            $('#clearResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards, counter: '.charged' },
                    ccn: { title: 'CCN cards', data: ccnCards, counter: '.ccn' },
                    declined: { title: 'Declined cards', data: declinedCards, counter: '.reprovadas' }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                Swal.fire({
                    title: `Clear ${config.title.toLowerCase()}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, clear!',
                    confirmButtonColor: '#ec4899'
                }).then((result) => {
                    if (result.isConfirmed) {
                        config.data.length = 0;
                        sessionStorage.setItem(`${currentView}Cards-${sessionId}`, JSON.stringify(config.data));
                        $(config.counter).text('0');
                        $('.checked').text(`${chargedCards.length + approvedCards.length + ccnCards.length + declinedCards.length} / ${totalCards}`);
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
                            let status = 'DECLINED';
                            if (response.includes('CHARGED')) status = 'CHARGED';
                            else if (response.includes('APPROVED')) status = 'APPROVED';
                            else if (response.includes('CCN')) status = 'CCN';
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
                        confirmButtonColor: '#ec4899'
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
                        confirmButtonColor: '#ec4899'
                    });
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire({
                        title: 'Limit exceeded!',
                        text: 'Maximum 1000 cards allowed',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
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
                ccnCards = [];
                declinedCards = [];
                sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                sessionStorage.setItem(`ccnCards-${sessionId}`, JSON.stringify(ccnCards));
                sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                $('.carregadas').text(totalCards);
                $('.charged').text('0');
                $('.approved').text('0');
                $('.ccn').text('0');
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
                            } else if (result.status === 'CCN') {
                                ccnCards.push(result.response);
                                sessionStorage.setItem(`ccnCards-${sessionId}`, JSON.stringify(ccnCards));
                                $('.ccn').text(ccnCards.length);
                            } else {
                                declinedCards.push(result.response);
                                sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                                $('.reprovadas').text(declinedCards.length);
                            }

                            $('.checked').text(`${chargedCards.length + approvedCards.length + ccnCards.length + declinedCards.length} / ${totalCards}`);

                            if (currentView === result.status.toLowerCase()) {
                                renderResult();
                            }

                            if (chargedCards.length + approvedCards.length + ccnCards.length + declinedCards.length >= totalCards || !isProcessing) {
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
                    text: 'All cards have been checked. See the results in the sidebar.',
                    icon: 'success',
                    confirmButtonColor: '#ec4899'
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
                $('.checked').text(`${chargedCards.length + approvedCards.length + ccnCards.length + declinedCards.length} / ${totalCards}`);
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire({
                    title: 'Stopped!',
                    text: 'Processing has been stopped',
                    icon: 'warning',
                    confirmButtonColor: '#ec4899'
                });
                if (currentView !== 'checkerhub') {
                    renderResult();
                }
            });

            $('#gate').change(function() {
                const selected = $(this).val();
                const validGates = ['gate/stripeauth.php', 'gate/stripe1$.php', 'gate/paypal1$.php', 'gate/shopify1$.php', 'gate/razorpay0.10$.php'];
                if (!validGates.includes(selected)) {
                    Swal.fire({
                        title: 'Invalid gateway',
                        text: 'Please select a valid gateway',
                        icon: 'info',
                        confirmButtonColor: '#ec4899'
                    });
                    $(this).val('gate/stripeauth.php');
                }
            });
        });
    </script>
</body>
</html>
