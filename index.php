<?php
require_once 'maintenance_check.php';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MAINTENANCE MODE CHECK
// Maintenance flag file path - using /tmp/.maintenance as specified
define('MAINTENANCE_FLAG', '/tmp/.maintenance');

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
            /* Light theme colors */
            --primary-bg: #f8fafc; 
            --secondary-bg: #ffffff; 
            --card-bg: #ffffff;
            --text-primary: #0f172a; 
            --text-secondary: #475569; 
            --border-color: #e2e8f0;
            --accent-blue: #3b82f6; 
            --accent-cyan: #06b6d4;
            --accent-green: #10b981; 
            --error: #ef4444; 
            --warning: #f59e0b; 
            --shadow: rgba(0,0,0,0.1);
            --success-green: #22c55e; 
            --declined-red: #ef4444;
            
            /* Enhanced color palette for stats - same in both modes */
            --stat-total: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            --stat-online: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        [data-theme="dark"] {
            /* Dark theme colors - beast level */
            --primary-bg: #0a0e1a;
            --secondary-bg: #141824;
            --card-bg: #1a1f2e;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #2a3142;
            --accent-blue: #4a9eff; 
            --accent-cyan: #00d4ff;
            --accent-green: #10b981; 
            --error: #ef4444; 
            --warning: #f59e0b; 
            --shadow: rgba(0,0,0,0.5);
            --success-green: #22c55e; 
            --declined-red: #ef4444;
            
            /* Shining effects for dark mode */
            --shine-1: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 50%, rgba(255,255,255,0) 100%);
            --shine-2: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 50%, rgba(255,255,255,0) 100%);
            --glow-blue: 0 0 20px rgba(74,158,255,0.3);
            --glow-cyan: 0 0 20px rgba(0,212,255,0.3);
        }
        body {
            font-family: Inter, sans-serif; background: var(--primary-bg);
            color: var(--text-primary); min-height: 100vh; overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Dark mode background with shining effect */
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0a0e1a 0%, #141824 100%);
            position: relative;
        }
        
        [data-theme="dark"] body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(74,158,255,0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(0,212,255,0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(139,92,246,0.1) 0%, transparent 50%);
            z-index: -1;
            animation: shimmer 20s infinite linear;
        }
        
        @keyframes shimmer {
            0% { opacity: 0.3; }
            50% { opacity: 0.5; }
            100% { opacity: 0.3; }
        }
        
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: var(--card-bg); backdrop-filter: blur(10px);
            padding: 0.5rem 1rem; display: flex; justify-content: space-between;
            align-items: center; z-index: 1000; border-bottom: 1px solid var(--border-color);
            height: 50px;
            box-shadow: var(--shadow);
        }
        
        /* Dark mode navbar with shining effect */
        [data-theme="dark"] .navbar {
            background: linear-gradient(135deg, rgba(26,31,46,0.9) 0%, rgba(20,24,36,0.9) 100%);
            backdrop-filter: blur(15px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .navbar-brand {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 1.2rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        
        /* Dark mode navbar brand with glow effect */
        [data-theme="dark"] .navbar-brand {
            text-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        .navbar-brand i { font-size: 1.2rem; }
        .navbar-actions { display: flex; align-items: center; gap: 0.5rem; }
        .theme-toggle {
            width: 40px; height: 20px; background: var(--secondary-bg);
            border-radius: 10px; cursor: pointer; border: 1px solid var(--border-color);
            position: relative; transition: all 0.3s;
        }
        
        /* Dark mode theme toggle with glow effect */
        [data-theme="dark"] .theme-toggle {
            background: linear-gradient(135deg, #2a3142 0%, #1a1f2e 100%);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .theme-toggle-slider {
            position: absolute; width: 16px; height: 16px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            left: 2px; transition: transform 0.3s; display: flex;
            align-items: center; justify-content: center; color: white; font-size: 0.5rem;
        }
        
        /* Dark mode theme toggle slider with glow effect */
        [data-theme="dark"] .theme-toggle-slider {
            box-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        [data-theme="light"] .theme-toggle-slider { transform: translateX(18px); }
        .user-info {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.2rem 0.5rem; background: var(--secondary-bg);
            border-radius: 8px; border: 1px solid var(--border-color);
        }
        
        /* Dark mode user info with glow effect */
        [data-theme="dark"] .user-info {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            box-shadow: var(--glow-blue);
        }
        
        .user-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
        }
        
        /* Dark mode user avatar with glow effect */
        [data-theme="dark"] .user-avatar {
            box-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .user-name {
            font-weight: 600; color: var(--text-primary);
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap; font-size: 0.85rem;
        }
        .user-username {
            font-size: 0.7rem; color: var(--text-secondary);
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap;
        }
        .menu-toggle {
            color: var(--text-primary) !important; font-size: 1.2rem; 
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px; background: var(--secondary-bg);
            flex-shrink: 0; cursor: pointer;
            border: 1px solid var(--border-color);
        }
        
        /* Dark mode menu toggle with glow effect */
        [data-theme="dark"] .menu-toggle {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            box-shadow: var(--glow-blue);
        }
        
        .menu-toggle:hover { transform: scale(1.1); background: var(--accent-blue); color: white !important; }
        .sidebar {
            position: fixed; left: 0; top: 50px; bottom: 0; width: 70vw;
            background: var(--card-bg); border-right: 1px solid var(--border-color);
            padding: 1rem 0; z-index: 999; overflow-y: auto;
            transform: translateX(-100%); transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        /* Dark mode sidebar with shining effect */
        [data-theme="dark"] .sidebar {
            background: linear-gradient(135deg, rgba(26,31,46,0.95) 0%, rgba(20,24,36,0.95) 100%);
            backdrop-filter: blur(15px);
            box-shadow: 4px 0 20px rgba(0,0,0,0.3);
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-menu { 
            list-style: none; 
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .sidebar-item { margin: 0.3rem 0.5rem; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.75rem; color: var(--text-secondary);
            border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: all 0.3s;
        }
        
        /* Dark mode sidebar link with hover effect */
        [data-theme="dark"] .sidebar-link:hover {
            background: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 100%);
            box-shadow: var(--glow-blue);
        }
        
        .sidebar-link:hover {
            background: rgba(59,130,246,0.1); color: var(--accent-blue);
            transform: translateX(5px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
        }
        
        /* Dark mode active sidebar link with glow effect */
        [data-theme="dark"] .sidebar-link.active {
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode page title with glow effect */
        [data-theme="dark"] .page-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
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
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        /* Dark mode welcome banner with shining effect */
        [data-theme="dark"] .welcome-banner {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-2);
            animation: shine 8s infinite linear;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            20% { left: 100%; }
            100% { left: 100%; }
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        /* Dark mode welcome icon with glow effect */
        [data-theme="dark"] .welcome-icon {
            box-shadow: var(--glow-blue);
        }
        
        .welcome-text h2 {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Dark mode welcome text with glow effect */
        [data-theme="dark"] .welcome-text h2 {
            text-shadow: 0 0 10px rgba(74,158,255,0.5);
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
            box-shadow: var(--shadow); 
            min-height: 140px;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        /* Dark mode stat card with shining effect */
        [data-theme="dark"] .stat-card {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
            animation-delay: calc(var(--i) * 0.5s);
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
        
        /* Dark mode stat card hover with glow effect */
        [data-theme="dark"] .stat-card:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
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
        
        /* Dark mode stat icon with glow effect */
        [data-theme="dark"] .stat-icon {
            box-shadow: 0 0 15px rgba(255,255,255,0.2);
        }
        
        .stat-value { 
            font-size: 2rem; 
            font-weight: 700; 
            margin-bottom: 0.5rem;
            line-height: 1;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            color: var(--text-secondary); 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }
        
        /* Fixed: Changed color for declined cards to red */
        .stat-card.declined .stat-value { color: var(--declined-red); }
        .stat-card.charged .stat-value { color: var(--success-green); }
        .stat-card.approved .stat-value { color: var(--success-green); }
        .stat-card.threeds .stat-value { color: var(--success-green); }
        .stat-card.online .stat-value { color: var(--accent-cyan); }
        
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
            grid-template-columns: 1.5fr 1fr; /* Adjusted ratio to make global stats more compact */
            gap: 1.5rem;
        }
        
        /* Global Statistics Section - Updated for better visibility and shining effect */
        .gs-panel{
            border-radius:16px; padding:14px 14px 16px; /* Reduced padding for more compact look */
            background: var(--card-bg);
            border:1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        /* Dark mode global stats panel with shining effect */
        [data-theme="dark"] .gs-panel {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .gs-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-2);
            animation: shine 8s infinite linear;
        }
        
        .gs-head{display:flex;align-items:center;gap:10px;margin-bottom:12px; /* Reduced margin */}
        .gs-chip{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;
            background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.25)}
        .gs-title{font-weight:600;color:var(--text-primary)}
        .gs-sub{font-size:12px;color:var(--text-secondary);margin-top:2px}
        .gs-grid{display:grid;gap:14px} /* Reduced gap */
        @media (min-width:640px){.gs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1280px){.gs-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}

        .gs-card{
            position:relative;border-radius:12px;padding:14px 12px; /* Reduced padding */
            border:1px solid var(--border-color);
            box-shadow: var(--shadow);
            color:var(--text-primary);
            overflow: hidden;
        }
        
        /* Dark mode global stat card with shining effect */
        [data-theme="dark"] .gs-card {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .gs-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
            animation-delay: calc(var(--i) * 0.5s);
        }
        
        .gs-card .gs-icon{
            width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;
            margin-bottom:8px;border:1px solid var(--border-color)
        }
        .gs-card .gs-icon svg{width:16px;height:16px;display:block;opacity:.95}
        .gs-num{font-weight:800;font-size:24px;line-height:1} /* Reduced font size */
        .gs-label{font-size:11px;color:var(--text-secondary);margin-top:4px} /* Reduced font size */
        
        /* Enhanced colors for global stats with better visibility */
        .gs-blue   { 
            background:linear-gradient(135deg, rgba(59,130,246,0.3), rgba(37,99,235,0.2));
            border: 1px solid rgba(59,130,246,0.3);
        }
        .gs-green  { 
            background:linear-gradient(135deg, rgba(16,185,129,0.3), rgba(5,150,105,0.2));
            border: 1px solid rgba(16,185,129,0.3);
        }
        .gs-red    { 
            background:linear-gradient(135deg, rgba(239,68,68,0.3), rgba(220,38,38,0.2));
            border: 1px solid rgba(239,68,68,0.3);
        }
        .gs-purple { 
            background:linear-gradient(135deg, rgba(139,92,246,0.3), rgba(124,58,237,0.2));
            border: 1px solid rgba(139,92,246,0.3);
        }
        
        /* Dark mode colors for stat cards with enhanced visibility */
        [data-theme="dark"] .gs-blue   { 
            background:linear-gradient(135deg, rgba(74,158,255,0.4), rgba(37,99,235,0.3));
            border: 1px solid rgba(74,158,255,0.4);
            box-shadow: 0 0 15px rgba(74,158,255,0.2);
        }
        [data-theme="dark"] .gs-green  { 
            background:linear-gradient(135deg, rgba(16,185,129,0.4), rgba(5,150,105,0.3));
            border: 1px solid rgba(16,185,129,0.4);
            box-shadow: 0 0 15px rgba(16,185,129,0.2);
        }
        [data-theme="dark"] .gs-red    { 
            background:linear-gradient(135deg, rgba(239,68,68,0.4), rgba(220,38,38,0.3));
            border: 1px solid rgba(239,68,68,0.4);
            box-shadow: 0 0 15px rgba(239,68,68,0.2);
        }
        [data-theme="dark"] .gs-purple { 
            background:linear-gradient(135deg, rgba(139,92,246,0.4), rgba(124,58,237,0.3));
            border: 1px solid rgba(139,92,246,0.4);
            box-shadow: 0 0 15px rgba(139,92,246,0.2);
        }
        
        /* Enhanced Online Users Section - Expanded */
        .online-users-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            height: fit-content;
            position: relative;
            overflow: hidden;
        }
        
        /* Dark mode online users section with shining effect */
        [data-theme="dark"] .online-users-section {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .online-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-2);
            animation: shine 8s infinite linear;
        }
        
        .online-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
        }
        
        /* Dark mode online users section header with glow effect */
        [data-theme="dark"] .online-users-section::before {
            box-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        .online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .online-users-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Dark mode online users title with glow effect */
        [data-theme="dark"] .online-users-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
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
        
        /* Dark mode online users count with glow effect */
        [data-theme="dark"] .online-users-count {
            background: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 100%);
            box-shadow: var(--glow-blue);
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
            position: relative;
            z-index: 1;
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
        
        /* Dark mode scrollbar with glow effect */
        [data-theme="dark"] .online-users-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode online user item with shining effect */
        [data-theme="dark"] .online-user-item {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        [data-theme="dark"] .online-user-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
            animation-delay: calc(var(--i) * 0.5s);
        }
        
        .online-user-item:hover {
            transform: translateX(5px);
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        /* Dark mode online user item hover with glow effect */
        [data-theme="dark"] .online-user-item:hover {
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode online user avatar with glow effect */
        [data-theme="dark"] .online-user-avatar {
            box-shadow: 0 0 10px rgba(74,158,255,0.5);
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
        
        /* Dark mode online indicator with glow effect */
        [data-theme="dark"] .online-indicator {
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
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
            box-shadow: var(--shadow);
        }
        
        /* Dark mode sections with shining effect */
        [data-theme="dark"] .checker-section, 
        [data-theme="dark"] .generator-section {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .checker-section::before, 
        [data-theme="dark"] .generator-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
        }
        
        .checker-header, .generator-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .checker-title, .generator-title {
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        /* Dark mode section titles with glow effect */
        [data-theme="dark"] .checker-title, 
        [data-theme="dark"] .generator-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .checker-title i, .generator-title i { color: var(--accent-cyan); font-size: 1rem; }
        .settings-btn {
            padding: 0.3rem 0.6rem; border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-primary);
            cursor: pointer; font-weight: 500; display: flex;
            align-items: center; gap: 0.3rem; font-size: 0.8rem;
        }
        
        /* Dark mode settings button with glow effect */
        [data-theme="dark"] .settings-btn {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .settings-btn:hover {
            border-color: var(--accent-blue); color: var(--accent-blue);
            transform: translateY(-2px);
        }
        
        /* Dark mode settings button hover with glow effect */
        [data-theme="dark"] .settings-btn:hover {
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode textarea with glow effect */
        [data-theme="dark"] .card-textarea {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .card-textarea:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        
        /* Dark mode textarea focus with glow effect */
        [data-theme="dark"] .card-textarea:focus {
            box-shadow: 0 0 0 2px rgba(74,158,255,0.3);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        .form-control {
            width: 100%; padding: 0.75rem; background: var(--secondary-bg);
            border: 1px solid var(--border-color); border-radius: 8px;
            color: var(--text-primary); font-size: 0.9rem; transition: all 0.3s;
        }
        
        /* Dark mode form control with glow effect */
        [data-theme="dark"] .form-control {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .form-control:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        
        /* Dark mode form control focus with glow effect */
        [data-theme="dark"] .form-control:focus {
            box-shadow: 0 0 0 2px rgba(74,158,255,0.3);
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
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
        }
        
        /* Dark mode primary button with glow effect */
        [data-theme="dark"] .btn-primary {
            box-shadow: var(--glow-blue);
        }
        
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); color: var(--text-primary);
        }
        
        /* Dark mode secondary button with glow effect */
        [data-theme="dark"] .btn-secondary {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .btn-secondary:hover { transform: translateY(-2px); }
        
        /* Dark mode secondary button hover with glow effect */
        [data-theme="dark"] .btn-secondary:hover {
            box-shadow: var(--glow-blue);
        }
        
        .results-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }
        
        /* Dark mode results section with shining effect */
        [data-theme="dark"] .results-section {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .results-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
        }
        
        .results-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .results-title {
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        /* Dark mode results title with glow effect */
        [data-theme="dark"] .results-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .results-title i { color: var(--accent-green); font-size: 1rem; }
        .results-filters { display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.3rem 0.6rem; border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-secondary);
            cursor: pointer; font-size: 0.7rem; transition: all 0.3s;
        }
        
        /* Dark mode filter button with glow effect */
        [data-theme="dark"] .filter-btn {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .filter-btn:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .filter-btn.active {
            background: var(--accent-blue); border-color: var(--accent-blue); color: white;
        }
        
        /* Dark mode active filter button with glow effect */
        [data-theme="dark"] .filter-btn.active {
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode settings popup with shining effect */
        [data-theme="dark"] .settings-content {
            background: linear-gradient(135deg, rgba(26,31,46,0.95) 0%, rgba(20,24,36,0.95) 100%);
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
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
        
        /* Dark mode settings title with glow effect */
        [data-theme="dark"] .settings-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .settings-close {
            width: 25px; height: 25px; border-radius: 6px; border: none;
            background: var(--secondary-bg); color: var(--text-secondary);
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 0.9rem; transition: all 0.3s;
        }
        
        /* Dark mode settings close button with glow effect */
        [data-theme="dark"] .settings-close {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
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
            position: relative;
        }
        
        /* Dark mode gateway option with shining effect */
        [data-theme="dark"] .gateway-option {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        [data-theme="dark"] .gateway-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
            animation-delay: calc(var(--i) * 0.5s);
        }
        
        .gateway-option:hover {
            border-color: var(--accent-blue); transform: translateX(3px);
        }
        
        /* Dark mode gateway option hover with glow effect */
        [data-theme="dark"] .gateway-option:hover {
            box-shadow: var(--glow-blue);
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
        .badge-maintenance {
            background-color: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 5px;
        }
        .settings-footer {
            display: flex; gap: 0.5rem; margin-top: 1rem;
            padding-top: 0.5rem; border-top: 1px solid var(--border-color);
        }
        .btn-save {
            flex: 1; padding: 0.5rem; border-radius: 8px; border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white; font-weight: 600; cursor: pointer; font-size: 0.9rem;
        }
        
        /* Dark mode save button with glow effect */
        [data-theme="dark"] .btn-save {
            box-shadow: var(--glow-blue);
        }
        
        .btn-save:hover { transform: translateY(-2px); }
        .btn-cancel {
            flex: 1; padding: 0.5rem; border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-primary);
            font-weight: 600; cursor: pointer; font-size: 0.9rem;
        }
        
        /* Dark mode cancel button with glow effect */
        [data-theme="dark"] .btn-cancel {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .btn-cancel:hover { transform: translateY(-2px); }
        
        /* Dark mode cancel button hover with glow effect */
        [data-theme="dark"] .btn-cancel:hover {
            box-shadow: var(--glow-blue);
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
            margin-top: auto;
            margin-bottom: 1rem;
        }
        
        /* Dark mode logout button with glow effect */
        [data-theme="dark"] .sidebar-link.logout {
            background: linear-gradient(135deg, rgba(239,68,68,0.2) 0%, rgba(220,38,38,0.1) 100%);
            border: 1px solid rgba(239,68,68,0.3);
        }
        
        .sidebar-link.logout:hover {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            transform: translateX(5px);
        }
        
        /* Dark mode logout button hover with glow effect */
        [data-theme="dark"] .sidebar-link.logout:hover {
            box-shadow: 0 0 10px rgba(239,68,68,0.3);
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
        
        /* Dark mode generated cards container with glow effect */
        [data-theme="dark"] .generated-cards-container {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
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
        
        /* Dark mode select with glow effect */
        [data-theme="dark"] .custom-select select {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .custom-select select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Dark mode select focus with glow effect */
        [data-theme="dark"] .custom-select select:focus {
            box-shadow: 0 0 0 2px rgba(74,158,255,0.3);
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
        
        /* Dark mode input with glow effect */
        [data-theme="dark"] .custom-input-group input {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        .custom-input-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Dark mode input focus with glow effect */
        [data-theme="dark"] .custom-input-group input:focus {
            box-shadow: 0 0 0 2px rgba(74,158,255,0.3);
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
        
        /* Dark mode input group text with glow effect */
        [data-theme="dark"] .custom-input-group .input-group-text {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
            border-left: none;
        }
        
        .custom-input-group .input-group-text:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
        }
        
        /* Dark mode input group text hover with glow effect */
        [data-theme="dark"] .custom-input-group .input-group-text:hover {
            background: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 100%);
            box-shadow: var(--glow-blue);
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
        
        /* Dark mode copy/clear buttons with glow effect */
        [data-theme="dark"] .copy-all-btn {
            background: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 100%);
            border: 1px solid rgba(74,158,255,0.3);
        }
        
        [data-theme="dark"] .clear-all-btn {
            background: linear-gradient(135deg, rgba(239,68,68,0.2) 0%, rgba(220,38,38,0.1) 100%);
            border: 1px solid rgba(239,68,68,0.3);
        }
        
        .copy-all-btn:hover, .clear-all-btn:hover {
            background: var(--accent-blue);
            color: white;
        }
        
        /* Dark mode copy/clear buttons hover with glow effect */
        [data-theme="dark"] .copy-all-btn:hover {
            box-shadow: var(--glow-blue);
        }
        
        [data-theme="dark"] .clear-all-btn:hover {
            box-shadow: 0 0 10px rgba(239,68,68,0.3);
        }
        
        .clear-all-btn {
            border-color: var(--error);
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        .results-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Profile Page Styles - BEAST LEVEL */
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        /* Profile Header with Glassmorphism */
        .profile-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(6, 182, 212, 0.1));
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        /* Dark mode profile header with shining effect */
        [data-theme="dark"] .profile-header {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-2);
            animation: shine 8s infinite linear;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
            animation: gradientShift 5s ease infinite;
        }
        
        /* Dark mode profile header gradient with glow effect */
        [data-theme="dark"] .profile-header::before {
            box-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar-container {
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent-blue);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            transition: transform 0.3s ease;
        }
        
        /* Dark mode profile avatar with glow effect */
        [data-theme="dark"] .profile-avatar {
            box-shadow: 0 0 25px rgba(74,158,255,0.5);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-status {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background-color: var(--success-green);
            border: 3px solid var(--card-bg);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        /* Dark mode profile status with glow effect */
        [data-theme="dark"] .profile-status {
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }
        
        /* Dark mode profile name with glow effect */
        [data-theme="dark"] .profile-name {
            text-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        
        .profile-username {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        .profile-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .profile-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            backdrop-filter: blur(5px);
        }
        
        .badge-member {
            background: rgba(59, 130, 246, 0.2);
            color: var(--accent-blue);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        /* Dark mode member badge with glow effect */
        [data-theme="dark"] .badge-member {
            background: linear-gradient(135deg, rgba(74,158,255,0.2) 0%, rgba(0,212,255,0.1) 100%);
            border: 1px solid rgba(74,158,255,0.3);
            box-shadow: var(--glow-blue);
        }
        
        .badge-active {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success-green);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        /* Dark mode active badge with glow effect */
        [data-theme="dark"] .badge-active {
            background: linear-gradient(135deg, rgba(16,185,129,0.2) 0%, rgba(5,150,105,0.1) 100%);
            border: 1px solid rgba(16,185,129,0.3);
            box-shadow: 0 0 10px rgba(16,185,129,0.3);
        }
        
        /* Compact Stats Section */
        .profile-stats-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        /* Dark mode profile stats container with shining effect */
        [data-theme="dark"] .profile-stats-container {
            background: linear-gradient(135deg, rgba(26,31,46,0.8) 0%, rgba(20,24,36,0.8) 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .profile-stats-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
        }
        
        .profile-stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .profile-stats-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Dark mode profile stats title with glow effect */
        [data-theme="dark"] .profile-stats-title {
            text-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .profile-stats-title i {
            color: var(--accent-cyan);
        }
        
        /* User Stats Column Layout - Updated to match online users style */
        .user-stats-column {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            position: relative;
            z-index: 1;
        }
        
        .user-stat-item {
            display: flex;
            align-items: center;
            padding: 0.8rem;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        /* Dark mode user stat item with shining effect */
        [data-theme="dark"] .user-stat-item {
            background: linear-gradient(135deg, rgba(42,49,66,0.8) 0%, rgba(26,31,46,0.8) 100%);
            border: 1px solid rgba(74,158,255,0.2);
        }
        
        [data-theme="dark"] .user-stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--shine-1);
            animation: shine 8s infinite linear;
            animation-delay: calc(var(--i) * 0.5s);
        }
        
        .user-stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 4px;
            border-radius: 4px 0 0 4px;
        }
        
        .user-stat-item.total::before { background: var(--stat-total); }
        .user-stat-item.charged::before { background: var(--stat-charged); }
        .user-stat-item.approved::before { background: var(--stat-approved); }
        .user-stat-item.threeds::before { background: var(--stat-threeds); }
        .user-stat-item.declined::before { background: var(--stat-declined); }
        
        .user-stat-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Dark mode user stat item hover with glow effect */
        [data-theme="dark"] .user-stat-item:hover {
            box-shadow: var(--glow-blue);
        }
        
        .user-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-right: 1rem;
        }
        
        .user-stat-item.total .user-stat-icon { background: var(--stat-total); }
        .user-stat-item.charged .user-stat-icon { background: var(--stat-charged); }
        .user-stat-item.approved .user-stat-icon { background: var(--stat-approved); }
        .user-stat-item.threeds .user-stat-icon { background: var(--stat-threeds); }
        .user-stat-item.declined .user-stat-icon { background: var(--stat-declined); }
        
        /* Dark mode user stat icon with glow effect */
        [data-theme="dark"] .user-stat-icon {
            box-shadow: 0 0 15px rgba(255,255,255,0.2);
        }
        
        .user-stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .user-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .user-stat-item.total .user-stat-value { color: var(--accent-purple); }
        .user-stat-item.charged .user-stat-value { color: var(--success-green); }
        .user-stat-item.approved .user-stat-value { color: var(--success-green); }
        .user-stat-item.threeds .user-stat-value { color: var(--success-green); }
        .user-stat-item.declined .user-stat-value { color: var(--declined-red); }
        
        /* Hide any potential role elements */
        .profile-role,
        .role-badge,
        .user-role {
            display: none !important;
        }
        
        /* Hide any undefined or empty elements */
        .profile-info:empty,
        .profile-name:empty,
        .profile-username:empty,
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
            
            /* Profile page mobile adjustments */
            .profile-header {
                padding: 1.5rem;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-name {
                font-size: 2rem;
            }
            
            .profile-username {
                font-size: 1rem;
            }
            
            .user-stats-column {
                gap: 0.6rem;
            }
            
            .user-stat-item {
                padding: 0.8rem;
            }
            
            .user-stat-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .user-stat-value {
                font-size: 1.3rem;
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
            
            /* Profile page for very small screens */
            .profile-header {
                padding: 1rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-name {
                font-size: 1.8rem;
            }
            
            .profile-username {
                font-size: 0.9rem;
            }
            
            .user-stats-column {
                gap: 0.5rem;
            }
            
            .user-stat-item {
                padding: 0.6rem;
            }
            
            .user-stat-icon {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .user-stat-value {
                font-size: 1.2rem;
            }
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
                <a class="sidebar-link" onclick="showPage('profile'); closeSidebar()">
                    <i class="fas fa-user"></i><span>My Profile</span>
                </a>
            </li>
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

                    <!-- Global Statistics Section - Updated for better visibility -->
                    <div class="dashboard-bottom">
                        <div class="gs-panel mt-6">
                            <div class="gs-head">
                                <div class="gs-chip">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M5 3h2v18H5V3zm6 6h2v12h-2V9zm6-4h2v16h-2V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="gs-title">Global Statistics</div>
                                    <div class="gs-sub">Platform-wide performance metrics</div>
                                </div>
                            </div>

                            <div class="gs-grid">
                                <div class="gs-card gs-blue">
                                    <div class="gs-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.89 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                        </svg>
                                    </div>
                                    <div id="gTotalUsers" class="gs-num">—</div>
                                    <div class="gs-label">Total Users</div>
                                </div>

                                <div class="gs-card gs-purple">
                                    <div class="gs-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                                        </svg>
                                    </div>
                                    <div id="gTotalHits" class="gs-num">—</div>
                                    <div class="gs-label">Total Checked Cards</div>
                                </div>

                                <div class="gs-card gs-red">
                                    <div class="gs-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
                                        </svg>
                                    </div>
                                    <div id="gChargeCards" class="gs-num">—</div>
                                    <div class="gs-label">Charge Cards</div>
                                </div>

                                <div class="gs-card gs-green">
                                    <div class="gs-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 13h3l2-6 4 12 2-6h5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div id="gLiveCards" class="gs-num">—</div>
                                    <div class="gs-label">Live Cards</div>
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
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑬𝑬𝑬𝑹</h1>
            <p class="page-subtitle">𝐂𝐡𝐞𝐜𝐤 𝐲𝐨𝐮𝐫 𝐜𝐚𝐫𝐝𝐬 𝐨𝐧 𝐦𝐮𝐥𝐭𝐢𝐥𝐥</p>

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
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑮𝑬𝑵𝑬𝑨𝑻𝑶</h1>
            <p class="page-subtitle">𝐆𝐞𝐧𝐫𝐚 𝐯𝐚𝐥𝐥𝐥𝐥𝐬 𝐰𝐢𝐢𝐥𝐥𝐥 𝐰𝐢𝐡𝐡𝐧𝐡</p>

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

        <section class="page-section" id="page-profile">
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">View your account information and statistics</p>
            
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-info">
                        <div class="profile-avatar-container">
                            <img id="profileAvatar" src="" alt="Profile" class="profile-avatar">
                            <div class="profile-status"></div>
                        </div>
                        <div class="profile-details">
                            <h2 class="profile-name" id="profileName"></h2>
                            <p class="profile-username" id="profileUsername"></p>
                            <div class="profile-badges">
                                <span class="profile-badge badge-member">
                                    <i class="fas fa-user-check"></i> Member
                                </span>
                                <span class="profile-badge badge-active">
                                    <i class="fas fa-circle"></i> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-stats-container">
                    <div class="profile-stats-header">
                        <h2 class="profile-stats-title">
                            <i class="fas fa-chart-bar"></i> My Statistics
                        </h2>
                    </div>
                    
                    <div class="user-stats-column">
                        <div class="user-stat-item total">
                            <div class="user-stat-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">Total Checked</div>
                                <div class="user-stat-value" id="profile-total-value">0</div>
                            </div>
                        </div>
                        
                        <div class="user-stat-item charged">
                            <div class="user-stat-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">Charged</div>
                                <div class="user-stat-value" id="profile-charged-value">0</div>
                            </div>
                        </div>
                        
                        <div class="user-stat-item approved">
                            <div class="user-stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">Approved</div>
                                <div class="user-stat-value" id="profile-approved-value">0</div>
                            </div>
                        </div>
                        
                        <div class="user-stat-item threeds">
                            <div class="user-stat-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">3DS</div>
                                <div class="user-stat-value" id="profile-threeds-value">0</div>
                            </div>
                        </div>
                        
                        <div class="user-stat-item declined">
                            <div class="user-stat-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">Declined</div>
                                <div class="user-stat-value" id="profile-declined-value">0</div>
                            </div>
                        </div>
                    </div>
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
                        <input type="radio" name="gateway" value="gate/stripe5$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-stripe"></i> Stripe
                                <span class="gateway-badge badge-charge">5$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Payment processing with $5 charge</div>
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
                        <input type="radio" name="gateway" value="gate/paypal0.1$.php">
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <i class="fab fa-paypal"></i> PayPal
                                <span class="gateway-badge badge-charge">0.1$ Charge</span>
                            </div>
                            <div class="gateway-option-desc">Payment processing with $0.1 charge</div>
                        </div>
                    </label>
                    <label class="gateway-option" id="razorpay-gateway">
                        <input type="radio" name="gateway" value="gate/razorpay0.10$.php" disabled>
                        <div class="gateway-option-content">
                            <div class="gateway-option-name">
                                <img src="https://cdn.razorpay.com/logo.svg" alt="Razorpay" 
                                    style="width:15px; height:15px; object-fit:contain;">Razorpay
                                <span class="gateway-badge badge-charge">0.10$ Charge</span>
                                <span class="gateway-badge badge-maintenance">Under Maintenance</span>
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

    <script src="indeex.js?v=<?= time(); ?>"></script>
    
    <script>
        // Disable Razorpay 0.10$ gateway and show maintenance popup
        document.addEventListener('DOMContentLoaded', function() {
            const razorpayGateway = document.querySelector('input[name="gateway"][value="gate/razorpay0.10$.php"]');
            if (razorpayGateway) {
                // Disable the radio button
                razorpayGateway.disabled = true;
                
                // Find the parent label
                const parentLabel = razorpayGateway.closest('label');
                if (parentLabel) {
                    // Add visual styling to show it's disabled
                    parentLabel.style.opacity = '0.6';
                    parentLabel.style.cursor = 'not-allowed';
                    parentLabel.style.position = 'relative';
                    
                    // Add click event to show popup
                    parentLabel.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Gateway Under Maintenance',
                                text: 'The Razorpay gateway is currently undergoing maintenance. Please select another gateway.',
                                icon: 'error',
                                confirmButtonColor: '#ef4444', // Red color
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Gateway under maintenance. Please select another gateway.');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
