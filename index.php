<?php
session_start();

// MAINTENANCE MODE CHECK
// Maintenance flag file path - using absolute path for consistency
define('MAINTENANCE_FLAG', __DIR__ . '/maintenance.flag');

// Check if maintenance mode is active
if (file_exists(MAINTENANCE_FLAG)) {
    // Get the current script name
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Allow access to maintenance page and admin panel
    if ($current_page === 'maintenance.php' || $current_page === 'adminaccess_panel.php') {
        // Continue with normal execution
    } else {
        // Check if admin is logged in
        if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
            // Admin can continue - bypass normal user authentication
            $adminBypass = true;
        } else {
            // Redirect to maintenance page
            header("Location: /maintenance.php");
            exit();
        }
    }
}

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log session state
error_log("Checking session in index.php: " . json_encode($_SESSION));

// Check if user is authenticated OR if admin is authenticated during maintenance
 $isAdminDuringMaintenance = isset($adminBypass) && $adminBypass === true;
if (!$isAdminDuringMaintenance && (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram')) {
    error_log("Redirecting to login.php: Session missing or invalid auth_provider");
    header('Location: login.php');
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

// Get user information for display
 $userName = $_SESSION['user']['name'] ?? 'User';
 $userPhotoUrl = $_SESSION['user']['photo_url'] ?? null;
 $userUsername = $_SESSION['user']['username'] ?? null;

// Generate avatar URL if no photo is available
if (empty($userPhotoUrl)) {
    $initials = '';
    $words = explode(' ', trim($userName));
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    if (empty($initials)) $initials = 'U';
    $userPhotoUrl = 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=3b82f6&color=fff&size=64';
}

// Format username with @ symbol
 $formattedUsername = $userUsername ? '@' . $userUsername : '';
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
            /* Enhanced color palette for stats */
            --stat-total: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            --stat-online: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        [data-theme="light"] {
            --primary-bg: #f8fafc; --secondary-bg: #ffffff; --card-bg: #ffffff;
            --text-primary: #0f172a; --text-secondary: #475569; --border-color: #e2e8f0;
            /* Light mode adjustments for stats */
            --stat-total: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            --stat-online: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        body {
            font-family: Inter, sans-serif; background: var(--primary-bg);
            color: var(--text-primary); min-height: 100vh; overflow-x: hidden;
        }
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: rgba(10,14,39,0.95); backdrop-filter: blur(10px);
            padding: 0.5rem 1rem; display: flex; justify-content: space-between;
            align-items: center; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1);
            height: 50px;
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 1.2rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .navbar-brand i { font-size: 1.2rem; }
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
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.2rem 0.5rem; background: rgba(255,255,255,0.1);
            border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);
        }
        .user-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
        }
        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .user-name {
            font-weight: 600; color: #ffffff;
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap; font-size: 0.85rem;
        }
        .user-username {
            font-size: 0.7rem; color: var(--text-secondary);
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap;
        }
        .menu-toggle {
            color: #ffffff !important; font-size: 1.2rem; 
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.1);
            flex-shrink: 0; cursor: pointer;
        }
        .menu-toggle:hover { transform: scale(1.1); background: rgba(255,255,255,0.2); }
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
        
        /* Enhanced Dashboard Stats */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Welcome banner */
        .welcome-banner {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .welcome-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .welcome-text h2 {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .stats-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.2rem; 
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--card-bg); 
            border-radius: 16px; 
            padding: 1.5rem; 
            position: relative;
            transition: all 0.3s; 
            box-shadow: 0 4px 20px var(--shadow); 
            min-height: 140px;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.total::before { background: var(--stat-total); }
        .stat-card.charged::before { background: var(--stat-charged); }
        .stat-card.approved::before { background: var(--stat-approved); }
        .stat-card.threeds::before { background: var(--stat-threeds); }
        .stat-card.declined::before { background: var(--stat-declined); }
        .stat-card.checked::before { background: var(--stat-checked); }
        .stat-card.online::before { background: var(--stat-online); }
        
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 30px var(--shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 45px; 
            height: 45px; 
            border-radius: 12px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.3rem; 
            color: white;
        }
        
        .stat-card.total .stat-icon { background: var(--stat-total); }
        .stat-card.charged .stat-icon { background: var(--stat-charged); }
        .stat-card.approved .stat-icon { background: var(--stat-approved); }
        .stat-card.threeds .stat-icon { background: var(--stat-threeds); }
        .stat-card.declined .stat-icon { background: var(--stat-declined); }
        .stat-card.checked .stat-icon { background: var(--stat-checked); }
        .stat-card.online .stat-icon { background: var(--stat-online); }
        
        .stat-value { 
            font-size: 2rem; 
            font-weight: 700; 
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-secondary); 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* Fixed: Changed color for declined cards to red */
        .stat-card.declined .stat-value { color: var(--declined-red); }
        .stat-card.charged .stat-value { color: var(--success-green); }
        .stat-card.approved .stat-value { color: var(--success-green); }
        .stat-card.threeds .stat-value { color: var(--success-green); }
        .stat-card.online .stat-value { color: var(--accent-cyan); }
        
        [data-theme="light"] .stat-card.declined .stat-value { color: var(--declined-red); }
        [data-theme="light"] .stat-card.charged .stat-value { color: var(--success-green); }
        [data-theme="light"] .stat-card.approved .stat-value { color: var(--success-green); }
        [data-theme="light"] .stat-card.threeds .stat-value { color: var(--success-green); }
        [data-theme="light"] .stat-card.online .stat-value { color: var(--accent-cyan); }
        
        .stat-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
        }
        
        .stat-card.total .stat-indicator { background: rgba(118, 75, 162, 0.7); }
        .stat-card.charged .stat-indicator { background: rgba(245, 87, 108, 0.7); }
        .stat-card.approved .stat-indicator { background: rgba(0, 242, 254, 0.7); }
        .stat-card.threeds .stat-indicator { background: rgba(56, 249, 215, 0.7); }
        .stat-card.declined .stat-indicator { background: rgba(239, 68, 68, 0.7); }
        .stat-card.checked .stat-indicator { background: rgba(48, 207, 208, 0.7); }
        .stat-card.online .stat-indicator { background: rgba(79, 172, 254, 0.7); }
        
        .dashboard-bottom {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 1.5rem;
        }
        
        .recent-activity {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px var(--shadow);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
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
            color: var(--accent-green);
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        /* Custom scrollbar */
        .activity-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .activity-list::-webkit-scrollbar-track {
            background: var(--secondary-bg);
            border-radius: 3px;
        }
        
        .activity-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 3px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            border-color: var(--accent-blue);
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
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
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .activity-status {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        /* Fixed: Changed color for declined cards to red in activity feed */
        .activity-item.charged .activity-status { color: var(--success-green); }
        .activity-item.approved .activity-status { color: var(--success-green); }
        .activity-item.threeds .activity-status { color: var(--success-green); }
        .activity-item.declined .activity-status { color: var(--declined-red); }
        
        .activity-time {
            font-size: 0.7rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        
        /* Enhanced Online Users Section */
        .online-users-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px var(--shadow);
            height: fit-content;
            position: relative;
            overflow: hidden;
        }
        
        .online-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple), var(--accent-cyan));
        }
        
        .online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .online-users-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .online-users-title i {
            color: var(--accent-cyan);
        }
        
        .online-users-count {
            font-size: 0.9rem;
            color: var(--text-secondary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .online-users-count i {
            color: var(--success-green);
            font-size: 0.8rem;
        }
        
        .online-users-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        /* Custom scrollbar */
        .online-users-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .online-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg);
            border-radius: 3px;
        }
        
        .online-users-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 3px;
        }
        
        .online-user-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        .online-user-item:hover {
            transform: translateX(5px);
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        .online-user-avatar-container {
            position: relative;
        }
        
        .online-user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
        }
        
        .online-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 14px;
            height: 14px;
            background-color: var(--success-green);
            border: 2px solid var(--card-bg);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
        
        .online-user-info {
            flex: 1;
            min-width: 0;
        }
        
        .online-user-name {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
        }
        
        .online-user-username {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 0.2rem;
        }
        
        /* Hide any potential role elements */
        .online-user-role,
        .role-badge,
        .user-role {
            display: none !important;
        }
        
        .checker-section, .generator-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .checker-header, .generator-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .checker-title, .generator-title {
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .checker-title i, .generator-title i { color: var(--accent-cyan); font-size: 1rem; }
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-control {
            width: 100%; padding: 0.75rem; background: var(--secondary-bg);
            border: 1px solid var(--border-color); border-radius: 8px;
            color: var(--text-primary); font-size: 0.9rem; transition: all 0.3s;
        }
        .form-control:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        .form-row {
            display: flex; gap: 1rem; flex-wrap: wrap;
        }
        .form-col {
            flex: 1; min-width: 120px;
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
        #statusLog, #genStatusLog { margin-top: 0.5rem; color: var(--text-secondary); text-align: center; font-size: 0.8rem; }
        
        /* Fixed: Changed color for declined cards to red in results */
        .result-item.declined .stat-label { color: var(--declined-red); }
        .result-item.approved .stat-label, .result-item.charged .stat-label, .result-item.threeds .stat-label { color: var(--success-green); }
        
        .copy-btn { background: transparent; border: none; cursor: pointer; color: var(--accent-blue); font-size: 0.8rem; margin-left: auto; }
        .copy-btn:hover { color: var(--accent-purple); }
        .stat-content { display: flex; align-items: center; justify-content: space-between; }
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
        .generated-cards-container {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        .custom-select {
            position: relative;
            display: flex,
            width: 100%;
        }
        .custom-select select {
            appearance: none;
            width: 100%;
            padding: 0.75rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .custom-select select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        .custom-select::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-secondary);
        }
        .custom-input-group {
            display: flex,
            width: 100%;
        }
        .custom-input-group input {
            flex: 1;
            padding: 0.75rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px 0 0 8px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .custom-input-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        .custom-input-group .input-group-append {
            display: flex;
        }
        .custom-input-group .input-group-text {
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 8px 8px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .custom-input-group .input-group-text:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
        }
        .copy-all-btn, .clear-all-btn {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .copy-all-btn:hover, .clear-all-btn:hover {
            background: var(--accent-blue);
            color: white;
        }
        .clear-all-btn {
            border-color: var(--error);
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        .clear-all-btn:hover {
            background: var(--error);
            color: white;
        }
        .results-actions {
            display: flex,
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Hide any undefined or empty elements */
        .online-user-info:empty,
        .online-user-username:empty,
        .online-user-name:empty,
        [data-undefined],
        [undefined] {
            display: none !important;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            body { font-size: 14px; }
            .navbar { 
                padding: 0.4rem 0.6rem; 
                height: 48px;
            }
            .navbar-brand { 
                font-size: 1rem; 
                margin-left: 0.5rem;
            }
            .navbar-brand i { font-size: 1rem; }
            .user-avatar { width: 24px; height: 24px; }
            .user-name { 
                max-width: 60px; 
                font-size: 0.75rem;
            }
            .user-username {
                max-width: 60px;
                font-size: 0.65rem;
            }
            .sidebar { width: 75vw; }
            .page-title { font-size: 1.2rem; }
            .page-subtitle { font-size: 0.8rem; }
            .dashboard-content {
                flex-direction: column;
            }
            .dashboard-bottom {
                grid-template-columns: 1fr;
            }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
            .stat-card { padding: 1rem; min-height: 100px; }
            .stat-icon { width: 32px; height: 32px; font-size: 1rem; }
            .stat-value { font-size: 1.4rem; }
            .stat-label { font-size: 0.7rem; }
            .welcome-banner { padding: 1rem; }
            .welcome-icon { width: 40px; height: 40px; font-size: 1.2rem; }
            .welcome-text h2 { font-size: 1.2rem; }
            .welcome-text p { font-size: 0.8rem; }
            .checker-section, .generator-section { padding: 0.75rem; }
            .checker-title, .generator-title { font-size: 1rem; }
            .checker-title i, .generator-title i { font-size: 0.8rem; }
            .settings-btn { padding: 0.2rem 0.4rem; font-size: 0.7rem; }
            .input-label { font-size: 0.8rem; }
            .card-textarea { min-height: 100px; padding: 0.5rem; font-size: 0.8rem; }
            .btn { padding: 0.4rem 0.8rem; min-width: 80px; font-size: 0.8rem; }
            .results-section { padding: 0.75rem; }
            .results-title { font-size: 1rem; }
            .results-title i { font-size: 0.8rem; }
            .filter-btn { padding: 0.2rem 0.4rem; font-size: 0.6rem; }
            .generated-cards-container { max-height: 200px; font-size: 0.7rem; padding: 0.5rem; }
            .copy-all-btn, .clear-all-btn { padding: 0.3rem 0.6rem; font-size: 0.7rem; }
            .form-row { flex-direction: column; gap: 0.5rem; }
            .form-col { min-width: 100%; }
            .settings-content { max-width: 95vw; }
            .gateway-option { padding: 0.5rem; }
            .gateway-option-name { font-size: 0.8rem; }
            .gateway-option-desc { font-size: 0.65rem; }
            .menu-toggle {
                position: absolute;
                left: 0.5rem;
                top: 50%;
                transform: translateY(-50%);
                width: 32px;
                height: 32px;
            }
            .navbar-brand {
                margin-left: 2.2rem;
            }
            .theme-toggle {
                width: 32px;
                height: 16px;
            }
            .theme-toggle-slider {
                width: 12px;
                height: 12px;
                left: 2px;
            }
            [data-theme="light"] .theme-toggle-slider { transform: translateX(14px); }
            .user-info {
                padding: 0.1rem 0.3rem;
                gap: 0.3rem;
            }
            .online-users-section {
                margin-top: 1rem;
            }
            .online-users-list {
                max-height: 250px;
            }
            .online-user-avatar {
                width: 38px;
                height: 38px;
            }
            .online-user-name {
                font-size: 0.85rem;
            }
        }
        
        /* For very small screens */
        @media (max-width: 480px) {
            .navbar { padding: 0.3rem 0.5rem; }
            .navbar-brand { font-size: 0.9rem; }
            .user-avatar { width: 22px; height: 22px; }
            .user-name { 
                max-width: 50px; 
                font-size: 0.7rem;
            }
            .user-username {
                max-width: 50px;
                font-size: 0.6rem;
            }
            .menu-toggle { width: 30px; height: 30px; font-size: 1rem; }
            .sidebar { width: 85vw; }
            .page-title { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
            .stat-card { padding: 0.8rem; min-height: 90px; }
            .stat-value { font-size: 1.2rem; }
            .stat-label { font-size: 0.65rem; }
            .btn { padding: 0.35rem 0.7rem; min-width: 70px; font-size: 0.75rem; }
        }
    </style>
</head>
<body data-theme="light">
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
                <img src="<?php echo htmlspecialchars($userPhotoUrl); ?>" alt="Profile" class="user-avatar">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                    <?php if (!empty($formattedUsername)): ?>
                        <span class="user-username"><?php echo htmlspecialchars($formattedUsername); ?></span>
                    <?php endif; ?>
                </div>
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
                <a class="sidebar-link" onclick="showPage('generator'); closeSidebar()">
                    <i class="fas fa-magic"></i><span>Card Generator</span>
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
            <div class="dashboard-container">
                <div class="welcome-banner">
                    <div class="welcome-content">
                        <div class="welcome-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="welcome-text">
                            <h2>Dashboard Overview</h2>
                            <p>Track your card checking performance</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-content">
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

                    <div class="dashboard-bottom">
                        <div class="recent-activity">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <i class="fas fa-history"></i> Recent Activity
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
                        
                        <div class="online-users-section">
                            <div class="online-users-header">
                                <div class="online-users-title">
                                    <i class="fas fa-users"></i> Online Users
                                </div>
                                <div class="online-users-count" id="onlineUsersCount">
                                    <i class="fas fa-circle"></i>
                                    <span id="onlineCount">0</span> online
                                </div>
                            </div>
                            <div class="online-users-list" id="onlineUsersList">
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <h3>No Users Online</h3>
                                    <p>No other users are currently online</p>
                                </div>
                            </div>
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

        <section class="page-section" id="page-generator">
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑮𝑬𝑵𝑬𝑹𝑨𝑻𝑶𝑹</h1>
            <p class="page-subtitle">𝐆𝐞𝐧𝐞𝐫𝐚𝐭𝐞 𝐯𝐚𝐥𝐢𝐝 𝐜𝐫𝐞𝐝𝐢𝐭 𝐜𝐚𝐫𝐝𝐬 𝐰𝐢𝐭𝐡 𝐋𝐮𝐡𝐧 𝐜𝐡𝐞𝐜𝐤𝐬𝐮𝐦</p>

            <div class="generator-section">
                <div class="generator-header">
                    <div class="generator-title">
                        <i class="fas fa-magic"></i> Card Generator
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
                        <i class="fas fa-magic"></i> Generate Cards
                    </button>
                    <button class="btn btn-secondary" id="clearGenBtn">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
                <div class="loader" id="genLoader"></div>
                <div id="genStatusLog" class="text-sm text-gray-500 mt-2"></div>
            </div>

            <div class="results-section" id="generatorResults">
                <div class="results-header">
                    <div class="results-title">
                        <i class="fas fa-list"></i> Generated Cards
                    </div>
                    <div class="results-actions">
                        <button class="copy-all-btn" id="copyAllBtn" style="display: none;">
                            <i class="fas fa-copy"></i> Copy All
                        </button>
                        <button class="clear-all-btn" id="clearAllBtn" style="display: none;">
                            <i class="fas fa-trash"></i> Clear
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
                            <div class="gateway-option-desc">Payment processing with $1 charge</div>
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
                        <input type="radio" name="gateway" value="gate/paypal0.1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-paypal"></i> PayPal
                                <span class="gateway-badge badge-auth">0.1$ Auth</span>
                            </div>
                            <div class="gateway-option-desc">Authorization only, no charge</div>
                        </div>
                    </label>
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

    <script src="index.js?v=1.0.3"></script>
</body>
</html>
