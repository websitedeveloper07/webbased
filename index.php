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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; user-select: none; }
        :root {
            --primary-bg: #0a0e27; --secondary-bg: #131937; --card-bg: #1a1f3a;
            --accent-blue: #3b82f6; --accent-purple: #8b5cf6; --accent-cyan: #06b6d4;
            --accent-green: #10b981; --text-primary: #ffffff; --text-secondary: #94a3b8;
            --border-color: #1e293b; --error: #ef4444; --warning: #f59e0b; --shadow: rgba(0,0,0,0.3);
            --success-green: #22c55e; --declined-red: #ef4444;
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
            backdrop-filter: blur(5px); z-index: 9998; opacity: 0; display: none;
        }
        .blur-overlay.active { opacity: 1; display: block; }
        .moving-logo {
            position: fixed; font-size: 3rem; z-index: 10000;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            transition: all 1.2s ease-in-out;
        }
        .moving-logo.in-position { font-size: 1.2rem; top: 1rem; left: 1rem; transform: translate(0,0); }
        .moving-logo.hidden { opacity: 0; }
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: rgba(10,14,39,0.85); backdrop-filter: blur(10px);
            padding: 0.5rem 1rem; display: flex; justify-content: space-between;
            z-index: 1000; border-bottom: 1px solid var(--border-color);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 1.2rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .navbar-brand i { opacity: 0; font-size: 1.2rem; transition: opacity 0.5s; }
        .navbar-brand i.visible, .brand-text.visible { opacity: 1; }
        .brand-text { opacity: 0; transition: opacity 0.5s; }
        .navbar-actions { display: flex; align-items: center; gap: 0.5rem; }
        .theme-toggle {
            width: 40px; height: 20px; background: var(--secondary-bg);
            border-radius: 10px; cursor: pointer; border: 1px solid var(--border-color);
            position: relative; transition: all 0.3s;
        }
        .theme-toggle-slider {
            position: absolute; width: 16px; height: 16px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            left: 2px; transition: transform 0.3s; display: flex;
            align-items: center; justify-content: center; color: white; font-size: 0.5rem;
        }
        [data-theme="light"] .theme-toggle-slider { transform: translateX(18px); }
        .user-info {
            display: flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.5rem; background: rgba(255,255,255,0.05);
            border-radius: 8px; border: 1px solid var(--border-color);
        }
        .user-avatar {
            width: 25px; height: 25px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-purple), #ec4899);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; color: white; font-size: 0.8rem;
        }
        .nav-btn {
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);
            padding: 0.3rem 0.6rem; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; gap: 0.3rem; font-size: 0.8rem;
        }
        .nav-btn.primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border: none; color: white;
        }
        .sidebar {
            position: fixed; left: 0; top: 50px; bottom: 0; width: 70vw;
            background: var(--card-bg); border-right: 1px solid var(--border-color);
            padding: 1rem 0; z-index: 999; overflow-y: auto;
            transform: translateX(-100%); transition: transform 0.3s ease;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-menu { list-style: none; }
        .sidebar-item { margin: 0.3rem 0.5rem; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.75rem; color: var(--text-secondary);
            border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: all 0.3s;
        }
        .sidebar-link:hover {
            background: rgba(59,130,246,0.1); color: var(--accent-blue);
            transform: translateX(5px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
        }
        .sidebar-link i { width: 15px; text-align: center; font-size: 0.9rem; }
        .sidebar-divider { height: 1px; background: var(--border-color); margin: 1rem 0.5rem; }
        .main-content {
            margin-left: 0; margin-top: 50px; padding: 1rem;
            min-height: calc(100vh - 50px); position: relative; z-index: 1;
            transition: margin-left 0.3s ease;
        }
        .main-content.sidebar-open { margin-left: 70vw; }
        .page-section { display: none; }
        .page-section.active { display: block; }
        .page-title {
            font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--accent-cyan));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem; margin-bottom: 1rem;
        }
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 0.75rem; position: relative;
            transition: all 0.3s; box-shadow: 0 2px 4px var(--shadow); min-height: 100px;
            display: flex; flex-direction: column; justify-content: flex-start; align-items: flex-start;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px var(--shadow); }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple));
        }
        .stat-card.approved::before { background: linear-gradient(90deg, var(--accent-cyan), var(--accent-green)); }
        .stat-card.charged::before { background: linear-gradient(90deg, var(--warning), #ec4899); }
        .stat-card.declined::before { background: linear-gradient(90deg, var(--error), #ec4899); }
        .stat-card.3ds::before { background: linear-gradient(90deg, var(--accent-purple), #ec4899); }
        .stat-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; margin-bottom: 0.5rem;
            background: rgba(59,130,246,0.15); color: var(--accent-blue);
        }
        .stat-card.approved .stat-icon { background: rgba(6,182,212,0.15); color: var(--accent-cyan); }
        .stat-card.charged .stat-icon { background: rgba(245,158,11,0.15); color: var(--warning); }
        .stat-card.declined .stat-icon { background: rgba(239,68,68,0.15); color: var(--error); }
        .stat-card.3ds .stat-icon { background: rgba(139,92,246,0.15); color: var(--accent-purple); }
        .stat-value { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.3rem; }
        .stat-label {
            color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; font-weight: 600;
        }
        .checker-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .checker-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .checker-title {
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .checker-title i { color: var(--accent-cyan); font-size: 1rem; }
        .settings-btn {
            padding: 0.3rem 0.6rem; border-radius: 8px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.05); color: var(--text-primary);
            cursor: pointer; font-weight: 500; display: flex;
            align-items: center; gap: 0.3rem; font-size: 0.8rem;
        }
        .settings-btn:hover {
            border-color: var(--accent-blue); color: var(--accent-blue);
            transform: translateY(-2px);
        }
        .input-section { margin-bottom: 1rem; }
        .input-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .input-label { font-weight: 600; font-size: 0.9rem; }
        .card-textarea {
            width: 100%; min-height: 150px; background: var(--secondary-bg);
            border: 1px solid var(--border-color); border-radius: 8px;
            padding: 0.75rem; color: var(--text-primary);
            font-family: 'Courier New', monospace; resize: vertical;
            font-size: 0.9rem; transition: all 0.3s;
        }
        .card-textarea:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; }
        .btn {
            padding: 0.5rem 1rem; border-radius: 8px; border: none;
            font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 0.3rem; min-width: 100px;
            font-size: 0.9rem; transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color); color: var(--text-primary);
        }
        .results-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .results-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .results-title {
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .results-title i { color: var(--accent-green); font-size: 1rem; }
        .results-filters { display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.3rem 0.6rem; border-radius: 6px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.03); color: var(--text-secondary);
            cursor: pointer; font-size: 0.7rem; transition: all 0.3s;
        }
        .filter-btn:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .filter-btn.active {
            background: var(--accent-blue); border-color: var(--accent-blue); color: white;
        }
        .empty-state {
            text-align: center; padding: 1.5rem 0.5rem; color: var(--text-secondary);
        }
        .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3; }
        .empty-state h3 { font-size: 1rem; margin-bottom: 0.3rem; }
        .settings-popup {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            display: none; align-items: center; justify-content: center; z-index: 10000;
        }
        .settings-popup.active { display: flex; }
        .settings-content {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1rem; max-width: 90vw; width: 90%;
            max-height: 80vh; overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .settings-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .settings-title {
            font-size: 1.1rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .settings-close {
            width: 25px; height: 25px; border-radius: 6px; border: none;
            background: rgba(255,255,255,0.05); color: var(--text-secondary);
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 0.9rem; transition: all 0.3s;
        }
        .settings-close:hover {
            background: var(--error); color: white; transform: rotate(90deg);
        }
        .gateway-group { margin-bottom: 1rem; }
        .gateway-group-title {
            font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;
            display: flex; align-items: center; gap: 0.3rem;
        }
        .gateway-options { display: grid; gap: 0.5rem; }
        .gateway-option {
            display: flex; align-items: center; padding: 0.5rem;
            background: var(--secondary-bg); border: 1px solid var(--border-color);
            border-radius: 8px; cursor: pointer; transition: all 0.3s;
        }
        .gateway-option:hover {
            border-color: var(--accent-blue); transform: translateX(3px);
        }
        .gateway-option input[type="radio"] {
            width: 15px; height: 15px; margin-right: 0.5rem;
            cursor: pointer; accent-color: var(--accent-blue);
        }
        .gateway-option-content { flex: 1; }
        .gateway-option-name {
            font-weight: 600; display: flex; align-items: center;
            gap: 0.3rem; margin-bottom: 0.2rem; font-size: 0.9rem;
        }
        .gateway-option-desc { font-size: 0.7rem; color: var(--text-secondary); }
        .gateway-badge {
            padding: 0.2rem 0.5rem; border-radius: 4px;
            font-size: 0.6rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-charge { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-auth { background: rgba(6,182,212,0.15); color: var(--accent-cyan); }
        .settings-footer {
            display: flex; gap: 0.5rem; margin-top: 1rem;
            padding-top: 0.5rem; border-top: 1px solid var(--border-color);
        }
        .btn-save {
            flex: 1; padding: 0.5rem; border-radius: 8px; border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white; font-weight: 600; cursor: pointer; font-size: 0.9rem;
        }
        .btn-save:hover { transform: translateY(-2px); }
        .btn-cancel {
            flex: 1; padding: 0.5rem; border-radius: 8px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.05); color: var(--text-primary);
            font-weight: 600; cursor: pointer; font-size: 0.9rem;
        }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #ec4899;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
            display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #statusLog { margin-top: 0.5rem; color: var(--text-secondary); text-align: center; font-size: 0.8rem; }
        .result-item.declined .stat-label { color: var(--declined-red); }
        .result-item.approved .stat-label, .result-item.charged .stat-label, .result-item.3ds .stat-label { color: var(--success-green); }
        .copy-btn { background: transparent; border: none; cursor: pointer; color: var(--accent-blue); font-size: 0.8rem; margin-left: auto; }
        .copy-btn:hover { color: var(--accent-purple); }
        .stat-content { display: flex; align-items: center; justify-content: space-between; }
        .menu-toggle { color: #ffffff !important; font-size: 1.2rem; padding: 0.3rem; cursor: pointer; transition: all 0.3s; }
        .menu-toggle:hover { transform: scale(1.1); }
        .sidebar-link.logout {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
        }
        .sidebar-link.logout:hover {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            transform: translateX(5px);
        }
        @media (min-width: 769px) {
            .navbar { padding: 1rem 2rem; }
            .navbar-brand { font-size: 1.5rem; }
            .navbar-brand i { font-size: 1.75rem; }
            .theme-toggle { width: 60px; height: 32px; }
            .theme-toggle-slider { width: 24px; height: 24px; font-size: 0.7rem; }
            [data-theme="light"] .theme-toggle-slider { transform: translateX(28px); }
            .user-info { padding: 0.5rem 1rem; gap: 0.75rem; }
            .user-avatar { width: 35px; height: 35px; font-size: 1rem; }
            .nav-btn { padding: 0.6rem 1.2rem; font-size: 1rem; }
            .sidebar { width: 260px; top: 70px; }
            .main-content { margin-top: 70px; padding: 2rem; margin-left: 0; }
            .main-content.sidebar-open { margin-left: 260px; }
            .page-title { font-size: 2rem; }
            .page-subtitle { font-size: 1rem; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
            .stat-card { padding: 1.75rem; min-height: 180px; border-radius: 20px; }
            .stat-icon { width: 50px; height: 50px; font-size: 1.5rem; }
            .stat-value { font-size: 2.5rem; }
            .stat-label { font-size: 0.95rem; }
            .checker-section { padding: 2.5rem; border-radius: 24px; }
            .checker-title { font-size: 1.75rem; }
            .checker-title i { font-size: 1.2rem; }
            .settings-btn { padding: 0.6rem 1.2rem; font-size: 1rem; border-radius: 12px; }
            .input-label { font-size: 1rem; }
            .card-textarea { min-height: 200px; padding: 1.25rem; font-size: 1rem; border-radius: 16px; }
            .btn { padding: 0.9rem 2rem; min-width: 140px; font-size: 1rem; border-radius: 14px; }
            .results-section { padding: 2.5rem; border-radius: 24px; }
            .results-title { font-size: 1.75rem; }
            .results-title i { font-size: 1.2rem; }
            .filter-btn { padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 10px; }
            .settings-content { padding: 2rem; max-width: 600px; border-radius: 24px; }
            .settings-title { font-size: 1.5rem; }
            .settings-close { width: 35px; height: 35px; font-size: 1rem; border-radius: 10px; }
            .gateway-group-title { font-size: 1.1rem; }
            .gateway-option { padding: 1rem; border-radius: 12px; }
            .gateway-option input[type="radio"] { width: 20px; height: 20px; }
            .gateway-option-name { font-size: 1rem; }
            .gateway-option-desc { font-size: 0.85rem; }
            .gateway-badge { padding: 0.25rem 0.75rem; font-size: 0.75rem; border-radius: 6px; }
            .btn-save, .btn-cancel { padding: 0.9rem; font-size: 1rem; border-radius: 12px; }
            .loader { width: 40px; height: 40px; border-width: 4px; }
            #statusLog { font-size: 0.95rem; }
            .copy-btn { font-size: 1rem; }
            .menu-toggle { font-size: 1.5rem; padding: 0.5rem; }
        }
    </style>
</head>
<body data-theme="light">
    <div class="blur-overlay" id="blurOverlay"></div>
    <i class="fas fa-credit-card moving-logo" id="movingLogo"></i>

    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-credit-card" id="navbarLogo"></i>
            <span class="brand-text" id="brandText">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</span>
        </div>
        <div class="navbar-actions">
            <div class="theme-toggle" onclick="toggleTheme()">
                <div class="theme-toggle-slider"><i class="fas fa-sun"></i></div>
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
            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a class="sidebar-link active" onclick="showPage('home'); closeSidebar()">
                    <i class="fas fa-home"></i><span>Home</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link" onclick="showPage('checking'); closeSidebar()">
                    <i class="fas fa-credit-card"></i><span>Card Checking</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link" onclick="showPage('autoshopify'); closeSidebar()">
                    <i class="fas fa-shopping-cart"></i><span>Autoshopify</span>
                </a>
            </li>
            <div class="sidebar-divider"></div>
            <li class="sidebar-item">
                <a class="sidebar-link" onclick="Swal.fire('Coming Soon','More pages soon','info'); closeSidebar()">
                    <i class="fas fa-plus"></i><span>More Coming Soon</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link logout" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <section class="page-section active" id="page-home">
            <h1 class="page-title">𝐃𝐚𝐬𝐡𝐛𝐨𝐚𝐫𝐝</h1>
            <p class="page-subtitle">𝐖𝐞𝐥𝐜𝐨𝐦𝐞 𝐛𝐚𝐜𝐤! 𝐇𝐞𝐫𝐞’𝐬 𝐲𝐨𝐮𝐫 𝐜𝐮𝐫𝐫𝐞𝐧𝐭 𝐬𝐭𝐚𝐭𝐬.</p>

            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <div id="total-value" class="stat-value">0</div>
                    <div class="stat-label">TOTAL</div>
                </div>
                <div class="stat-card charged">
                    <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                    <div id="charged-value" class="stat-value">0</div>
                    <div class="stat-label">HIT|CHARGED</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div id="approved-value" class="stat-value">0</div>
                    <div class="stat-label">LIVE|APPROVED</div>
                </div>
                <div class="stat-card 3ds">
                    <div class="stat-icon"><i class="fas fa-lock"></i></div>
                    <div id="3ds-value" class="stat-value">0</div>
                    <div class="stat-label">3DS</div>
                </div>
                <div class="stat-card declined">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div id="declined-value" class="stat-value">0</div>
                    <div class="stat-label">DEAD|DECLINED</div>
                </div>
                <div class="stat-card checked">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div id="checked-value" class="stat-value">0 / 0</div>
                    <div class="stat-label">CHECKED</div>
                </div>
            </div>
        </section>

        <section class="page-section" id="page-checking">
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑬𝑪𝑲𝑬𝑹</h1>
            <p class="page-subtitle">𝐂𝐡𝐞𝐜𝐤 𝐲𝐨𝐮𝐫 𝐜𝐚𝐫𝐝𝐬 𝐨𝐧 𝐦𝐮𝐥𝐭𝐢𝐩𝐥𝐞 𝐠𝐚𝐭𝐞𝐰𝐚𝐲𝐬</p>

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
                        <span class="card-count" id="cardCount">
                            <i class="fas fa-list"></i> 0 valid cards detected
                        </span>
                    </div>
                    <textarea id="cardInput" class="card-textarea" 
                        placeholder="Enter card details: card|month|year|cvv&#10;Example:&#10;4532123456789012|12|2025|123"></textarea>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button class="btn btn-secondary" id="stopBtn" disabled>
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
                <div id="statusLog" class="text-sm text-gray-500 mt-2"></div>
            </div>

            <div class="results-section" id="checkingResults">
                <div class="results-header">
                    <div class="results-title">
                        <i class="fas fa-list-check"></i> Recent Results
                    </div>
                    <div class="results-filters">
                        <button class="filter-btn active" onclick="filterResults('all')">All</button>
                        <button class="filter-btn" onclick="filterResults('charged')">Charged</button>
                        <button class="filter-btn" onclick="filterResults('approved')">Approved</button>
                        <button class="filter-btn" onclick="filterResults('3ds')">3D Cards</button>
                        <button class="filter-btn" onclick="filterResults('declined')">Declined</button>
                    </div>
                </div>
                <div id="checkingResultsList" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Results Yet</h3>
                    <p>Start checking cards to see results here</p>
                </div>
            </div>
        </section>

        <section class="page-section" id="page-autoshopify">
            <h1 class="page-title">Autoshopify Card Checker</h1>
            <p class="page-subtitle">Check your cards on multiple sites using Autoshopify</p>

            <div class="checker-section">
                <div class="checker-header">
                    <div class="checker-title">
                        <i class="fas fa-shopping-cart"></i> Autoshopify Checker
                    </div>
                    <!-- No settings for now -->
                </div>

                <div class="input-section">
                    <div class="input-header">
                        <label class="input-label">Enter Sites (one per line)</label>
                        <span class="card-count" id="sitesCount">
                            <i class="fas fa-globe"></i> 0 sites detected
                        </span>
                    </div>
                    <textarea id="sitesInput" class="card-textarea" 
                        placeholder="Enter sites: https://example.com&#10;Example:&#10;https://shop1.com&#10;https://shop2.com"></textarea>
                </div>

                <div class="input-section">
                    <div class="input-header">
                        <label class="input-label">Enter Card Details</label>
                        <span class="card-count" id="autoCardCount">
                            <i class="fas fa-list"></i> 0 valid cards detected
                        </span>
                    </div>
                    <textarea id="autoCardInput" class="card-textarea" 
                        placeholder="Enter card details: card|month|year|cvv&#10;Example:&#10;4532123456789012|12|2025|123"></textarea>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-primary" id="autoStartBtn">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button class="btn btn-secondary" id="autoStopBtn" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button class="btn btn-secondary" id="autoClearBtn">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                    <button class="btn btn-secondary" id="autoExportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
                <div class="loader" id="autoLoader"></div>
                <div id="autoStatusLog" class="text-sm text-gray-500 mt-2"></div>
            </div>

            <div class="results-section" id="autoResults">
                <div class="results-header">
                    <div class="results-title">
                        <i class="fas fa-list-check"></i> Recent Results
                    </div>
                    <div class="results-filters">
                        <button class="filter-btn active" onclick="filterAutoResults('all')">All</button>
                        <button class="filter-btn" onclick="filterAutoResults('charged')">Charged</button>
                        <button class="filter-btn" onclick="filterAutoResults('approved')">Approved</button>
                        <button class="filter-btn" onclick="filterAutoResults('3ds')">3D Cards</button>
                        <button class="filter-btn" onclick="filterAutoResults('declined')">Declined</button>
                    </div>
                </div>
                <div id="autoResultsList" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Results Yet</h3>
                    <p>Start checking cards to see results here</p>
                </div>
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
                                <span class="gateway-badge badge-charge">1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Payment processing with charge</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/paypal1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-paypal"></i> PayPal
                                <span class="gateway-badge badge-charge">1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Popular online payment system</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/shopify1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-shopify"></i> Shopify
                                <span class="gateway-badge badge-charge">1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">E-commerce payment processing</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/razorpay0.10$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <img src="https://cdn.razorpay.com/logo.svg" alt="Razorpay" 
                                    style="width:15px; height:15px; object-fit:contain;">Razorpay
                                <span class="gateway-badge badge-charge">0.10$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Indian payment gateway</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/authnet1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fas fa-credit-card"></i> Authnet
                                <span class="gateway-badge badge-charge">1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Authorize.net payment gateway</div>
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
                        <input type="radio" name="gateway" value="gate/stripeauth.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i> Stripe
                                <span class="gateway-badge badge-auth">Auth</span>
                            </div>
                            <div class="gateway-option-desc">Authorization only, no charge</div>
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
const MAX_CONCURRENT = 3; // Max 3 parallel requests for 3+ cards
const MAX_RETRIES = 2;
let abortControllers = [];
let totalCards = 0;
let chargedCards = [];
let approvedCards = [];
let threeDSCards = [];
let declinedCards = [];
let sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
let sidebarOpen = false;

window.addEventListener('load', function() {
    const movingLogo = document.getElementById('movingLogo');
    const navbarLogo = document.getElementById('navbarLogo');
    const brandText = document.getElementById('brandText');
    const blurOverlay = document.getElementById('blurOverlay');

    setTimeout(function() {
        movingLogo.classList.add('in-position');
        blurOverlay.classList.add('active');
        setTimeout(function() {
            navbarLogo.classList.add('visible');
            brandText.classList.add('visible');
            setTimeout(function() {
                movingLogo.classList.add('hidden');
                blurOverlay.classList.remove('active');
            }, 200);
            setTimeout(function() {
                blurOverlay.style.display = 'none';
            }, 400);
        }, 1200);
    }, 800);
});

// Disable copy, context menu, and dev tools, but allow pasting in the textarea
document.addEventListener('contextmenu', e => {
    if (e.target.id !== 'cardInput' && e.target.id !== 'autoCardInput' && e.target.id !== 'sitesInput') e.preventDefault();
});
document.addEventListener('copy', e => {
    if (e.target.id !== 'cardInput' && e.target.id !== 'autoCardInput' && e.target.id !== 'sitesInput') e.preventDefault();
});
document.addEventListener('cut', e => {
    if (e.target.id !== 'cardInput' && e.target.id !== 'autoCardInput' && e.target.id !== 'sitesInput') e.preventDefault();
});
document.addEventListener('paste', e => {
    if (e.target.id === 'cardInput' || e.target.id === 'autoCardInput' || e.target.id === 'sitesInput') {
        const pastedText = e.clipboardData.getData('text');
        const cursorPos = e.target.selectionStart;
        const textBefore = e.target.value.substring(0, cursorPos);
        const textAfter = e.target.value.substring(e.target.selectionEnd);
        e.target.value = textBefore + pastedText + textAfter;
        e.target.selectionStart = e.target.selectionEnd = cursorPos + pastedText.length;
        e.preventDefault();
        if (e.target.id === 'cardInput') updateCardCount();
        if (e.target.id === 'autoCardInput') updateAutoCardCount();
        if (e.target.id === 'sitesInput') updateSitesCount();
    } else {
        e.preventDefault();
    }
});
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 85 || e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 83)) {
        if (e.target.id !== 'cardInput' && e.target.id !== 'autoCardInput' && e.target.id !== 'sitesInput') e.preventDefault();
    } else if (e.keyCode === 123 || (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67))) {
        e.preventDefault();
    }
});

function toggleTheme() {
    const body = document.body;
    const theme = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    body.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    document.querySelector('.theme-toggle-slider i').className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: `${theme === 'light' ? 'Light' : 'Dark'} Mode`,
        showConfirmButton: false, timer: 1500
    });
}

function showPage(pageName) {
    document.querySelectorAll('.page-section').forEach(page => page.classList.remove('active'));
    document.getElementById('page-' + pageName).classList.add('active');
    document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
    event.target.closest('.sidebar-link').classList.add('active');
}

function closeSidebar() {
    sidebarOpen = false;
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.main-content').classList.remove('sidebar-open');
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
        cardCount.innerHTML = `<i class="fas fa-list"></i> ${validCards.length} valid cards detected (max 1000)`;
    }
}

function updateAutoCardCount() {
    const cardInput = document.getElementById('autoCardInput');
    const cardCount = document.getElementById('autoCardCount');
    if (cardInput && cardCount) {
        const lines = cardInput.value.trim().split('\n').filter(line => line.trim() !== '');
        const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
        cardCount.innerHTML = `<i class="fas fa-list"></i> ${validCards.length} valid cards detected (max 1000)`;
    }
}

function updateSitesCount() {
    const sitesInput = document.getElementById('sitesInput');
    const sitesCount = document.getElementById('sitesCount');
    if (sitesInput && sitesCount) {
        const lines = sitesInput.value.trim().split('\n').filter(line => line.trim() !== '');
        sitesCount.innerHTML = `<i class="fas fa-globe"></i> ${lines.length} sites detected`;
    }
}

function updateStats(total, charged, approved, threeDS, declined) {
    document.getElementById('total-value').textContent = total;
    document.getElementById('charged-value').textContent = charged;
    document.getElementById('approved-value').textContent = approved;
    document.getElementById('3ds-value').textContent = threeDS;
    document.getElementById('declined-value').textContent = declined;
    document.getElementById('checked-value').textContent = `${charged + approved + threeDS + declined} / ${total}`;
}

function addResult(card, status, response, isAuto = false) {
    const resultsList = isAuto ? document.getElementById('autoResultsList') : document.getElementById('checkingResultsList');
    if (!resultsList) return;
    let cardClass = status.toLowerCase();
    if (status === '3DS') cardClass = '3ds';
    const icon = (status === 'APPROVED' || status === 'CHARGED' || status === '3DS') ? 'fas fa-check-circle' : 'fas fa-times-circle';
    const color = (status === 'APPROVED' || status === 'CHARGED' || status === '3DS') ? 'var(--success-green)' : 'var(--declined-red)';
    const resultDiv = document.createElement('div');
    resultDiv.className = `stat-card ${cardClass} result-item`;
    resultDiv.innerHTML = `
        <div class="stat-icon" style="background: rgba(var(${color}), 0.15); color: ${color}; width: 20px; height: 20px; font-size: 0.8rem;">
            <i class="${icon}"></i>
        </div>
        <div class="stat-content">
            <div>
                <div class="stat-value" style="font-size: 0.9rem;">${card.displayCard}</div>
                <div class="stat-label" style="color: ${color}; font-size: 0.7rem;">${status} - ${response}</div>
            </div>
            <button class="copy-btn" onclick="copyToClipboard('${card.displayCard}')"><i class="fas fa-copy"></i></button>
        </div>
    `;
    resultsList.insertBefore(resultDiv, resultsList.firstChild);
    if (resultsList.classList.contains('empty-state')) {
        resultsList.classList.remove('empty-state');
        resultsList.innerHTML = '';
        resultsList.appendChild(resultDiv);
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Card copied!', showConfirmButton: false, timer: 1500
        });
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

function filterResults(filter) {
    document.querySelectorAll('#checkingResults .filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    const items = document.querySelectorAll('#checkingResultsList .result-item');
    items.forEach(item => {
        let status = item.className.split(' ')[1];
        if (status === '3ds') status = '3ds';
        item.style.display = filter === 'all' || status === filter ? 'block' : 'none';
    });
    Swal.fire({
        toast: true, position: 'top-end', icon: 'info',
        title: `Filter: ${filter.charAt(0).toUpperCase() + filter.slice(1)}`,
        showConfirmButton: false, timer: 1500
    });
}

function filterAutoResults(filter) {
    document.querySelectorAll('#autoResults .filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    const items = document.querySelectorAll('#autoResultsList .result-item');
    items.forEach(item => {
        let status = item.className.split(' ')[1];
        if (status === '3ds') status = '3ds';
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
            normalizedYear = '20' + normalizedYear;
        }
        formData.append('card[number]', card.number);
        formData.append('card[exp_month]', card.exp_month);
        formData.append('card[exp_year]', normalizedYear);
        formData.append('card[cvc]', card.cvc);

        $('#statusLog').text(`Processing card: ${card.displayCard}`);
        console.log(`Starting request for card: ${card.displayCard}`);

        $.ajax({
            url: selectedGateway,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 20000, // Reduced to match server
            signal: controller.signal,
            success: function(response) {
                let status = 'DECLINED';
                let message = response;
                try {
                    const jsonResponse = typeof response === 'string' ? JSON.parse(response) : response;
                    status = jsonResponse.status ? jsonResponse.status.toUpperCase() : 'DECLINED';
                    message = jsonResponse.message || response;
                    if (status.includes('3D_AUTHENTICATION') || status.includes('3DS') || message.includes('3D_AUTHENTICATION') || message.includes('3DS')) {
                        status = '3DS';
                    } else if (status === 'CHARGED' || message.includes('CHARGED')) {
                        status = 'CHARGED';
                    } else if (status === 'APPROVED' && !message.includes('3D_AUTHENTICATION')) {
                        status = 'APPROVED';
                    } else {
                        status = 'DECLINED';
                    }
                } catch (e) {
                    if (typeof response === 'string') {
                        if (response.includes('3D_AUTHENTICATION') || response.includes('3DS')) {
                            status = '3DS';
                        } else if (response.includes('CHARGED')) {
                            status = 'CHARGED';
                        } else if (response.includes('APPROVED')) {
                            status = 'APPROVED';
                        } else {
                            status = 'DECLINED';
                        }
                    }
                    message = response;
                }
                console.log(`Completed request for card: ${card.displayCard}, Status: ${status}, Response: ${message}`);
                resolve({
                    status: status,
                    response: message,
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
                    setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 1000);
                } else {
                    resolve({
                        status: 'DECLINED',
                        response: `Declined [Request failed: ${xhr.statusText} (HTTP ${xhr.status})]`,
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
    threeDSCards = [];
    declinedCards = [];
    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
    sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
    sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
    updateStats(totalCards, 0, 0, 0, 0);
    $('#startBtn').prop('disabled', true);
    $('#stopBtn').prop('disabled', false);
    $('#loader').show();
    $('#checkingResultsList').innerHTML = '';
    $('#statusLog').text('Starting processing...');

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
                const cardEntry = { response: result.response, displayCard: result.displayCard };
                if (result.status === 'CHARGED') {
                    chargedCards.push(cardEntry);
                    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                } else if (result.status === 'APPROVED') {
                    approvedCards.push(cardEntry);
                    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                } else if (result.status === '3DS') {
                    threeDSCards.push(cardEntry);
                    sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
                } else {
                    declinedCards.push(cardEntry);
                    sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                }

                addResult({displayCard: result.displayCard}, result.status, result.response);
                updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);

                if (chargedCards.length + approvedCards.length + threeDSCards.length + declinedCards.length >= totalCards || !isProcessing) {
                    finishProcessing();
                }
            });
        }
        if (isProcessing) {
            await new Promise(resolve => setTimeout(resolve, 10));
        }
    }
}

async function processAutoCards() {
    if (isProcessing) {
        Swal.fire({
            title: 'Processing in progress',
            text: 'Please wait until current process completes',
            icon: 'warning',
            confirmButtonColor: '#ec4899'
        });
        return;
    }

    const sitesText = $('#sitesInput').val().trim();
    const sitesLines = sitesText.split('\n').filter(line => line.trim());
    const sites = sitesLines.map(line => line.trim());

    if (sites.length === 0) {
        Swal.fire({
            title: 'No sites!',
            text: 'Please enter at least one site',
            icon: 'error',
            confirmButtonColor: '#ec4899'
        });
        return;
    }

    const cardText = $('#autoCardInput').val().trim();
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
    abortControllers = [];
    totalCards = validCards.length; // One check per card
    chargedCards = [];
    approvedCards = [];
    threeDSCards = [];
    declinedCards = [];
    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
    sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
    sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
    updateStats(totalCards, 0, 0, 0, 0);
    $('#autoStartBtn').prop('disabled', true);
    $('#autoStopBtn').prop('disabled', false);
    $('#autoLoader').show();
    $('#autoResultsList').innerHTML = '';
    $('#autoStatusLog').text('Starting processing...');

    async function checkCardWithSites(card, sites, siteIndex = 0) {
        if (siteIndex >= sites.length || !isProcessing || isStopping) {
            if (siteIndex >= sites.length) {
                const cardEntry = { response: 'Declined [No valid sites]', displayCard: card.displayCard, site: '' };
                declinedCards.push(cardEntry);
                sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                addResult({ displayCard: card.displayCard }, 'DECLINED', cardEntry.response, true);
                updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
            }
            activeRequests--;
            return;
        }

        const site = sites[siteIndex];
        const controller = new AbortController();
        abortControllers.push(controller);

        const formData = new FormData();
        formData.append('card[number]', card.number);
        formData.append('card[exp_month]', card.exp_month);
        let normalizedYear = card.exp_year;
        if (normalizedYear.length === 2) {
            normalizedYear = '20' + normalizedYear;
        }
        formData.append('card[exp_year]', normalizedYear);
        formData.append('card[cvc]', card.cvc);
        formData.append('card[displayCard]', card.displayCard);
        formData.append('site', site);

        $('#autoStatusLog').text(`Processing: ${card.displayCard} on ${site}`);

        $.ajax({
            url: 'gate/autoshopify.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 20000, // Match server timeout
            signal: controller.signal,
            success: function(response) {
                let status = 'DECLINED';
                let message = response;
                let displayCard = card.displayCard;
                let usedSite = site;
                try {
                    const jsonResponse = typeof response === 'string' ? JSON.parse(response) : response;
                    status = jsonResponse.status ? jsonResponse.status.toUpperCase() : 'DECLINED';
                    message = jsonResponse.message || response;
                    displayCard = jsonResponse.card || card.displayCard;
                    usedSite = jsonResponse.site || site;
                    if (status.includes('3D_AUTHENTICATION') || status.includes('3DS') || message.includes('3D_AUTHENTICATION') || message.includes('3DS')) {
                        status = '3DS';
                    } else if (status === 'CHARGED' || message.includes('CHARGED')) {
                        status = 'CHARGED';
                    } else if (status === 'APPROVED' && !message.includes('3D_AUTHENTICATION')) {
                        status = 'APPROVED';
                    } else {
                        status = 'DECLINED';
                    }
                } catch (e) {
                    console.error(`Error parsing response for card: ${card.displayCard}, Response: ${response}`);
                    if (typeof response === 'string') {
                        if (response.includes('3D_AUTHENTICATION') || response.includes('3DS')) {
                            status = '3DS';
                        } else if (response.includes('CHARGED')) {
                            status = 'CHARGED';
                        } else if (response.includes('APPROVED')) {
                            status = 'APPROVED';
                        } else {
                            status = 'DECLINED';
                        }
                    }
                    message = response;
                }

                if (status === 'ERROR' || status === 'DECLINED') {
                    // Retry with next site
                    activeRequests--;
                    setTimeout(() => checkCardWithSites(card, sites, siteIndex + 1), 100);
                    return;
                }

                const cardEntry = { response: message, displayCard: card.displayCard, site: usedSite };
                if (status === 'CHARGED') {
                    chargedCards.push(cardEntry);
                    sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                } else if (status === 'APPROVED') {
                    approvedCards.push(cardEntry);
                    sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                } else if (status === '3DS') {
                    threeDSCards.push(cardEntry);
                    sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
                } else {
                    declinedCards.push(cardEntry);
                    sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                }

                addResult({ displayCard: card.displayCard }, status, message, true);
                updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
                $('#autoStatusLog').text(`Processed ${chargedCards.length + approvedCards.length + threeDSCards.length + declinedCards.length} of ${totalCards} cards`);
                activeRequests--;
            },
            error: function(xhr) {
                if (xhr.statusText !== 'abort') {
                    // Treat AJAX errors as DECLINED and retry with next site
                    const message = xhr.responseText || 'Request failed';
                    activeRequests--;
                    setTimeout(() => checkCardWithSites(card, sites, siteIndex + 1), 100);
                } else {
                    activeRequests--;
                }
            }
        });
    }

    async function processNext() {
        if (!isProcessing || isStopping || cardQueue.length === 0) {
            if (activeRequests === 0 && cardQueue.length === 0) {
                finishAutoProcessing();
            }
            return;
        }

        if (validCards.length >= 3 && activeRequests >= MAX_CONCURRENT) {
            setTimeout(processNext, 10);
            return;
        }

        const card = cardQueue.shift();
        activeRequests++;
        await checkCardWithSites(card, sites);
        setTimeout(processNext, 0);
    }

    cardQueue = [...validCards];
    if (validCards.length < 3) {
        // Sequential processing for 1 or 2 cards
        for (const card of cardQueue) {
            if (!isProcessing || isStopping) break;
            await checkCardWithSites(card, sites);
        }
        if (activeRequests === 0 && cardQueue.length === 0) {
            finishAutoProcessing();
        }
    } else {
        // Parallel processing for 3+ cards
        for (let i = 0; i < Math.min(MAX_CONCURRENT, cardQueue.length); i++) {
            setTimeout(processNext, i * 50);
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
    $('#cardInput').val('');
    updateCardCount();
    $('#statusLog').text('Processing completed.');
    Swal.fire({
        title: 'Processing complete!',
        text: 'All cards have been checked. See the results below.',
        icon: 'success',
        confirmButtonColor: '#ec4899'
    });
}

function finishAutoProcessing() {
    isProcessing = false;
    isStopping = false;
    abortControllers.forEach(controller => controller.abort());
    abortControllers = [];
    cardQueue = [];
    $('#autoStartBtn').prop('disabled', false);
    $('#autoStopBtn').prop('disabled', true);
    $('#autoLoader').hide();
    $('#autoCardInput').val('');
    $('#sitesInput').val('');
    updateAutoCardCount();
    updateSitesCount();
    $('#autoStatusLog').text('Processing completed.');
    Swal.fire({
        title: 'Processing complete!',
        text: 'All cards have been checked. See the results below.',
        icon: 'success',
        confirmButtonColor: '#ec4899'
    });
}

$('#startBtn').on('click', processCards);

$('#stopBtn').on('click', function() {
    if (!isProcessing || isStopping) return;

    isProcessing = false;
    isStopping = true;
    cardQueue = [];
    abortControllers.forEach(controller => controller.abort());
    abortControllers = [];
    activeRequests = 0;
    updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
    $('#startBtn').prop('disabled', false);
    $('#stopBtn').prop('disabled', true);
    $('#loader').hide();
    $('#statusLog').text('Processing stopped.');
    Swal.fire({
        title: 'Stopped!',
        text: 'Processing has been stopped',
        icon: 'warning',
        confirmButtonColor: '#ec4899'
    });
});

$('#autoStartBtn').on('click', processAutoCards);

$('#autoStopBtn').on('click', function() {
    if (!isProcessing || isStopping) return;

    isProcessing = false;
    isStopping = true;
    cardQueue = [];
    abortControllers.forEach(controller => controller.abort());
    abortControllers = [];
    activeRequests = 0;
    updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
    $('#autoStartBtn').prop('disabled', false);
    $('#autoStopBtn').prop('disabled', true);
    $('#autoLoader').hide();
    $('#autoStatusLog').text('Processing stopped.');
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

$('#autoClearBtn').on('click', function() {
    if ($('#autoCardInput').val().trim() || $('#sitesInput').val().trim()) {
        Swal.fire({
            title: 'Clear Input?', text: 'Remove all entered cards and sites',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, clear'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#autoCardInput').val('');
                $('#sitesInput').val('');
                updateAutoCardCount();
                updateSitesCount();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Cleared!', showConfirmButton: false, timer: 1500
                });
            }
        });
    }
});

$('#exportBtn').on('click', function() {
    const allCards = [...chargedCards, ...approvedCards, ...threeDSCards, ...declinedCards];
    if (allCards.length === 0) {
        Swal.fire({
            title: 'No data to export!',
            text: 'Please check some cards first.',
            icon: 'warning',
            confirmButtonColor: '#ec4899'
        });
        return;
    }
    let csvContent = "Card,Status,Response\n";
    allCards.forEach(card => {
        const status = card.response.includes('CHARGED') ? 'CHARGED' :
                      card.response.includes('APPROVED') ? 'APPROVED' :
                      card.response.includes('3DS') ? '3DS' : 'DECLINED';
        csvContent += `${card.displayCard},${status},${card.response}\n`;
    });
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `card_results_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: 'Exported!', showConfirmButton: false, timer: 1500
    });
});

$('#autoExportBtn').on('click', function() {
    const allCards = [
        ...chargedCards.map(c => ({...c, status: 'CHARGED'})),
        ...approvedCards.map(c => ({...c, status: 'APPROVED'})),
        ...threeDSCards.map(c => ({...c, status: '3DS'})),
        ...declinedCards.map(c => ({...c, status: 'DECLINED'}))
    ];
    if (allCards.length === 0) {
        Swal.fire({
            title: 'No data to export!',
            text: 'Please check some cards first.',
            icon: 'warning',
            confirmButtonColor: '#ec4899'
        });
        return;
    }
    let csvContent = "Card,Site,Status,Response\n";
    allCards.forEach(({displayCard, site, status, response}) => {
        csvContent += `${displayCard},${site || ''},${status},${response}\n`;
    });
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `autoshopify_results_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: 'Exported!', showConfirmButton: false, timer: 1500
    });
});

$('#cardInput').on('input', updateCardCount);
$('#autoCardInput').on('input', updateAutoCardCount);
$('#sitesInput').on('input', updateSitesCount);

document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('gatewaySettings')) {
        closeGatewaySettings();
    }
});

// Mobile sidebar toggle
document.getElementById('menuToggle').addEventListener('click', function() {
    sidebarOpen = !sidebarOpen;
    document.getElementById('sidebar').classList.toggle('open', sidebarOpen);
    document.querySelector('.main-content').classList.toggle('sidebar-open', sidebarOpen);
});

function logout() {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You will be logged out and returned to the login page.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: 'Yes, logout'
    }).then((result) => {
        if (result.isConfirmed) {
            sessionStorage.clear();
            window.location.href = 'https://cardxchk.onrender.com/login.php';
        }
    });
}
</script>
</body>
</html>
