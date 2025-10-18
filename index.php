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
    header('Location: http://cxchk.site/login.php');
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
            /* Dark Theme Colors */
            --primary-bg: #0f0f23;
            --secondary-bg: #1a1a2e;
            --card-bg: #16213e;
            --accent-bg: #0f3460;
            --text-primary: #ffffff;
            --text-secondary: #a8b2d1;
            --border-color: #2a2a4a;
            --shadow: rgba(0, 0, 0, 0.4);
            
            /* Accent Colors */
            --primary-accent: #00d9ff;
            --secondary-accent: #ee00ff;
            --success: #00ff88;
            --warning: #ffaa00;
            --error: #ff0055;
            --info: #0099ff;
            
            /* Stat Colors */
            --stat-total: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        [data-theme="light"] {
            /* Light Theme Colors */
            --primary-bg: #f8f9fa;
            --secondary-bg: #ffffff;
            --card-bg: #ffffff;
            --accent-bg: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --shadow: rgba(0, 0, 0, 0.1);
            
            /* Adjusted stat colors for light mode */
            --stat-total: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #0099cc 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #00cc99 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #ff9900 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #0066cc 100%);
        }
        
        body {
            font-family: Inter, sans-serif;
            background: var(--primary-bg);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Navbar Styles */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--secondary-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            z-index: 1000;
            box-shadow: 0 2px 10px var(--shadow);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }
        
        .navbar-brand i {
            font-size: 1.5rem;
            color: var(--primary-accent);
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .theme-toggle {
            width: 50px;
            height: 24px;
            background: var(--accent-bg);
            border-radius: 12px;
            cursor: pointer;
            border: 1px solid var(--border-color);
            position: relative;
            transition: all 0.3s;
        }
        
        .theme-toggle-slider {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            left: 2px;
            top: 2px;
            transition: transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.6rem;
        }
        
        [data-theme="light"] .theme-toggle-slider {
            transform: translateX(24px);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.8rem;
            background: var(--accent-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-accent);
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .menu-toggle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        
        .menu-toggle:hover {
            background: var(--primary-accent);
            color: white;
            transform: scale(1.05);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--secondary-bg);
            border-right: 1px solid var(--border-color);
            min-height: 100vh;
            position: fixed;
            top: 60px;
            left: 0;
            z-index: 999;
            box-shadow: 2px 0 10px var(--shadow);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .sidebar-logo i {
            font-size: 1.3rem;
            color: var(--primary-accent);
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-item {
            margin: 0.25rem 0.75rem;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-accent);
            transform: scaleY(0);
            transition: transform 0.3s;
        }
        
        .sidebar-link:hover {
            background: var(--accent-bg);
            color: var(--primary-accent);
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), rgba(238, 0, 255, 0.1));
            color: var(--primary-accent);
            font-weight: 600;
        }
        
        .sidebar-link.active::before {
            transform: scaleY(1);
        }
        
        .sidebar-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1rem 1.5rem;
        }
        
        .sidebar-link.logout {
            color: var(--error);
            margin-top: 1rem;
        }
        
        .sidebar-link.logout:hover {
            background: rgba(255, 0, 85, 0.1);
            color: var(--error);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            margin-top: 60px;
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .page-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .page-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-size: 1rem;
            font-weight: 400;
        }
        
        /* Dashboard Styles */
        .dashboard-container {
            display: grid;
            gap: 1.5rem;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--accent-bg), var(--card-bg));
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 217, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 5px 15px rgba(0, 217, 255, 0.3);
        }
        
        .welcome-text h2 {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .stat-card.total::before { background: var(--stat-total); }
        .stat-card.charged::before { background: var(--stat-charged); }
        .stat-card.approved::before { background: var(--stat-approved); }
        .stat-card.threeds::before { background: var(--stat-threeds); }
        .stat-card.declined::before { background: var(--stat-declined); }
        .stat-card.checked::before { background: var(--stat-checked); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px var(--shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card.total .stat-icon { background: var(--stat-total); }
        .stat-card.charged .stat-icon { background: var(--stat-charged); }
        .stat-card.approved .stat-icon { background: var(--stat-approved); }
        .stat-card.threeds .stat-icon { background: var(--stat-threeds); }
        .stat-card.declined .stat-icon { background: var(--stat-declined); }
        .stat-card.checked .stat-icon { background: var(--stat-checked); }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-card.total .stat-value { color: #764ba2; }
        .stat-card.charged .stat-value { color: #f5576c; }
        .stat-card.approved .stat-value { color: #00f2fe; }
        .stat-card.threeds .stat-value { color: #38f9d7; }
        .stat-card.declined .stat-value { color: #fee140; }
        .stat-card.checked .stat-value { color: #30cfd0; }
        
        [data-theme="light"] .stat-card.total .stat-value { color: #764ba2; }
        [data-theme="light"] .stat-card.charged .stat-value { color: #f5576c; }
        [data-theme="light"] .stat-card.approved .stat-value { color: #0099cc; }
        [data-theme="light"] .stat-card.threeds .stat-value { color: #00cc99; }
        [data-theme="light"] .stat-card.declined .stat-value { color: #ff9900; }
        [data-theme="light"] .stat-card.checked .stat-value { color: #0066cc; }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .stat-indicator {
            position: absolute;
            bottom: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .stat-card.total .stat-indicator { background: rgba(118, 75, 162, 0.7); }
        .stat-card.charged .stat-indicator { background: rgba(245, 87, 108, 0.7); }
        .stat-card.approved .stat-indicator { background: rgba(0, 242, 254, 0.7); }
        .stat-card.threeds .stat-indicator { background: rgba(56, 249, 215, 0.7); }
        .stat-card.declined .stat-indicator { background: rgba(254, 225, 64, 0.7); }
        .stat-card.checked .stat-indicator { background: rgba(48, 207, 208, 0.7); }
        
        .recent-activity {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow);
            margin-top: 1.5rem;
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .activity-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .activity-title i {
            color: var(--primary-accent);
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--accent-bg);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            border-color: var(--primary-accent);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        .activity-item.charged .activity-icon { background: var(--stat-charged); }
        .activity-item.approved .activity-icon { background: var(--stat-approved); }
        .activity-item.threeds .activity-icon { background: var(--stat-threeds); }
        .activity-item.declined .activity-icon { background: var(--stat-declined); }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-card {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: var(--text-primary);
        }
        
        .activity-status {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .activity-item.charged .activity-status { color: #f5576c; }
        .activity-item.approved .activity-status { color: #00f2fe; }
        .activity-item.threeds .activity-status { color: #38f9d7; }
        .activity-item.declined .activity-status { color: #fee140; }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        
        /* Checker Section */
        .checker-section, .generator-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow);
        }
        
        .checker-header, .generator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .checker-title, .generator-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .checker-title i, .generator-title i {
            color: var(--primary-accent);
            font-size: 1.3rem;
        }
        
        .settings-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--accent-bg);
            color: var(--text-primary);
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .settings-btn:hover {
            border-color: var(--primary-accent);
            color: var(--primary-accent);
            transform: translateY(-2px);
        }
        
        .input-section {
            margin-bottom: 1.5rem;
        }
        
        .input-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .input-label {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .card-count {
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .card-textarea {
            width: 100%;
            min-height: 150px;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            resize: vertical;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .card-textarea:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.9rem;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .form-col {
            flex: 1;
            min-width: 150px;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 120px;
            font-size: 0.95rem;
            transition: all 0.3s;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            color: white;
            box-shadow: 0 5px 15px rgba(0, 217, 255, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 217, 255, 0.4);
        }
        
        .btn-secondary {
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }
        
        .results-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .results-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .results-title i {
            color: var(--success);
            font-size: 1.3rem;
        }
        
        .results-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--accent-bg);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .filter-btn:hover {
            border-color: var(--primary-accent);
            color: var(--primary-accent);
        }
        
        .filter-btn.active {
            background: var(--primary-accent);
            border-color: var(--primary-accent);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        /* Settings Popup */
        .settings-popup {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .settings-popup.active {
            display: flex;
        }
        
        .settings-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            max-width: 90vw;
            width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .settings-title {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .settings-close {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            border: none;
            background: var(--accent-bg);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .settings-close:hover {
            background: var(--error);
            color: white;
            transform: rotate(90deg);
        }
        
        .gateway-group {
            margin-bottom: 1.5rem;
        }
        
        .gateway-group-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }
        
        .gateway-options {
            display: grid;
            gap: 1rem;
        }
        
        .gateway-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .gateway-option:hover {
            border-color: var(--primary-accent);
            transform: translateX(5px);
        }
        
        .gateway-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 1rem;
            cursor: pointer;
            accent-color: var(--primary-accent);
        }
        
        .gateway-option-content {
            flex: 1;
        }
        
        .gateway-option-name {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.3rem;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .gateway-option-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .gateway-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-charge {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .badge-auth {
            background: rgba(6, 182, 212, 0.15);
            color: var(--info);
        }
        
        .settings-footer {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .btn-save {
            flex: 1;
            padding: 0.8rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            color: white;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            flex: 1;
            padding: 0.8rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--accent-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .loader {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--primary-accent);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #statusLog, #genStatusLog {
            margin-top: 1rem;
            color: var(--text-secondary);
            text-align: center;
            font-size: 0.9rem;
        }
        
        .result-item {
            background: var(--accent-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .result-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--shadow);
        }
        
        .result-item.declined {
            border-left: 4px solid var(--error);
        }
        
        .result-item.approved, .result-item.charged, .result-item.threeds {
            border-left: 4px solid var(--success);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .result-card {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .result-status {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            text-transform: uppercase;
        }
        
        .result-item.declined .result-status {
            background: rgba(255, 0, 85, 0.1);
            color: var(--error);
        }
        
        .result-item.approved .result-status,
        .result-item.charged .result-status,
        .result-item.threeds .result-status {
            background: rgba(0, 255, 136, 0.1);
            color: var(--success);
        }
        
        .result-response {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .copy-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--primary-accent);
            font-size: 0.9rem;
            margin-left: auto;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            color: var(--secondary-accent);
            transform: scale(1.1);
        }
        
        .sidebar-link.logout {
            color: var(--error);
            background: rgba(255, 0, 85, 0.1);
            border: 1px solid var(--error);
        }
        
        .sidebar-link.logout:hover {
            background: rgba(255, 0, 85, 0.2);
            color: var(--error);
        }
        
        .generated-cards-container {
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .custom-select {
            position: relative;
            display: flex;
            width: 100%;
        }
        
        .custom-select select {
            appearance: none;
            width: 100%;
            padding: 0.9rem;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .custom-select select:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.1);
        }
        
        .custom-select::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-secondary);
        }
        
        .custom-input-group {
            display: flex;
            width: 100%;
        }
        
        .custom-input-group input {
            flex: 1;
            padding: 0.9rem;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px 0 0 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .custom-input-group input:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.1);
        }
        
        .custom-input-group .input-group-append {
            display: flex;
        }
        
        .custom-input-group .input-group-text {
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background: var(--accent-bg);
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 12px 12px 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .custom-input-group .input-group-text:hover {
            background: rgba(0, 217, 255, 0.1);
            color: var(--primary-accent);
        }
        
        .copy-all-btn, .clear-all-btn {
            background: rgba(0, 217, 255, 0.1);
            border: 1px solid var(--primary-accent);
            color: var(--primary-accent);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .copy-all-btn:hover, .clear-all-btn:hover {
            background: var(--primary-accent);
            color: white;
        }
        
        .clear-all-btn {
            border-color: var(--error);
            color: var(--error);
            background: rgba(255, 0, 85, 0.1);
        }
        
        .clear-all-btn:hover {
            background: var(--error);
            color: white;
        }
        
        .results-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .navbar {
                height: 56px;
                padding: 0 0.75rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .navbar-brand i {
                font-size: 1.3rem;
            }
            
            .user-avatar {
                width: 28px;
                height: 28px;
            }
            
            .user-name {
                max-width: 60px;
                font-size: 0.8rem;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .sidebar {
                width: 280px;
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .sidebar-header {
                padding: 1rem;
            }
            
            .sidebar-logo {
                font-size: 1rem;
            }
            
            .sidebar-logo i {
                font-size: 1.2rem;
            }
            
            .sidebar-item {
                margin: 0.25rem 0.75rem;
            }
            
            .sidebar-link {
                padding: 0.75rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .sidebar-link i {
                font-size: 1rem;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                margin-top: 56px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .page-subtitle {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }
            
            .welcome-banner {
                padding: 1.5rem;
            }
            
            .welcome-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .welcome-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .welcome-text h2 {
                font-size: 1.4rem;
            }
            
            .welcome-text p {
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.2rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .activity-header {
                margin-bottom: 1rem;
            }
            
            .activity-title {
                font-size: 1.1rem;
            }
            
            .activity-item {
                padding: 0.75rem;
            }
            
            .activity-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .activity-card {
                font-size: 0.9rem;
            }
            
            .activity-status {
                font-size: 0.8rem;
            }
            
            .checker-section, .generator-section {
                padding: 1.2rem;
            }
            
            .checker-header, .generator-header {
                margin-bottom: 1.2rem;
            }
            
            .checker-title, .generator-title {
                font-size: 1.2rem;
            }
            
            .checker-title i, .generator-title i {
                font-size: 1.1rem;
            }
            
            .settings-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .input-label {
                font-size: 0.9rem;
            }
            
            .card-textarea {
                min-height: 120px;
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 0.7rem 1.2rem;
                min-width: 100px;
                font-size: 0.9rem;
            }
            
            .results-section {
                padding: 1.2rem;
            }
            
            .results-header {
                margin-bottom: 1.2rem;
            }
            
            .results-title {
                font-size: 1.2rem;
            }
            
            .results-title i {
                font-size: 1.1rem;
            }
            
            .filter-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .empty-state {
                padding: 2rem 0.5rem;
            }
            
            .empty-state i {
                font-size: 2.5rem;
            }
            
            .empty-state h3 {
                font-size: 1rem;
            }
            
            .generated-cards-container {
                max-height: 200px;
                font-size: 0.8rem;
                padding: 0.75rem;
            }
            
            .copy-all-btn, .clear-all-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .form-col {
                min-width: 100%;
            }
            
            .settings-content {
                max-width: 95vw;
                width: 100%;
                border-radius: 16px;
                padding: 1.2rem;
            }
            
            .settings-header {
                margin-bottom: 1.2rem;
                padding-bottom: 0.8rem;
            }
            
            .settings-title {
                font-size: 1.1rem;
            }
            
            .settings-close {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .gateway-group {
                margin-bottom: 1.2rem;
            }
            
            .gateway-group-title {
                font-size: 0.9rem;
                margin-bottom: 0.8rem;
            }
            
            .gateway-option {
                padding: 0.8rem;
            }
            
            .gateway-option input[type="radio"] {
                width: 16px;
                height: 16px;
                margin-right: 0.8rem;
            }
            
            .gateway-option-name {
                font-size: 0.9rem;
            }
            
            .gateway-option-desc {
                font-size: 0.8rem;
            }
            
            .settings-footer {
                margin-top: 1.2rem;
                padding-top: 0.8rem;
            }
            
            .btn-save, .btn-cancel {
                padding: 0.7rem;
                font-size: 0.9rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 20px;
            }
            
            .theme-toggle-slider {
                width: 16px;
                height: 16px;
                left: 2px;
            }
            
            [data-theme="light"] .theme-toggle-slider {
                transform: translateX(20px);
            }
            
            .user-info {
                padding: 0.3rem 0.6rem;
                gap: 0.5rem;
            }
        }
        
        /* For very small screens */
        @media (max-width: 480px) {
            .navbar {
                padding: 0 0.5rem;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
            
            .navbar-brand i {
                font-size: 1.1rem;
            }
            
            .user-avatar {
                width: 24px;
                height: 24px;
            }
            
            .user-name {
                max-width: 50px;
                font-size: 0.75rem;
            }
            
            .menu-toggle {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
            
            .sidebar {
                width: 85vw;
            }
            
            .page-title {
                font-size: 1.3rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.8rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .btn {
                padding: 0.6rem 1rem;
                min-width: 90px;
                font-size: 0.85rem;
            }
            
            .welcome-banner {
                padding: 1.2rem;
            }
            
            .welcome-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .welcome-text h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body data-theme="light">
    <div class="app-container">
        <nav class="navbar">
            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>
            <div class="navbar-brand">
                <i class="fas fa-credit-card"></i>
                <span class="brand-text">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</span>
            </div>
            <div class="navbar-actions">
                <div class="theme-toggle" onclick="toggleTheme()">
                    <div class="theme-toggle-slider"><i class="fas fa-sun"></i></div>
                </div>
                <div class="user-info">
                    <img src="<?php 
                        // Use Telegram profile photo if available, otherwise generate avatar
                        if (!empty($_SESSION['user']['photo_url'])) {
                            echo htmlspecialchars($_SESSION['user']['photo_url']);
                        } else {
                            // Generate avatar with initials
                            $name = $_SESSION['user']['name'] ?? 'User';
                            $initials = '';
                            $words = explode(' ', trim($name));
                            foreach ($words as $word) {
                                if (!empty($word)) {
                                    $initials .= strtoupper(substr($word, 0, 1));
                                    if (strlen($initials) >= 2) break;
                                }
                            }
                            if (empty($initials)) $initials = 'U';
                            echo 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=00d9ff&color=fff&size=64';
                        }
                    ?>" alt="Profile" class="user-avatar">
                    <span class="user-name"><?php 
                        // Display user's real name from Telegram
                        $name = $_SESSION['user']['name'] ?? 'User';
                        echo htmlspecialchars($name);
                    ?></span>
                </div>
            </div>
        </nav>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-credit-card"></i>
                    <span>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</span>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a class="sidebar-link active" onclick="showPage('home'); closeSidebar()">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" onclick="showPage('checking'); closeSidebar()">
                        <i class="fas fa-credit-card"></i>
                        <span>Card Checker</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" onclick="showPage('generator'); closeSidebar()">
                        <i class="fas fa-magic"></i>
                        <span>Card Generator</span>
                    </a>
                </li>
                <div class="sidebar-divider"></div>
                <li class="sidebar-item">
                    <a class="sidebar-link" onclick="Swal.fire('Coming Soon','More features coming soon','info'); closeSidebar()">
                        <i class="fas fa-plus"></i>
                        <span>More Features</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link logout" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <section class="page-section active" id="page-home">
                <div class="dashboard-container">
                    <div class="welcome-banner">
                        <div class="welcome-content">
                            <div class="welcome-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="welcome-text">
                                <h2>Dashboard Overview</h2>
                                <p>Track your card checking performance and statistics</p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-grid" id="statsGrid">
                        <div class="stat-card total">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                            </div>
                            <div id="total-value" class="stat-value">0</div>
                            <div class="stat-label">TOTAL</div>
                            <div class="stat-indicator"></div>
                        </div>
                        <div class="stat-card charged">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                            </div>
                            <div id="charged-value" class="stat-value">0</div>
                            <div class="stat-label">HIT|CHARGED</div>
                            <div class="stat-indicator"></div>
                        </div>
                        <div class="stat-card approved">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div id="approved-value" class="stat-value">0</div>
                            <div class="stat-label">LIVE|APPROVED</div>
                            <div class="stat-indicator"></div>
                        </div>
                        <div class="stat-card threeds">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                            </div>
                            <div id="threed-value" class="stat-value">0</div>
                            <div class="stat-label">3DS</div>
                            <div class="stat-indicator"></div>
                        </div>
                        <div class="stat-card declined">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <div id="declined-value" class="stat-value">0</div>
                            <div class="stat-label">DEAD|DECLINED</div>
                            <div class="stat-indicator"></div>
                        </div>
                        <div class="stat-card checked">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                            <div id="checked-value" class="stat-value">0 / 0</div>
                            <div class="stat-label">CHECKED</div>
                            <div class="stat-indicator"></div>
                        </div>
                    </div>

                    <div class="recent-activity">
                        <div class="activity-header">
                            <div class="activity-title">
                                <i class="fas fa-history"></i>
                                Recent Activity
                            </div>
                        </div>
                        <div class="activity-list" id="activityList">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No Activity Yet</h3>
                                <p>Start checking cards to see activity here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-section" id="page-checking">
                <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑬𝑪𝑲𝑬𝑹</h1>
                <p class="page-subtitle">𝐂𝐡𝐞𝐜𝐤 𝐲𝐨𝐮𝐫 𝐜𝐚𝐫𝐝𝐬 𝐨𝐧 𝐦𝐮𝐥𝐭𝐢𝐩𝐥𝐞 𝐠𝐚𝐭𝐞𝐰𝐚𝐲𝐬</p>

                <div class="checker-section">
                    <div class="checker-header">
                        <div class="checker-title">
                            <i class="fas fa-shield-alt"></i>
                            Card Checker
                        </div>
                        <button class="settings-btn" onclick="openGatewaySettings()">
                            <i class="fas fa-cog"></i>
                            Gateway Settings
                        </button>
                    </div>

                    <div class="input-section">
                        <div class="input-header">
                            <label class="input-label">Enter Card Details</label>
                            <span class="card-count" id="cardCount">
                                <i class="fas fa-list"></i>
                                0 valid cards detected
                            </span>
                        </div>
                        <textarea id="cardInput" class="card-textarea" 
                            placeholder="Enter card details: card|month|year|cvv&#10;Example:&#10;4532123456789012|12|2025|123"></textarea>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" id="startBtn">
                            <i class="fas fa-play"></i>
                            Start Check
                        </button>
                        <button class="btn btn-secondary" id="stopBtn" disabled>
                            <i class="fas fa-stop"></i>
                            Stop
                        </button>
                        <button class="btn btn-secondary" id="clearBtn">
                            <i class="fas fa-trash"></i>
                            Clear
                        </button>
                        <button class="btn btn-secondary" id="exportBtn">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>
                    <div class="loader" id="loader"></div>
                    <div id="statusLog" class="text-sm text-gray-500 mt-2"></div>
                </div>

                <div class="results-section" id="checkingResults">
                    <div class="results-header">
                        <div class="results-title">
                            <i class="fas fa-list-check"></i>
                            Recent Results
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

            <section class="page-section" id="page-generator">
                <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑮𝑬𝑵𝑬𝑹𝑨𝑻𝑶𝑹</h1>
                <p class="page-subtitle">𝐆𝐞𝐧𝐞𝐫𝐚𝐭𝐞 𝐯𝐚𝐥𝐢𝐝 𝐜𝐫𝐞𝐝𝐢𝐭 𝐜𝐚𝐫𝐝𝐬 𝐰𝐢𝐭𝐡 𝐋𝐮𝐡𝐧 𝐜𝐡𝐞𝐜𝐤𝐬𝐮𝐦</p>

                <div class="generator-section">
                    <div class="generator-header">
                        <div class="generator-title">
                            <i class="fas fa-magic"></i>
                            Card Generator
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">BIN (6-8 digits)</label>
                        <input type="text" id="binInput" class="form-control" placeholder="Enter BIN (e.g., 414740)" maxlength="8">
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label class="input-label">Month</label>
                            <div class="custom-select">
                                <select id="monthSelect" class="form-control">
                                    <option value="rnd">rnd</option>
                                    <option value="01">01</option>
                                    <option value="02">02</option>
                                    <option value="03">03</option>
                                    <option value="04">04</option>
                                    <option value="05">05</option>
                                    <option value="06">06</option>
                                    <option value="07">07</option>
                                    <option value="08">08</option>
                                    <option value="09">09</option>
                                    <option value="10">10</option>
                                    <option value="11">11</option>
                                    <option value="12">12</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <label class="input-label">Year</label>
                            <div class="custom-input-group">
                                <input type="text" id="yearInput" class="form-control" placeholder="Year (e.g., 30, 2030)" maxlength="4">
                                <div class="input-group-append">
                                    <span class="input-group-text" onclick="setYearRnd()">rnd</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-col">
                            <label class="input-label">CVV</label>
                            <div class="custom-input-group">
                                <input type="text" id="cvvInput" class="form-control" placeholder="CVV (e.g., 123)" maxlength="4">
                                <div class="input-group-append">
                                    <span class="input-group-text" onclick="setCvvRnd()">rnd</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Number of Cards</label>
                        <input type="number" id="numCardsInput" class="form-control" value="10" min="1" max="5000">
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" id="generateBtn">
                            <i class="fas fa-magic"></i>
                            Generate Cards
                        </button>
                        <button class="btn btn-secondary" id="clearGenBtn">
                            <i class="fas fa-trash"></i>
                            Clear
                        </button>
                    </div>
                    <div class="loader" id="genLoader"></div>
                    <div id="genStatusLog" class="text-sm text-gray-500 mt-2"></div>
                </div>

                <div class="results-section" id="generatorResults">
                    <div class="results-header">
                        <div class="results-title">
                            <i class="fas fa-list"></i>
                            Generated Cards
                        </div>
                        <div class="results-actions">
                            <button class="copy-all-btn" id="copyAllBtn" style="display: none;">
                                <i class="fas fa-copy"></i>
                                Copy All
                            </button>
                            <button class="clear-all-btn" id="clearAllBtn" style="display: none;">
                                <i class="fas fa-trash"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                    <div id="generatedCardsList" class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Cards Generated Yet</h3>
                        <p>Generate cards to see them here</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="settings-popup" id="gatewaySettings">
        <div class="settings-content">
            <div class="settings-header">
                <div class="settings-title">
                    <i class="fas fa-cog"></i>
                    Gateway Settings
                </div>
                <button class="settings-close" onclick="closeGatewaySettings()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="gateway-group">
                <div class="gateway-group-title">
                    <i class="fas fa-bolt"></i>
                    Charge Gateways
                </div>
                <div class="gateway-options">
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/stripe5$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i>
                                Stripe
                                <span class="gateway-badge badge-charge">5$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Payment processing with $5 charge</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/paypal0.1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-paypal"></i>
                                PayPal
                                <span class="gateway-badge badge-charge">0.1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Popular online payment system with minimal charge</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/shopify1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-shopify"></i>
                                Shopify
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
                                    style="width:15px; height:15px; object-fit:contain;">
                                Razorpay
                                <span class="gateway-badge badge-charge">0.10$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Indian payment gateway</div>
                        </div>
                    </label>
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/authnet1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fas fa-credit-card"></i>
                                Authnet
                                <span class="gateway-badge badge-charge">1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Authorize.net payment gateway</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="gateway-group">
                <div class="gateway-group-title">
                    <i class="fas fa-shield-alt"></i>
                    Auth Gateways
                </div>
                <div class="gateway-options">
                    <label class="gateway-option">
                        <input type="radio" name="gateway" value="gate/stripeauth.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i>
                                Stripe
                                <span class="gateway-badge badge-auth">Auth</span>
                            </div>
                            <div class="gateway-option-desc">Authorization only, no charge</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="settings-footer">
                <button class="btn-save" onclick="saveGatewaySettings()">
                    <i class="fas fa-check"></i>
                    Save Settings
                </button>
                <button class="btn-cancel" onclick="closeGatewaySettings()">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedGateway = 'gate/stripe5$.php';
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
        let threeDSCards = [];
        let declinedCards = [];
        let sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        let sidebarOpen = false;
        let generatedCardsData = [];

        // Disable copy, context menu, and dev tools, but allow pasting in the textarea
        document.addEventListener('contextmenu', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') e.preventDefault();
        });
        document.addEventListener('copy', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') e.preventDefault();
        });
        document.addEventListener('cut', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') e.preventDefault();
        });
        document.addEventListener('paste', e => {
            if (e.target.id === 'cardInput' || e.target.id === 'binInput' || e.target.id === 'cvvInput' || e.target.id === 'yearInput') {
                const pastedText = e.clipboardData.getData('text');
                const cursorPos = e.target.selectionStart;
                const textBefore = e.target.value.substring(0, cursorPos);
                const textAfter = e.target.value.substring(e.target.selectionEnd);
                e.target.value = textBefore + pastedText + textAfter;
                e.target.selectionStart = e.target.selectionEnd = cursorPos + pastedText.length;
                e.preventDefault();
                if (e.target.id === 'cardInput') updateCardCount();
            } else {
                e.preventDefault();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 85 || e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 83)) {
                if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') e.preventDefault();
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
                toast: true, 
                position: 'top-end', 
                icon: 'success',
                title: `${theme === 'light' ? 'Light' : 'Dark'} Mode`,
                showConfirmButton: false, 
                timer: 1500
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
                    icon: 'success', 
                    title: 'Gateway Updated!',
                    text: `Now using: ${gatewayName}`,
                    confirmButtonColor: '#00d9ff'
                });
                closeGatewaySettings();
            } else {
                Swal.fire({
                    icon: 'warning', 
                    title: 'No Gateway Selected',
                    text: 'Please select a gateway', 
                    confirmButtonColor: '#ffaa00'
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

        function updateStats(total, charged, approved, threeDS, declined) {
            document.getElementById('total-value').textContent = total;
            document.getElementById('charged-value').textContent = charged;
            document.getElementById('approved-value').textContent = approved;
            document.getElementById('threed-value').textContent = threeDS;
            document.getElementById('declined-value').textContent = declined;
            document.getElementById('checked-value').textContent = `${charged + approved + threeDS + declined} / ${total}`;
        }

        function addResult(card, status, response) {
            const resultsList = document.getElementById('checkingResultsList');
            if (!resultsList) return;
            
            // Remove empty state if it exists
            if (resultsList.classList.contains('empty-state')) {
                resultsList.classList.remove('empty-state');
                resultsList.innerHTML = '';
            }
            
            const cardClass = status.toLowerCase();
            const resultDiv = document.createElement('div');
            resultDiv.className = `result-item ${cardClass}`;
            
            resultDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-card">${card.displayCard}</div>
                    <div class="result-status">${status}</div>
                </div>
                <div class="result-response">${response}</div>
                <button class="copy-btn" onclick="copyToClipboard('${card.displayCard}')">
                    <i class="fas fa-copy"></i>
                </button>
            `;
            
            resultsList.insertBefore(resultDiv, resultsList.firstChild);
            
            // Add to activity feed
            addActivityItem(card, status);
        }

        function addActivityItem(card, status) {
            const activityList = document.getElementById('activityList');
            if (!activityList) return;
            
            // Remove empty state if it exists
            if (activityList.querySelector('.empty-state')) {
                activityList.innerHTML = '';
            }
            
            const activityItem = document.createElement('div');
            activityItem.className = `activity-item ${status.toLowerCase()}`;
            
            // Format time
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            activityItem.innerHTML = `
                <div class="activity-icon">
                    ${status === 'CHARGED' ? '<i class="fas fa-bolt"></i>' : 
                      status === 'APPROVED' ? '<i class="fas fa-check-circle"></i>' :
                      status === '3DS' ? '<i class="fas fa-lock"></i>' :
                      '<i class="fas fa-times-circle"></i>'}
                </div>
                <div class="activity-content">
                    <div class="activity-card">${card.displayCard}</div>
                    <div class="activity-status">${status}</div>
                </div>
                <div class="activity-time">${timeString}</div>
            `;
            
            // Add to the top of the list
            activityList.insertBefore(activityItem, activityList.firstChild);
            
            // Keep only the last 5 activities
            while (activityList.children.length > 5) {
                activityList.removeChild(activityList.lastChild);
            }
        }

        function displayGeneratedCards(cards) {
            const cardsList = document.getElementById('generatedCardsList');
            if (!cardsList) return;
            
            if (cardsList.classList.contains('empty-state')) {
                cardsList.classList.remove('empty-state');
                cardsList.innerHTML = '';
            }
            
            // Create a single container for all cards
            const cardsContainer = document.createElement('div');
            cardsContainer.className = 'generated-cards-container';
            cardsContainer.textContent = cards.join('\n');
            
            // Clear previous cards and add the new container
            cardsList.innerHTML = '';
            cardsList.appendChild(cardsContainer);
            
            // Show action buttons
            document.getElementById('copyAllBtn').style.display = 'flex';
            document.getElementById('clearAllBtn').style.display = 'flex';
            
            // Store the cards data
            generatedCardsData = cards;
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'success',
                    title: 'Copied!', 
                    showConfirmButton: false, 
                    timer: 1500
                });
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        function copyAllGeneratedCards() {
            if (generatedCardsData.length === 0) {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'warning',
                    title: 'No cards to copy', 
                    showConfirmButton: false, 
                    timer: 1500
                });
                return;
            }
            
            const allCardsText = generatedCardsData.join('\n');
            navigator.clipboard.writeText(allCardsText).then(() => {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'success',
                    title: 'All cards copied!', 
                    showConfirmButton: false, 
                    timer: 1500
                });
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        function clearAllGeneratedCards() {
            const cardsList = document.getElementById('generatedCardsList');
            if (cardsList) {
                cardsList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Cards Generated Yet</h3>
                        <p>Generate cards to see them here</p>
                    </div>
                `;
            }
            
            document.getElementById('copyAllBtn').style.display = 'none';
            document.getElementById('clearAllBtn').style.display = 'none';
            generatedCardsData = [];
            
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'success',
                title: 'Cleared!', 
                showConfirmButton: false, 
                timer: 1500
            });
        }

        function checkGeneratedCard(card) {
            // Switch to checking page and populate the card input
            showPage('checking');
            document.getElementById('cardInput').value = card;
            updateCardCount();
            
            // Scroll to the checking section
            document.getElementById('page-checking').scrollIntoView({ behavior: 'smooth' });
            
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'info',
                title: 'Card added to checker', 
                showConfirmButton: false, 
                timer: 1500
            });
        }

        function filterResults(filter) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            const items = document.querySelectorAll('.result-item');
            items.forEach(item => {
                const status = item.className.split(' ')[1];
                item.style.display = filter === 'all' || status === filter ? 'block' : 'none';
            });
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'info',
                title: `Filter: ${filter.charAt(0).toUpperCase() + filter.slice(1)}`,
                showConfirmButton: false, 
                timer: 1500
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
                console.log(`Starting request for card: ${card.displayCard}`);

                $.ajax({
                    url: selectedGateway,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 300000,
                    signal: controller.signal,
                    success: function(response) {
                        let status = 'DECLINED';
                        let message = response;
                        try {
                            const jsonResponse = JSON.parse(response);
                            if (jsonResponse.status) {
                                status = jsonResponse.status.toUpperCase();
                            }
                            message = jsonResponse.message || jsonResponse.response || response;
                            // Normalize status
                            if (status === '3D_AUTHENTICATION' || status.includes('3D') || status.includes('3DS')) {
                                status = '3DS';
                            } else if (status === 'CHARGED' || status.includes('CHARGED')) {
                                status = 'CHARGED';
                            } else if (status === 'APPROVED' || status.includes('APPROVED')) {
                                status = 'APPROVED';
                            } else {
                                status = 'DECLINED';
                            }
                        } catch (e) {
                            if (response.includes('3D_AUTHENTICATION') || response.includes('3DS') || response.includes('3D')) {
                                status = '3DS';
                            } else if (response.includes('CHARGED')) {
                                status = 'CHARGED';
                            } else if (response.includes('APPROVED')) {
                                status = 'APPROVED';
                            } else {
                                status = 'DECLINED';
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
                            setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
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
                    confirmButtonColor: '#ff0055'
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
                    confirmButtonColor: '#ff0055'
                });
                return;
            }

            if (validCards.length > 1000) {
                Swal.fire({
                    title: 'Limit exceeded!',
                    text: 'Maximum 1000 cards allowed',
                    icon: 'error',
                    confirmButtonColor: '#ff0055'
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

                    await new Promise(resolve => setTimeout(resolve, requestIndex * 500));
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

                        addResult(card, result.status, result.response);
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
                confirmButtonColor: '#00d9ff'
            });
        }

        function setYearRnd() {
            document.getElementById('yearInput').value = 'rnd';
        }

        function setCvvRnd() {
            document.getElementById('cvvInput').value = 'rnd';
        }

        function generateCards() {
            const bin = $('#binInput').val().trim();
            const month = $('#monthSelect').val();
            let year = $('#yearInput').val().trim();
            const cvv = $('#cvvInput').val().trim();
            const numCards = parseInt($('#numCardsInput').val());
            
            // Validate BIN
            if (!/^\d{6,8}$/.test(bin)) {
                Swal.fire({
                    title: 'Invalid BIN!',
                    text: 'Please enter a valid 6-8 digit BIN',
                    icon: 'error',
                    confirmButtonColor: '#ff0055'
                });
                return;
            }
            
            // Validate number of cards
            if (isNaN(numCards) || numCards < 1 || numCards > 5000) {
                Swal.fire({
                    title: 'Invalid Number!',
                    text: 'Please enter a number between 1 and 5000',
                    icon: 'error',
                    confirmButtonColor: '#ff0055'
                });
                return;
            }
            
            // Show warning for large number of cards
            if (numCards > 1000) {
                Swal.fire({
                    title: 'Large Number of Cards',
                    text: `You are about to generate ${numCards} cards. This may take a while and use significant resources. Continue?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#00d9ff',
                    cancelButtonColor: '#ff0055',
                    confirmButtonText: 'Yes, generate'
                }).then((result) => {
                    if (result.isConfirmed) {
                        continueGenerateCards(bin, month, year, cvv, numCards);
                    }
                });
            } else {
                continueGenerateCards(bin, month, year, cvv, numCards);
            }
        }
        
        function continueGenerateCards(bin, month, year, cvv, numCards) {
            // Validate year if not random
            if (year !== 'rnd') {
                // Convert two-digit year to four-digit
                if (year.length === 2) {
                    const currentYear = new Date().getFullYear();
                    const currentCentury = Math.floor(currentYear / 100) * 100;
                    const twoDigitYear = parseInt(year);
                    // If the two-digit year is less than 50, assume current century, otherwise previous century
                    year = (twoDigitYear < 50 ? currentCentury : currentCentury - 100) + twoDigitYear;
                }
                
                // Validate year is a number and between 2000 and 2099
                if (!/^\d{4}$/.test(year) || parseInt(year) < 2000 || parseInt(year) > 2099) {
                    Swal.fire({
                        title: 'Invalid Year!',
                        text: 'Please enter a valid year (e.g., 2025, 30, or "rnd")',
                        icon: 'error',
                        confirmButtonColor: '#ff0055'
                    });
                    return;
                }
            }
            
            // Validate CVV if not random
            if (cvv !== 'rnd' && !/^\d{3,4}$/.test(cvv)) {
                Swal.fire({
                    title: 'Invalid CVV!',
                    text: 'Please enter a valid 3-4 digit CVV or "rnd"',
                    icon: 'error',
                    confirmButtonColor: '#ff0055'
                });
                return;
            }
            
            // Prepare parameters
            let params = bin;
            if (month !== 'rnd') params += '|' + month;
            if (year !== 'rnd') params += '|' + year;
            if (cvv !== 'rnd') params += '|' + cvv;
            
            // Show loader
            $('#genLoader').show();
            $('#genStatusLog').text('Generating cards...');
            
            // Make AJAX request
            $.ajax({
                url: '/gate/ccgen.php',  // Updated path
                method: 'GET',
                data: {
                    bin: params,
                    num: numCards,
                    format: 0
                },
                dataType: 'json',  // Specify that we expect JSON response
                success: function(response) {
                    $('#genLoader').hide();
                    
                    // Check if response has cards property
                    if (response.cards && Array.isArray(response.cards) && response.cards.length > 0) {
                        $('#genStatusLog').text(`Generated ${response.cards.length} cards successfully!`);
                        
                        // Display all cards in a single box
                        displayGeneratedCards(response.cards);
                        
                        Swal.fire({
                            title: 'Success!',
                            text: `Generated ${response.cards.length} cards`,
                            icon: 'success',
                            confirmButtonColor: '#00d9ff'
                        });
                    } else if (response.error) {
                        // Handle error response
                        Swal.fire({
                            title: 'Error!',
                            text: response.error,
                            icon: 'error',
                            confirmButtonColor: '#ff0055'
                        });
                        $('#genStatusLog').text('Error: ' + response.error);
                    } else {
                        // Handle case where no cards were generated
                        $('#genStatusLog').text('No cards generated');
                        Swal.fire({
                            title: 'No Cards!',
                            text: 'Could not generate cards with the provided parameters',
                            icon: 'warning',
                            confirmButtonColor: '#ffaa00'
                        });
                    }
                },
                error: function(xhr) {
                    $('#genLoader').hide();
                    $('#genStatusLog').text('Error: ' + xhr.statusText);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to generate cards: ' + xhr.statusText,
                        icon: 'error',
                        confirmButtonColor: '#ff0055'
                    });
                }
            });
        }

        $('#startBtn').on('click', processCards);
        $('#generateBtn').on('click', generateCards);
        $('#copyAllBtn').on('click', copyAllGeneratedCards);
        $('#clearAllBtn').on('click', clearAllGeneratedCards);

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
                confirmButtonColor: '#ffaa00'
            });
        });

        $('#clearBtn').on('click', function() {
            if ($('#cardInput').val().trim()) {
                Swal.fire({
                    title: 'Clear Input?', 
                    text: 'Remove all entered cards',
                    icon: 'warning', 
                    showCancelButton: true,
                    confirmButtonColor: '#ff0055', 
                    confirmButtonText: 'Yes, clear'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#cardInput').val('');
                        updateCardCount();
                        Swal.fire({
                            toast: true, 
                            position: 'top-end', 
                            icon: 'success',
                            title: 'Cleared!', 
                            showConfirmButton: false, 
                            timer: 1500
                        });
                    }
                });
            }
        });

        $('#clearGenBtn').on('click', function() {
            $('#binInput').val('');
            $('#monthSelect').val('rnd');
            $('#yearInput').val('');
            $('#cvvInput').val('');
            $('#numCardsInput').val('10');
            $('#generatedCardsList').html('<div class="empty-state"><i class="fas fa-inbox"></i><h3>No Cards Generated Yet</h3><p>Generate cards to see them here</p></div>');
            $('#genStatusLog').text('');
            generatedCardsData = [];
            document.getElementById('copyAllBtn').style.display = 'none';
            document.getElementById('clearAllBtn').style.display = 'none';
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'success',
                title: 'Cleared!', 
                showConfirmButton: false, 
                timer: 1500
            });
        });

        $('#exportBtn').on('click', function() {
            const allCards = [...chargedCards, ...approvedCards, ...threeDSCards, ...declinedCards];
            if (allCards.length === 0) {
                Swal.fire({
                    title: 'No data to export!',
                    text: 'Please check some cards first.',
                    icon: 'warning',
                    confirmButtonColor: '#ffaa00'
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
                toast: true, 
                position: 'top-end', 
                icon: 'success',
                title: 'Exported!', 
                showConfirmButton: false, 
                timer: 1500
            });
        });

        $('#cardInput').on('input', updateCardCount);

        document.addEventListener('click', function(e) {
            if (e.target === document.getElementById('gatewaySettings')) {
                closeGatewaySettings();
            }
        });

        // Mobile sidebar toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            sidebarOpen = !sidebarOpen;
            document.getElementById('sidebar').classList.toggle('open', sidebarOpen);
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (sidebarOpen && window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const menuToggle = document.getElementById('menuToggle');
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    closeSidebar();
                }
            }
        });

        function logout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will be logged out and returned to the login page.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff0055',
                cancelButtonColor: '#00d9ff',
                confirmButtonText: 'Yes, logout'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Perform logout action (e.g., clear session and redirect)
                    sessionStorage.clear();
                    window.location.href = 'http://cxchk.site/login.php';
                }
            });
        }
    </script>
</body>
</html>
