<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Location: https://cardxchk.onrender.com/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CardXCHK Checker</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-bg: #0a0e27; --secondary-bg: #131937; --card-bg: #1a1f3a;
            --accent-blue: #3b82f6; --accent-purple: #8b5cf6; --accent-cyan: #06b6d4;
            --accent-green: #10b981; --text-primary: #ffffff; --text-secondary: #94a3b8;
            --border-color: #1e293b; --error: #ef4444; --warning: #f59e0b; --shadow: rgba(0,0,0,0.3);
        }
        [data-theme="light"] {
            --primary-bg: #f8fafc; --secondary-bg: #ffffff; --card-bg: #ffffff;
            --text-primary: #0f172a; --text-secondary: #475569; --border-color: #e2e8f0;
        }
        body {
            font-family: Inter, sans-serif; background: var(--primary-bg);
            color: var(--text-primary); min-height: 100vh; overflow-x: hidden;
        }
        .blur-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            backdrop-filter: blur(5px); z-index: 9998; opacity: 1;
            transition: opacity 1.2s ease-in-out, backdrop-filter 1.2s ease-in-out;
        }
        .blur-overlay.fade-out { opacity: 0; backdrop-filter: blur(0); pointer-events: none; }
        .moving-logo {
            position: fixed; font-size: 5rem; z-index: 10000;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            transition: all 1.2s ease-in-out;
        }
        .moving-logo.in-position { font-size: 1.75rem; top: 1.9rem; left: 2rem; transform: translate(0,0); }
        .moving-logo.hidden { opacity: 0; }
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: rgba(10,14,39,0.85); backdrop-filter: blur(20px);
            padding: 1rem 2rem; display: flex; justify-content: space-between;
            z-index: 1000; border-bottom: 1px solid var(--border-color);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 0.75rem;
            font-size: 1.5rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .navbar-brand i { opacity: 0; font-size: 1.75rem; transition: opacity 0.5s; }
        .navbar-brand i.visible, .brand-text.visible { opacity: 1; }
        .brand-text { opacity: 0; transition: opacity 0.5s; }
        .navbar-actions { display: flex; align-items: center; gap: 1rem; }
        .theme-toggle {
            width: 60px; height: 32px; background: var(--secondary-bg);
            border-radius: 20px; cursor: pointer; border: 2px solid var(--border-color);
            position: relative; transition: all 0.3s;
        }
        .theme-toggle-slider {
            position: absolute; width: 24px; height: 24px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            left: 4px; transition: transform 0.3s; display: flex;
            align-items: center; justify-content: center; color: white; font-size: 0.7rem;
        }
        [data-theme="light"] .theme-toggle-slider { transform: translateX(28px); }
        .user-info {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.5rem 1rem; background: rgba(255,255,255,0.05);
            border-radius: 12px; border: 1px solid var(--border-color);
        }
        .user-avatar {
            width: 35px; height: 35px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-purple), #ec4899);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; color: white;
        }
        .nav-btn {
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);
            padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .nav-btn.primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border: none; color: white;
        }
        .sidebar {
            position: fixed; left: 0; top: 70px; bottom: 0; width: 260px;
            background: var(--card-bg); border-right: 1px solid var(--border-color);
            padding: 2rem 0; z-index: 999; overflow-y: auto;
            transform: translateX(0); transition: transform 0.3s ease;
        }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-menu { list-style: none; }
        .sidebar-item { margin: 0.5rem 1rem; }
        .sidebar-link {
            display: flex; align-items: center; gap: 1rem;
            padding: 0.85rem 1.25rem; color: var(--text-secondary);
            border-radius: 12px; cursor: pointer; transition: all 0.3s;
        }
        .sidebar-link:hover {
            background: rgba(59,130,246,0.1); color: var(--accent-blue);
            transform: translateX(5px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
        }
        .sidebar-link i { width: 20px; text-align: center; }
        .sidebar-divider { height: 1px; background: var(--border-color); margin: 1.5rem 1rem; }
        .main-content {
            margin-left: 260px; margin-top: 70px; padding: 2rem;
            min-height: calc(100vh - 70px); position: relative; z-index: 1;
            transition: margin-left 0.3s ease;
        }
        .main-content.sidebar-closed { margin-left: 0; }
        .page-section { display: none; }
        .page-section.active { display: block; }
        .page-title {
            font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--accent-cyan));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); margin-bottom: 2rem; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 20px; padding: 1.75rem; position: relative;
            transition: all 0.3s; box-shadow: 0 2px 8px var(--shadow);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 40px var(--shadow); }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple));
        }
        .stat-card.approved::before { background: linear-gradient(90deg, var(--accent-cyan), var(--accent-green)); }
        .stat-card.charged::before { background: linear-gradient(90deg, var(--warning), #ec4899); }
        .stat-card.declined::before { background: linear-gradient(90deg, var(--error), #ec4899); }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 1rem;
            background: rgba(59,130,246,0.15); color: var(--accent-blue);
        }
        .stat-card.approved .stat-icon { background: rgba(6,182,212,0.15); color: var(--accent-cyan); }
        .stat-card.charged .stat-icon { background: rgba(245,158,11,0.15); color: var(--warning); }
        .stat-card.declined .stat-icon { background: rgba(239,68,68,0.15); color: var(--error); }
        .stat-value { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .stat-label {
            color: var(--text-secondary); font-size: 0.95rem; text-transform: uppercase;
        }
        .checker-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem;
        }
        .checker-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .checker-title {
            font-size: 1.75rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .checker-title i { color: var(--accent-cyan); }
        .settings-btn {
            padding: 0.6rem 1.2rem; border-radius: 12px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.05); color: var(--text-primary);
            cursor: pointer; font-weight: 500; display: flex;
            align-items: center; gap: 0.5rem; transition: all 0.3s;
        }
        .settings-btn:hover {
            border-color: var(--accent-blue); color: var(--accent-blue);
            transform: translateY(-2px);
        }
        .input-section { margin-bottom: 2rem; }
        .input-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 1rem;
        }
        .input-label { font-weight: 600; }
        .card-textarea {
            width: 100%; min-height: 200px; background: var(--secondary-bg);
            border: 2px solid var(--border-color); border-radius: 16px;
            padding: 1.25rem; color: var(--text-primary);
            font-family: 'Courier New', monospace; resize: vertical;
            transition: all 0.3s;
        }
        .card-textarea:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn {
            padding: 0.9rem 2rem; border-radius: 14px; border: none;
            font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 0.75rem; min-width: 140px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
        }
        .btn-primary:hover { transform: translateY(-3px); }
        .btn-primary:disabled { background: rgba(59,130,246,0.5); cursor: not-allowed; }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color); color: var(--text-primary);
        }
        .btn-secondary:hover { transform: translateY(-2px); }
        .btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
        .results-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem;
        }
        .results-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .results-title {
            font-size: 1.75rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .results-title i { color: var(--accent-green); }
        .results-filters { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.5rem 1rem; border-radius: 10px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.03); color: var(--text-secondary);
            cursor: pointer; font-size: 0.85rem; transition: all 0.3s;
        }
        .filter-btn:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .filter-btn.active {
            background: var(--accent-blue); border-color: var(--accent-blue); color: white;
        }
        .empty-state {
            text-align: center; padding: 4rem 2rem; color: var(--text-secondary);
        }
        .empty-state i { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .settings-popup {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            display: none; align-items: center; justify-content: center; z-index: 10000;
        }
        .settings-popup.active { display: flex; }
        .settings-content {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 24px; padding: 2rem; max-width: 600px; width: 90%;
            max-height: 80vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .settings-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .settings-title {
            font-size: 1.5rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .settings-close {
            width: 35px; height: 35px; border-radius: 10px; border: none;
            background: rgba(255,255,255,0.05); color: var(--text-secondary);
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; transition: all 0.3s;
        }
        .settings-close:hover {
            background: var(--error); color: white; transform: rotate(90deg);
        }
        .gateway-group { margin-bottom: 2rem; }
        .gateway-group-title {
            font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .gateway-options { display: grid; gap: 0.75rem; }
        .gateway-option {
            display: flex; align-items: center; padding: 1rem;
            background: var(--secondary-bg); border: 2px solid var(--border-color);
            border-radius: 12px; cursor: pointer; transition: all 0.3s;
        }
        .gateway-option:hover {
            border-color: var(--accent-blue); transform: translateX(5px);
        }
        .gateway-option input[type="radio"] {
            width: 20px; height: 20px; margin-right: 1rem;
            cursor: pointer; accent-color: var(--accent-blue);
        }
        .gateway-option-content { flex: 1; }
        .gateway-option-name {
            font-weight: 600; display: flex; align-items: center;
            gap: 0.5rem; margin-bottom: 0.25rem;
        }
        .gateway-option-desc { font-size: 0.85rem; color: var(--text-secondary); }
        .gateway-badge {
            padding: 0.25rem 0.75rem; border-radius: 6px;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-charge { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-auth { background: rgba(6,182,212,0.15); color: var(--accent-cyan); }
        .settings-footer {
            display: flex; gap: 1rem; margin-top: 2rem;
            padding-top: 1rem; border-top: 1px solid var(--border-color);
        }
        .btn-save {
            flex: 1; padding: 0.9rem; border-radius: 12px; border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white; font-weight: 600; cursor: pointer; transition: all 0.3s;
        }
        .btn-save:hover { transform: translateY(-2px); }
        .btn-cancel {
            flex: 1; padding: 0.9rem; border-radius: 12px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.05); color: var(--text-primary);
            font-weight: 600; cursor: pointer;
        }
        .loader {
            border: 4px solid #f3f3f3; border-top: 4px solid #ec4899;
            border-radius: 50%; width: 40px; height: 40px;
            animation: spin 1s linear infinite; margin: 20px auto; display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #statusLog { margin-top: 1rem; color: var(--text-secondary); text-align: center; }

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 0; padding: 1rem; }
            .main-content.sidebar-open { margin-left: 200px; }
            .navbar { padding: 1rem; }
            .page-title { font-size: 1.5rem; }
            .checker-section { padding: 1.5rem; }
            .card-textarea { min-height: 150px; }
            .btn { padding: 0.7rem 1.5rem; min-width: 100px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .stat-card { padding: 1rem; }
            .stat-value { font-size: 1.8rem; }
            .results-section { padding: 1.5rem; }
            .moving-logo { font-size: 3rem; }
            .moving-logo.in-position { font-size: 1.25rem; left: 1rem; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; }
        }
    </style>
</head>
<body data-theme="dark">
    <div class="blur-overlay" id="blurOverlay"></div>
    <i class="fas fa-credit-card moving-logo" id="movingLogo"></i>

    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-credit-card" id="navbarLogo"></i>
            <span class="brand-text" id="brandText">CARDXCHK</span>
        </div>
        <div class="navbar-actions">
            <div class="theme-toggle" onclick="toggleTheme()">
                <div class="theme-toggle-slider"><i class="fas fa-moon"></i></div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user']['first_name'] ?? 'U', 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['user']['first_name'] ?? 'User'); ?></span>
            </div>
            <button class="nav-btn primary">
                <i class="fas fa-crown"></i><span>10 Days Left</span>
            </button>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a class="sidebar-link active" onclick="showPage('home')">
                    <i class="fas fa-home"></i><span>Home</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link" onclick="showPage('checking')">
                    <i class="fas fa-credit-card"></i><span>Card Checking</span>
                </a>
            </li>
            <div class="sidebar-divider"></div>
            <li class="sidebar-item">
                <a class="sidebar-link" onclick="Swal.fire('Coming Soon','More pages soon','info')">
                    <i class="fas fa-plus"></i><span>More Coming Soon</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <section class="page-section active" id="page-home">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back! Here's your checking overview</p>

            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="stat-value carregadas">0</div>
                    <div class="stat-label">Total Checked</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value approved">0</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card charged">
                    <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                    <div class="stat-value charged">0</div>
                    <div class="stat-label">Charged</div>
                </div>
                <div class="stat-card declined">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value declined">0</div>
                    <div class="stat-label">Declined</div>
                </div>
            </div>

            <div class="results-section">
                <div class="results-header">
                    <div class="results-title">
                        <i class="fas fa-list-check"></i> Recent Results
                    </div>
                    <div class="results-filters">
                        <button class="filter-btn active" onclick="filterResults('all')">All</button>
                        <button class="filter-btn" onclick="filterResults('approved')">Approved</button>
                        <button class="filter-btn" onclick="filterResults('declined')">Declined</button>
                    </div>
                </div>
                <div id="resultsList" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Results Yet</h3>
                    <p>Start checking cards to see results here</p>
                </div>
            </div>
        </section>

        <section class="page-section" id="page-checking">
            <h1 class="page-title">Card Checking</h1>
            <p class="page-subtitle">Check your cards with multiple gateways</p>

            <div class="checker-section">
                <div class="checker-header">
                    <div class="checker-title">
                        <i class="fas fa-shield-alt"></i> Card Checker
                    </div>
                    <button class="settings-btn" onclick="openGatewaySettings()">
                        <i class="fas fa-cog"></i> Gateway Settings
                    </button>
                </div>

                <div class="input-section">
                    <div class="input-header">
                        <label class="input-label">Enter Card Details</label>
                        <span class="card-count" id="cardCount" style="color: var(--text-secondary);">
                            <i class="fas fa-list"></i> 0 cards
                        </span>
                    </div>
                    <textarea id="cardInput" class="card-textarea" 
                        placeholder="Enter card details: card|month|year|cvv&#10;Example:&#10;4532123456789012|12|2025|123"></textarea>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-primary" id="startCheckBtn">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button class="btn btn-secondary" id="stopCheckBtn" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button class="btn btn-secondary" id="clearBtn">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                    <button class="btn btn-secondary" id="exportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
                <div class="loader" id="loader"></div>
                <div id="statusLog"></div>
            </div>
        </section>
    </main>

    <div class="settings-popup" id="gatewaySettings">
        <div class="settings-content">
            <div class="settings-header">
                <div class="settings-title">
                    <i class="fas fa-cog"></i> Gateway Settings
                </div>
                <button class="settings-close" onclick="closeGatewaySettings()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="gateway-group">
                <div class="gateway-group-title">
                    <i class="fas fa-bolt"></i> Charge Gateways
                </div>
                <div class="gateway-options">
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/stripe1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i> Stripe
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">Payment processing with charge</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/braintree1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fas fa-credit-card"></i> Braintree
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">PayPal owned payment gateway</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/paypal1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-paypal"></i> PayPal
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">Popular online payment system</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/shopify1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-shopify"></i> Shopify
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">E-commerce payment processing</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/razorpay0.10$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <img src="https://cdn.razorpay.com/logo.svg" alt="Razorpay" 
                                    style="width:20px; height:20px; object-fit:contain;"> Razorpay
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">Indian payment gateway</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/payu1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fas fa-wallet"></i> PayU
                                <span class="gateway-badge badge-charge">Charge</span>
                            </div>
                            <div class="gateway-option-desc">Global payment service provider</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="gateway-group">
                <div class="gateway-group-title">
                    <i class="fas fa-shield-alt"></i> Auth Gateways
                </div>
                <div class="gateway-options">
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/stripeauth.php" checked>
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i> Stripe
                                <span class="gateway-badge badge-auth">Auth</span>
                            </div>
                            <div class="gateway-option-desc">Authorization only, no charge</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/braintreeauth.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fas fa-credit-card"></i> Braintree
                                <span class="gateway-badge badge-auth">Auth</span>
                            </div>
                            <div class="gateway-option-desc">Card verification without charge</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="settings-footer">
                <button class="btn-save" onclick="saveGatewaySettings()">
                    <i class="fas fa-check"></i> Save Settings
                </button>
                <button class="btn-cancel" onclick="closeGatewaySettings()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedGateway = 'gate/stripeauth.php';
        let isProcessing = false;
        let isStopping = false;
        let activeRequests = 0;
        let cardQueue = [];
        const MAX_CONCURRENT = 2;
        const MAX_RETRIES = 2;
        let abortControllers = [];
        let totalCards = 0;
        let chargedCards = [];
        let approvedCards = [];
        let declinedCards = [];
        let sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        let sidebarOpen = true;

        window.addEventListener('load', function() {
            const movingLogo = document.getElementById('movingLogo');
            const navbarLogo = document.getElementById('navbarLogo');
            const brandText = document.getElementById('brandText');
            const blurOverlay = document.getElementById('blurOverlay');

            setTimeout(function() {
                movingLogo.classList.add('in-position');
                blurOverlay.classList.add('fade-out');
                setTimeout(function() {
                    navbarLogo.classList.add('visible');
                    brandText.classList.add('visible');
                    setTimeout(function() {
                        movingLogo.classList.add('hidden');
                    }, 200);
                    setTimeout(function() {
                        blurOverlay.style.display = 'none';
                    }, 400);
                }, 1200);
            }, 800);

            // Sidebar toggle for mobile
            document.querySelector('.navbar-brand').addEventListener('click', function(e) {
                e.preventDefault();
                sidebarOpen = !sidebarOpen;
                document.getElementById('sidebar').classList.toggle('closed', !sidebarOpen);
                document.querySelector('.main-content').classList.toggle('sidebar-closed', !sidebarOpen);
                document.getElementById('sidebar').classList.toggle('sidebar-open', sidebarOpen);
            });
        });

        function toggleTheme() {
            const body = document.body;
            const theme = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            document.querySelector('.theme-toggle-slider i').className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: `${theme === 'light' ? '☀️ Light' : '🌙 Dark'} Mode`,
                showConfirmButton: false, timer: 1500
            });
        }

        function showPage(pageName) {
            document.querySelectorAll('.page-section').forEach(page => page.classList.remove('active'));
            document.getElementById('page-' + pageName).classList.add('active');
            document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
            event.target.closest('.sidebar-link').classList.add('active');
            if (pageName === 'home') renderResult();
        }

        function openGatewaySettings() {
            document.getElementById('gatewaySettings').classList.add('active');
            const radio = document.querySelector(`input[value="${selectedGateway}"]`);
            if (radio) radio.checked = true;
        }

        function closeGatewaySettings() {
            document.getElementById('gatewaySettings').classList.remove('active');
        }

        function saveGatewaySettings() {
            const selected = document.querySelector('input[name="gateway"]:checked');
            if (selected) {
                selectedGateway = selected.value;
                const gatewayName = selected.parentElement.querySelector('.gateway-option-name').textContent.trim();
                Swal.fire({
                    icon: 'success', title: 'Gateway Updated!',
                    text: `Now using: ${gatewayName}`,
                    confirmButtonColor: '#10b981'
                });
                closeGatewaySettings();
            } else {
                Swal.fire({
                    icon: 'warning', title: 'No Gateway Selected',
                    text: 'Please select a gateway', confirmButtonColor: '#f59e0b'
                });
            }
        }

        function updateCardCount() {
            const cardInput = document.getElementById('cardInput');
            const cardCount = document.getElementById('cardCount');
            if (cardInput && cardCount) {
                const lines = cardInput.value.trim().split('\n').filter(line => line.trim() !== '');
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                cardCount.innerHTML = `<i class="fas fa-list"></i> ${validCards.length} cards`;
            }
        }

        function updateStats(total, approved, charged, declined) {
            document.querySelector('.carregadas').textContent = total;
            document.querySelector('.approved').textContent = approved;
            document.querySelector('.charged').textContent = charged;
            document.querySelector('.declined').textContent = declined;
        }

        function addResult(card, status, response) {
            const resultsList = document.getElementById('resultsList');
            const cardClass = status.toLowerCase();
            const icon = status === 'Approved' || status === 'Charged' ? 'fas fa-check-circle' : 'fas fa-times-circle';
            const color = status === 'Approved' ? 'var(--accent-cyan)' : status === 'Charged' ? 'var(--warning)' : 'var(--error)';
            const resultDiv = document.createElement('div');
            resultDiv.className = `stat-card ${cardClass} result-item`;
            resultDiv.innerHTML = `
                <div class="stat-icon" style="background: rgba(${color}, 0.15); color: ${color};">
                    <i class="${icon}"></i>
                </div>
                <div class="stat-value">${card.displayCard}</div>
                <div class="stat-label" style="color: ${color};">${status} - ${response}</div>
            `;
            resultsList.insertBefore(resultDiv, resultsList.firstChild);
            if (resultsList.classList.contains('empty-state')) {
                resultsList.classList.remove('empty-state');
                resultsList.innerHTML = '';
            }
        }

        function filterResults(filter) {
            document.querySelectorAll('#page-home .filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            const items = document.querySelectorAll('.result-item');
            items.forEach(item => {
                const status = item.className.match(/approved|charged|declined/)[0];
                item.style.display = filter === 'all' || status === filter ? 'block' : 'none';
            });
            Swal.fire({
                toast: true, position: 'top-end', icon: 'info',
                title: `Filter: ${filter.charAt(0).toUpperCase() + filter.slice(1)}`,
                showConfirmButton: false, timer: 1500
            });
        }

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

                $('#statusLog').text(`Processing card: ${card.displayCard}`);
                console.log(`Starting request for card: ${card.displayCard} with ${selectedGateway}`);

                $.ajax({
                    url: selectedGateway,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 300000,
                    signal: controller.signal,
                    success: function(response) {
                        let status = 'Declined';
                        let jsonResponse;
                        try {
                            jsonResponse = JSON.parse(response);
                            if (jsonResponse.status === 'CHARGED') status = 'Charged';
                            else if (jsonResponse.status === 'APPROVED') status = 'Approved';
                        } catch (e) {
                            console.error('Failed to parse response:', response, e);
                            if (response.includes('Charged!')) status = 'Charged';
                            else if (response.includes('Approved!')) status = 'Approved';
                        }
                        console.log(`Completed request for card: ${card.displayCard}, Status: ${status}, Response: ${jsonResponse ? JSON.stringify(jsonResponse) : response}`);
                        resolve({
                            status: status,
                            response: jsonResponse ? jsonResponse.message || jsonResponse.response || response : response,
                            card: card,
                            displayCard: card.displayCard
                        });
                    },
                    error: function(xhr) {
                        $('#statusLog').text(`Error on card: ${card.displayCard} - ${xhr.statusText} (HTTP ${xhr.status})`);
                        console.error(`Error for card: ${card.displayCard}, Status: ${xhr.status}, Text: ${xhr.statusText}, Response: ${xhr.responseText}`);
                        if (xhr.statusText === 'abort') {
                            resolve(null);
                        } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                            setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
                        } else {
                            resolve({
                                status: 'Declined',
                                response: `Declined [Request failed: ${xhr.statusText} (HTTP ${xhr.status})] ${card.displayCard}`,
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

            const cardText = $('#cardInput').val().trim();
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
            declinedCards = [];
            sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
            sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
            sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
            updateStats(totalCards, approvedCards.length, chargedCards.length, declinedCards.length);
            $('#startCheckBtn').prop('disabled', true);
            $('#stopCheckBtn').prop('disabled', false);
            $('#loader').show();
            $('#resultsList').addClass('hidden');
            $('#statusLog').text('Starting processing...');

            let requestIndex = 0;

            while (cardQueue.length > 0 && isProcessing) {
                while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                    const card = cardQueue.shift();
                    activeRequests++;
                    const controller = new AbortController();
                    abortControllers.push(controller);

                    await new Promise(resolve => setTimeout(resolve, requestIndex * 500));
                    requestIndex++;

                    processCard(card, controller).then(result => {
                        if (result === null) return;

                        activeRequests--;
                        const cardEntry = { response: result.response, displayCard: result.displayCard };
                        if (result.status === 'Charged') {
                            chargedCards.push(cardEntry);
                            sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                        } else if (result.status === 'Approved') {
                            approvedCards.push(cardEntry);
                            sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                        } else {
                            declinedCards.push(cardEntry);
                            sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                        }

                        addResult(result.card, result.status, result.response);
                        updateStats(totalCards, approvedCards.length, chargedCards.length, declinedCards.length);

                        if (approvedCards.length + chargedCards.length + declinedCards.length >= totalCards || !isProcessing) {
                            finishProcessing();
                        }
                    });
                }
                if (isProcessing) {
                    await new Promise(resolve => setTimeout(resolve, 10));
                }
            }
        }

        function finishProcessing() {
            isProcessing = false;
            isStopping = false;
            activeRequests = 0;
            cardQueue = [];
            abortControllers = [];
            $('#startCheckBtn').prop('disabled', false);
            $('#stopCheckBtn').prop('disabled', true);
            $('#loader').hide();
            $('#cardInput').val('');
            updateCardCount();
            $('#statusLog').text('Processing completed.');
            Swal.fire({
                title: 'Processing complete!',
                text: 'All cards have been checked. See the results in the dashboard.',
                icon: 'success',
                confirmButtonColor: '#ec4899'
            });
        }

        $('#startCheckBtn').on('click', processCards);

        $('#stopCheckBtn').on('click', function() {
            if (!isProcessing || isStopping) return;

            isProcessing = false;
            isStopping = true;
            cardQueue = [];
            abortControllers.forEach(controller => controller.abort());
            abortControllers = [];
            activeRequests = 0;
            updateStats(totalCards, approvedCards.length, chargedCards.length, declinedCards.length);
            $('#startCheckBtn').prop('disabled', false);
            $('#stopCheckBtn').prop('disabled', true);
            $('#loader').hide();
            $('#statusLog').text('Processing stopped.');
            Swal.fire({
                title: 'Stopped!',
                text: 'Processing has been stopped',
                icon: 'warning',
                confirmButtonColor: '#ec4899'
            });
        });

        $('#clearBtn').on('click', function() {
            if ($('#cardInput').val().trim()) {
                Swal.fire({
                    title: 'Clear Input?', text: 'Remove all entered cards',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, clear'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#cardInput').val('');
                        updateCardCount();
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Cleared!', showConfirmButton: false, timer: 1500
                        });
                    }
                });
            }
        });

        $('#exportBtn').on('click', function() {
            Swal.fire({
                icon: 'info', title: 'Export',
                text: 'Export functionality coming soon',
                confirmButtonColor: '#3b82f6'
            });
        });

        $('#cardInput').on('input', updateCardCount);

        function renderResult() {
            const totalChecked = approvedCards.length + chargedCards.length + declinedCards.length;
            updateStats(totalChecked, approvedCards.length, chargedCards.length, declinedCards.length);
            const resultsList = $('#resultsList');
            resultsList.empty();
            if (totalChecked === 0) {
                resultsList.addClass('empty-state').html(`
                    <i class="fas fa-inbox"></i>
                    <h3>No Results Yet</h3>
                    <p>Start checking cards to see results here</p>
                `);
            } else {
                resultsList.removeClass('empty-state');
                [approvedCards, chargedCards, declinedCards].forEach((cards, index) => {
                    const status = ['Approved', 'Charged', 'Declined'][index];
                    cards.forEach(card => addResult(card, status, card.response));
                });
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target === document.getElementById('gatewaySettings')) {
                closeGatewaySettings();
            }
        });
    </script>
</body>
</html>
