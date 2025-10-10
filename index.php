<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CardX CHK - Neon Pulse Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --color-dark-bg: #0a001f; /* Dark purple-tinted background */
            --color-card-bg: rgba(15, 15, 40, 0.9);
            --color-text-light: #e0e0ff;
            --color-text-muted: #7a7aaf;
            --color-primary: #ff69b4; /* Hot Pink */
            --color-secondary: #9b30ff; /* Vivid Purple */
            --color-blue: #1e90ff; /* Blue */
            --color-neon: #00ffff; /* Neon Cyan */
            --color-yellow: #ffff00; /* Yellow */
            --color-success: #00ff7f; /* Bright Green */
            --color-danger: #dc143c; /* Red */
            --neon-glow-flowing: 0 0 6px #1e90ff, 0 0 12px rgba(30, 144, 255, 0.3), 0 0 18px rgba(30, 144, 255, 0.2);
            --neon-glow-purple: 0 0 6px #9b30ff, 0 0 12px rgba(155, 48, 255, 0.3), 0 0 18px rgba(155, 48, 255, 0.2);
            --border-radius: 24px; /* Highly rounded corners */
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-dark-bg);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: var(--color-text-light);
            overflow-y: auto;
            position: relative;
            user-select: none; /* Prevent text selection */
        }
        /* Enhanced Background Animation */
        #arrow-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        .arrow {
            position: absolute;
            font-size: calc(12px + 6px * var(--random-size));
            text-shadow: var(--neon-glow-flowing);
            animation: flowArrow linear infinite;
            opacity: calc(0.4 + 0.4 * var(--random-opacity));
        }
        @keyframes flowArrow {
            0% { transform: translate(-50vw, -50vh) rotate(45deg); }
            100% { transform: translate(50vw, 50vh) rotate(45deg); }
        }
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr;
            min-height: calc(100vh - 80px);
        }
        .header {
            grid-column: 1 / 2;
            background: rgba(10, 10, 30, 0.95);
            padding: 15px 30px;
            box-shadow: var(--neon-glow-flowing);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }
        .header::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        @keyframes neonBorder {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            font-weight: 900;
            color: var(--color-neon);
            text-shadow: 0 0 6px var(--color-neon);
            margin: 0;
        }
        .openbtn {
            font-size: 22px;
            cursor: pointer;
            color: var(--color-neon);
            position: absolute;
            top: 15px;
            right: 20px;
            transition: all 0.3s ease;
            text-shadow: var(--neon-glow-purple);
            background: rgba(10, 10, 30, 0.7);
            padding: 5px 10px;
            border-radius: var(--border-radius);
            box-shadow: var(--neon-glow-purple);
        }
        .openbtn::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-secondary), var(--color-primary), var(--color-secondary));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        .openbtn:hover {
            color: var(--color-yellow);
        }
        .sidebar {
            height: 100%;
            width: 0;
            position: fixed;
            z-index: 10;
            top: 0;
            right: 0;
            background: transparent; /* Transparent background */
            overflow-x: hidden;
            transition: width 0.4s ease;
            padding-top: 60px;
        }
        .sidebar .closebtn {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 34px;
            color: var(--color-text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .sidebar .closebtn:hover {
            color: var(--color-secondary);
        }
        .nav-item {
            padding: 14px 25px;
            font-size: 14px;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            border-radius: var(--border-radius);
        }
        .nav-item i { margin-right: 12px; }
        .nav-item:hover {
            background: rgba(155, 48, 255, 0.15);
            color: var(--color-neon);
            text-shadow: var(--neon-glow-flowing);
        }
        .nav-item.active {
            background: rgba(255, 105, 180, 0.2);
            color: var(--color-primary);
            text-shadow: var(--neon-glow-flowing);
        }
        .nav-label {
            padding: 12px 25px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1.2px;
            background: transparent;
        }
        .nav-label.core-functions {
            color: var(--color-primary); /* Hot Pink for Core Functions */
        }
        .nav-label.results-logs {
            color: var(--color-text-muted); /* Muted gray for Results Logs */
        }
        .main-content {
            grid-column: 1 / 2;
            grid-row: 2 / 3;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 30px;
            padding-bottom: 80px;
        }
        .card-panel {
            background: var(--color-card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--neon-glow-flowing);
        }
        .card-panel::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        .input-controls-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .status-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
            padding: 12px;
        }
        .stat-item {
            background: rgba(10, 10, 30, 0.9);
            padding: 16px;
            border-radius: var(--border-radius);
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--neon-glow-flowing);
            min-height: 100px;
        }
        .stat-item::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        .stat-item .icon {
            font-size: 1.5rem;
            color: var(--color-text-muted);
            margin-bottom: 8px;
        }
        .stat-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--color-text-muted);
            margin-bottom: 6px;
            letter-spacing: 1.2px;
        }
        .stat-item .value {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--color-blue);
        }
        .stat-item:nth-child(2) .value { color: var(--color-success); text-shadow: 0 0 6px var(--color-success); }
        .stat-item:nth-child(3) .value { color: var(--color-primary); text-shadow: 0 0 6px var(--color-primary); }
        .stat-item:nth-child(4) .value { color: var(--color-danger); text-shadow: 0 0 6px var(--color-danger); }
        .stat-item:nth-child(5) .value { color: var(--color-yellow); text-shadow: 0 0 6px var(--color-yellow); }
        .form-control {
            background: rgba(10, 10, 30, 0.7);
            border-radius: var(--border-radius);
            color: var(--color-text-light);
            padding: 12px;
            font-size: 13px;
            font-weight: 400;
            transition: all 0.3s;
            resize: none;
            width: 100%;
            box-shadow: var(--neon-glow-flowing);
        }
        .form-control:focus {
            box-shadow: 0 0 15px rgba(30, 144, 255, 0.5);
            outline: none;
        }
        #cards {
            min-height: 220px;
            position: relative;
        }
        #cards::before, #gate::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-neon);
            text-shadow: var(--neon-glow-flowing);
        }
        .card-count {
            font-size: 12px;
            color: var(--color-text-muted);
            margin-top: 8px;
            font-weight: 400;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }
        .btn {
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn:hover::before { left: 100%; }
        .btn-primary {
            background: linear-gradient(45deg, var(--color-primary), var(--color-blue));
            color: var(--color-dark-bg);
            box-shadow: var(--neon-glow-flowing);
        }
        .btn-primary:hover:not(:disabled) {
            box-shadow: 0 0 15px rgba(30, 144, 255, 0.5);
        }
        .btn-danger {
            background: linear-gradient(45deg, var(--color-danger), #8b0000);
            color: var(--color-text-light);
            box-shadow: 0 0 8px rgba(220, 20, 60, 0.5);
        }
        .btn-danger:hover:not(:disabled) {
            box-shadow: 0 0 12px rgba(220, 20, 60, 0.7);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }
        .loader {
            border: 4px solid rgba(30, 144, 255, 0.3);
            border-top: 4px solid var(--color-blue);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .log-viewer-container {
            flex-grow: 1;
            min-height: 300px;
        }
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            box-shadow: var(--neon-glow-flowing);
            margin-bottom: 15px;
        }
        .log-header::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        .log-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--color-primary);
            text-shadow: var(--neon-glow-flowing);
        }
        .log-content {
            font-family: 'Consolas', monospace;
            font-size: 12px;
            font-weight: 400;
            color: var(--color-text-light);
            white-space: pre-wrap;
            max-height: 450px;
            overflow-y: auto;
        }
        .log-content::-webkit-scrollbar {
            width: 8px;
        }
        .log-content::-webkit-scrollbar-thumb {
            background: var(--color-neon);
            border-radius: var(--border-radius);
        }
        .action-btn {
            background: rgba(10, 10, 30, 0.7);
            color: var(--color-text-light);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            font-weight: 600;
            box-shadow: var(--neon-glow-flowing);
        }
        .action-btn::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-blue), var(--color-neon), var(--color-blue));
            z-index: -1;
            filter: blur(4px);
            animation: neonBorder 2s ease infinite;
        }
        .action-btn:hover {
            background: rgba(155, 48, 255, 0.2);
            box-shadow: 0 0 15px rgba(30, 144, 255, 0.5);
        }
        .log-success { color: #00ff7f; text-shadow: 0 0 10px #00ff7f; }
        .log-charged { color: #00ff7f; text-shadow: 0 0 10px #00ff7f; }
        .log-danger { color: #ffffff; text-shadow: 0 0 5px #ffffff; }
        .view-section.hidden { display: none; }
        @media (max-width: 900px) {
            .input-controls-grid {
                grid-template-columns: 1fr;
                margin-bottom: 30px;
            }
            .control-panel { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .header h1 { font-size: 1.6rem; }
            .main-content { padding: 15px; }
            .status-bar {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 10px;
            }
            .stat-item { padding: 10px; min-height: 80px; }
            .stat-item .value { font-size: 1.8rem; }
            .sidebar { width: 250px; }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 1.3rem; }
            .openbtn { font-size: 20px; }
            .stat-item .value { font-size: 1.5rem; }
            .btn { padding: 10px; font-size: 13px; }
            .form-control { font-size: 12px; }
        }
    </style>
</head>
<body oncontextmenu="return false;">
    <!-- Enhanced Background Animation -->
    <div id="arrow-animation"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> CardX CHK</h1>
            <div class="openbtn" onclick="openNav()"><i class="fas fa-ellipsis-v"></i></div>
        </div>

        <!-- Sidebar Menu -->
        <div id="mySidebar" class="sidebar">
            <span class="closebtn" onclick="closeNav()">&times;</span>
            <div class="nav-label core-functions">Core Functions</div>
            <div class="nav-item active" data-view="process" onclick="switchView('process'); closeNav()"><i class="fas fa-play-circle"></i> Checker Hub</div>
            <div class="nav-label results-logs">Results Logs</div>
            <div class="nav-item" data-view="charged" onclick="switchView('charged'); closeNav()"><i class="fas fa-bolt"></i> Charged (<span class="charged">0</span>)</div>
            <div class="nav-item" data-view="approved" onclick="switchView('approved'); closeNav()"><i class="fas fa-check-circle"></i> Approved (<span class="approved">0</span>)</div>
            <div class="nav-item" data-view="declined" onclick="switchView('declined'); closeNav()"><i class="fas fa-times-circle"></i> Declined (<span class="reprovadas">0</span>)</div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Process View -->
            <div id="view-process" class="view-section">
                <div class="input-controls-grid">
                    <!-- Input Panel -->
                    <div class="card-panel input-panel">
                        <label for="cards"><i class="fas fa-keyboard"></i> Card List (card|MM|YY or YYYY|CVV)</label>
                        <textarea id="cards" class="form-control" rows="6" placeholder="4147768578745265|04|26|168&#10;4242424242424242|12|2025|123"></textarea>
                        <div class="card-count" id="card-count">0 valid cards detected</div>
                    </div>
                    <!-- Control Panel -->
                    <div class="card-panel control-panel">
                        <div class="form-group">
                            <label for="gate"><i class="fas fa-network-wired"></i> Select Gateway</label>
                            <select id="gate" class="form-control">
                                <option value="gate/stripeauth.php">Stripe Auth</option>
                                <option value="gate/paypal1$.php">PayPal 1$</option>
                                <option value="gate/paypal.php" disabled>PayPal (Coming Soon)</option>
                                <option value="gate/razorpay.php" disabled>Razorpay (Coming Soon)</option>
                                <option value="gate/shopify.php" disabled>Shopify (Coming Soon)</option>
                            </select>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary btn-play" id="startBtn"><i class="fas fa-play"></i> Start Check</button>
                            <button class="btn btn-danger btn-stop" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                        </div>
                        <div class="loader" id="loader"></div>
                    </div>
                </div>

                <!-- Status Bar -->
                <div class="status-bar card-panel">
                    <div class="stat-item">
                        <div class="icon"><i class="fas fa-list"></i></div>
                        <div class="label">Total</div>
                        <div class="value carregadas">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="icon"><i class="fas fa-bolt"></i></div>
                        <div class="label">Charged</div>
                        <div class="value charged">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                        <div class="label">Approved</div>
                        <div class="value approved">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                        <div class="label">Declined</div>
                        <div class="value reprovadas">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="icon"><i class="fas fa-tasks"></i></div>
                        <div class="label">Checked</div>
                        <div class="value checked">0 / 0</div>
                    </div>
                </div>
            </div>

            <!-- Log Viewer -->
            <div id="view-log" class="view-section hidden">
                <div class="card-panel log-viewer-container">
                    <div class="log-header">
                        <h3 class="log-title" id="dynamic-log-title">Log Output</h3>
                        <div>
                            <button class="action-btn" id="copyLogBtn"><i class="fas fa-copy"></i> Copy</button>
                            <button class="action-btn" id="clearLogBtn"><i class="fas fa-trash-alt"></i> Clear</button>
                        </div>
                    </div>
                    <div id="dynamic-log-content" class="log-content">Select a log from the menu...</div>
                </div>
            </div>
        </div>
    </div>

    <footer style="text-align: center; padding: 10px; color: var(--color-text-muted); font-size: 12px; position: fixed; bottom: 0; width: 100%; z-index: 1; background: rgba(10, 10, 30, 0.95);">
        <p><strong>© 2025 CardX CHK - Multi-Gateway Checker</strong></p>
        <p style="font-style: italic; color: var(--color-yellow);">The New ERA Begins</p>
    </footer>

    <script>
        // Prevent Copying
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                e.preventDefault();
                Swal.fire({
                    title: 'Copying Disabled',
                    text: 'Copying is not allowed on this page.',
                    icon: 'warning',
                    background: '#2a2a4a',
                    color: 'var(--color-text-light)',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });

        // Enhanced Background Animation
        const arrowContainer = document.getElementById('arrow-animation');
        for (let i = 0; i < 50; i++) {
            const arrow = document.createElement('div');
            arrow.className = 'arrow';
            const arrowTypes = ['>', '→', '⇒', '➤'];
            arrow.innerHTML = arrowTypes[Math.floor(Math.random() * arrowTypes.length)];
            arrow.style.left = `${Math.random() * 100}%`;
            arrow.style.top = `${Math.random() * 100}%`;
            arrow.style.animationDuration = `${Math.random() * 9 + 3}s`;
            arrow.style.animationDelay = `${Math.random() * 6}s`;
            arrow.style.color = ['#ff69b4', '#9b30ff', '#1e90ff', '#00ff7f', '#dc143c', '#00ffff', '#ffff00'][Math.floor(Math.random() * 7)];
            arrow.style.setProperty('--random-size', Math.random());
            arrow.style.setProperty('--random-opacity', Math.random());
            arrowContainer.appendChild(arrow);
        }

        $(document).ready(function() {
            let isProcessing = false;
            let isStopping = false;
            let activeRequests = 0;
            let cardQueue = [];
            let totalCards = 0;
            const MAX_CONCURRENT = 3;
            const MAX_RETRIES = 1;
            let abortControllers = [];
            let approvedCards = [];
            let chargedCards = [];
            let declinedCards = [];

            const LOG_MAP = {
                'approved': { title: 'Approved Cards Logs', data: approvedCards, countClass: '.approved', logClass: 'log-success', clearable: true },
                'charged': { title: 'Charged Cards Logs', data: chargedCards, countClass: '.charged', logClass: 'log-charged', clearable: true },
                'declined': { title: 'Declined Cards Logs', data: declinedCards, countClass: '.reprovadas', logClass: 'log-danger', clearable: true }
            };

            function updateAllCounts() {
                $('.approved').text(approvedCards.length);
                $('.charged').text(chargedCards.length);
                $('.reprovadas').text(declinedCards.length);
                $('.checked').text(`${approvedCards.length + chargedCards.length + declinedCards.length} / ${totalCards}`);
            }

            function renderLog(viewId) {
                const config = LOG_MAP[viewId];
                if (!config) return;

                $('#dynamic-log-title').text(config.title);
                $('#dynamic-log-content').empty();
                
                if (config.data.length === 0) {
                    $('#dynamic-log-content').html(`<span style="color: var(--color-text-muted);">No entries yet.</span>`);
                } else {
                    const table = $('<table style="width: 100%; border-collapse: collapse;">');
                    config.data.forEach(item => {
                        const row = $('<tr><td style="padding: 8px; border-bottom: 1px solid rgba(30, 144, 255, 0.2); color: ' + (viewId === 'declined' ? '#ffffff' : '#00ff7f') + '; text-shadow: 0 0 5px ' + (viewId === 'declined' ? '#ffffff' : '#00ff7f') + ';">' + item + '</td></tr>');
                        table.append(row);
                    });
                    $('#dynamic-log-content').append(table);
                }
                $('#clearLogBtn').toggle(config.clearable);
            }

            function switchView(viewId) {
                $('.view-section').addClass('hidden');
                $('#view-' + viewId).removeClass('hidden');
                $('.nav-item').removeClass('active');
                $(`.nav-item[data-view="${viewId}"]`).addClass('active');
                if (viewId !== 'process') renderLog(viewId);
            }

            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
                
                if (!isProcessing) {
                    $('.carregadas').text('0');
                    $('.charged').text('0');
                    $('.approved').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                }
            });

            $('#copyLogBtn').click(function() {
                Swal.fire({
                    title: 'Copying Disabled',
                    text: 'Copying logs is not allowed.',
                    icon: 'warning',
                    background: '#2a2a4a',
                    color: 'var(--color-text-light)',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            $('#clearLogBtn').click(function() {
                const viewId = $('.nav-item.active').data('view');
                const config = LOG_MAP[viewId];
                if (!config || !config.clearable) return;

                Swal.fire({
                    title: `Clear ${config.title}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--color-danger)',
                    confirmButtonText: 'Yes, clear!',
                    background: '#2a2a4a',
                    color: 'var(--color-text-light)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        config.data.length = 0;
                        renderLog(viewId);
                        updateAllCounts();
                        Swal.fire({
                            title: 'Cleared!',
                            text: '',
                            icon: 'success',
                            background: '#2a2a4a',
                            color: 'var(--color-text-light)'
                        });
                    }
                });
            });

            async function processCard(card, controller, retryCount = 0) {
                if (!isProcessing) return null;

                try {
                    const formData = new FormData();
                    let normalizedYear = card.exp_year;
                    if (normalizedYear.length === 2) {
                        normalizedYear = (parseInt(normalizedYear) < 50 ? '20' : '19') + normalizedYear;
                    }
                    formData.append('card[number]', card.number);
                    formData.append('card[exp_month]', card.exp_month);
                    formData.append('card[exp_year]', normalizedYear);
                    formData.append('card[cvc]', card.cvc);

                    const response = await $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000,
                        signal: controller.signal
                    });

                    return {
                        isCharged: response.includes('CHARGED'),
                        isApproved: response.includes('APPROVED') && !response.includes('CHARGED'),
                        response: response.trim(),
                        displayCard: card.displayCard
                    };
                } catch (xhr) {
                    if (xhr.statusText === 'abort') return null;
                    if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        return processCard(card, controller, retryCount + 1);
                    }
                    return {
                        isCharged: false,
                        isApproved: false,
                        response: `DECLINED [${xhr.statusText || 'Error'}] ${card.displayCard}`,
                        displayCard: card.displayCard
                    };
                }
            }

            async function processCards() {
                if (isProcessing) return;

                const cardText = $('#cards').val().trim();
                const lines = cardText.split('\n').filter(line => line.trim());
                const validCards = lines
                    .map(line => line.trim())
                    .filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line))
                    .map(line => {
                        const [number, exp_month, exp_year, cvc] = line.split('|');
                        return { number, exp_month, exp_year, cvc, displayCard: line };
                    });

                if (validCards.length === 0) {
                    Swal.fire({
                        title: 'No Valid Cards',
                        text: 'Check format: card|MM|YY or YYYY|CVV',
                        icon: 'error',
                        background: '#2a2a4a',
                        color: 'var(--color-text-light)'
                    });
                    return;
                }
                if (validCards.length > 1000) {
                    Swal.fire({
                        title: 'Limit Exceeded',
                        text: 'Maximum 1000 cards allowed',
                        icon: 'warning',
                        background: '#2a2a4a',
                        color: 'var(--color-text-light)'
                    });
                    return;
                }

                isProcessing = true;
                isStopping = false;
                activeRequests = 0;
                abortControllers = [];
                cardQueue = [...validCards];
                totalCards = validCards.length;
                approvedCards.length = 0;
                chargedCards.length = 0;
                declinedCards.length = 0;

                $('.carregadas').text(totalCards);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                switchView('process');
                updateAllCounts();

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
                            if (result === null || !isProcessing) return;

                            activeRequests--;

                            if (result.isCharged) {
                                chargedCards.push(result.response);
                            } else if (result.isApproved) {
                                approvedCards.push(result.response);
                            } else {
                                declinedCards.push(result.response);
                            }

                            updateAllCounts();

                            if (currentView !== 'process') renderLog(currentView);

                            const completed = approvedCards.length + chargedCards.length + declinedCards.length;
                            if (completed >= totalCards || !isProcessing) finishProcessing();
                        }).catch(err => {
                            console.error('Processing error:', err);
                            activeRequests--;
                            updateAllCounts();
                            if (cardQueue.length === 0 && activeRequests === 0) finishProcessing();
                        });
                    }
                    if (isProcessing) await new Promise(resolve => setTimeout(resolve, 5));
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
                updateAllCounts();

                Swal.fire({
                    title: 'Processing Complete',
                    text: 'All cards checked. View logs via menu.',
                    icon: 'success',
                    background: '#2a2a4a',
                    color: 'var(--color-text-light)'
                });
            }

            $('#startBtn').click(function() {
                if ($('#gate').val() === '') {
                    Swal.fire({
                        title: 'No Gateway Selected',
                        text: 'Please select a valid gateway.',
                        icon: 'error',
                        background: '#2a2a4a',
                        color: 'var(--color-text-light)'
                    });
                    return;
                }
                processCards();
            });

            $('#stopBtn').click(() => {
                if (!isProcessing || isStopping) return;

                isProcessing = false;
                isStopping = true;
                cardQueue = [];
                abortControllers.forEach(controller => controller.abort());
                abortControllers = [];
                activeRequests = 0;
                updateAllCounts();
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();

                Swal.fire({
                    title: 'Stopped',
                    text: 'Processing halted.',
                    icon: 'warning',
                    background: '#2a2a4a',
                    color: 'var(--color-text-light)'
                });
            });

            $('#gate').change(function() {
                const selected = $(this).val();
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php')) {
                    Swal.fire({
                        title: 'Gateway Not Available',
                        text: 'Only Stripe Auth and PayPal 1$ available',
                        icon: 'info',
                        background: '#2a2a4a',
                        color: 'var(--color-text-light)'
                    });
                    $(this).val('gate/stripeauth.php');
                }
            });

            let currentView = 'process';
            switchView('process');
        });

        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
        }
    </script>
</body>
</html>
