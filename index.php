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
            /* Light theme colors - less whitish */
            --primary-bg: #f5f7fa; 
            --secondary-bg: #e9ecef; 
            --card-bg: #ffffff;
            --text-primary: #212529; 
            --text-secondary: #6c757d; 
            --border-color: #dee2e6;
            --accent-blue: #3b82f6; 
            --accent-cyan: #06b6d4;
            --accent-green: #10b981; 
            --error: #ef4444; 
            --warning: #f59e0b; 
            --shadow: rgba(0,0,0,0.08);
            --success-green: #22c55e; 
            --declined-red: #ef4444;
            
            /* Dark theme colors */
            --dark-primary-bg: #1a1a1a;
            --dark-secondary-bg: #2d2d2d;
            --dark-card-bg: #252525;
            --dark-text-primary: #f8f9fa;
            --dark-text-secondary: #adb5bd;
            --dark-border-color: #404040;
            --dark-shadow: rgba(0,0,0,0.3);
            
            /* Enhanced color palette for stats - same in both modes */
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            --stat-online: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        /* Smooth transition for theme switching */
        body, .navbar, .sidebar, .card, .stat-card, .gs-card, .user-stat-item, 
        .checker-section, .generator-section, .results-section, .online-users-section,
        .profile-header, .profile-stats-container, .settings-content {
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
        }
        
        body {
            font-family: Inter, sans-serif; background: var(--primary-bg);
            color: var(--text-primary); min-height: 100vh; overflow-x: hidden;
        }
        
        /* Dark mode styles */
        body[data-theme="dark"] {
            background: var(--dark-primary-bg);
            color: var(--dark-text-primary);
        }
        
        body[data-theme="dark"] .navbar {
            background: #0f0f0f;
        }
        
        body[data-theme="dark"] .sidebar {
            background: var(--dark-card-bg);
            border-right-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .sidebar-link {
            color: var(--dark-text-secondary);
        }
        
        body[data-theme="dark"] .sidebar-link:hover {
            background: rgba(59,130,246,0.1);
            color: var(--accent-blue);
        }
        
        body[data-theme="dark"] .main-content {
            background: var(--dark-primary-bg);
        }
        
        body[data-theme="dark"] .page-section {
            background: var(--dark-primary-bg);
        }
        
        body[data-theme="dark"] .welcome-banner,
        body[data-theme="dark"] .stat-card,
        body[data-theme="dark"] .gs-panel,
        body[data-theme="dark"] .online-users-section,
        body[data-theme="dark"] .top-users-section,
        body[data-theme="dark"] .checker-section,
        body[data-theme="dark"] .generator-section,
        body[data-theme="dark"] .results-section,
        body[data-theme="dark"] .profile-header,
        body[data-theme="dark"] .profile-stats-container {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--dark-shadow);
        }
        
        body[data-theme="dark"] .gs-card,
        body[data-theme="dark"] .online-user-item,
        body[data-theme="dark"] .top-user-item,
        body[data-theme="dark"] .user-stat-item {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .card-textarea,
        body[data-theme="dark"] .form-control,
        body[data-theme="dark"] .generated-cards-container {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        body[data-theme="dark"] .custom-select select,
        body[data-theme="dark"] .custom-input-group input {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        body[data-theme="dark"] .custom-input-group .input-group-text {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-secondary);
        }
        
        body[data-theme="dark"] .settings-content {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--dark-shadow);
        }
        
        body[data-theme="dark"] .gateway-option {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .settings-header,
        body[data-theme="dark"] .settings-footer {
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .page-title {
            background: linear-gradient(135deg, var(--dark-text-primary), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        body[data-theme="dark"] .page-subtitle,
        body[data-theme="dark"] .stat-label,
        body[data-theme="dark"] .gs-label,
        body[data-theme="dark"] .online-user-username,
        body[data-theme="dark"] .top-user-username,
        body[data-theme="dark"] .user-stat-label,
        body[data-theme="dark"] .gateway-option-desc {
            color: var(--dark-text-secondary);
        }
        
        body[data-theme="dark"] .sidebar-divider {
            background: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .theme-toggle {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .menu-toggle {
            background: rgba(0,0,0,0.5);
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .btn-secondary {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        body[data-theme="dark"] .filter-btn {
            background: var(--dark-secondary-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-secondary);
        }
        
        body[data-theme="dark"] .filter-btn.active {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
        }
        
        body[data-theme="dark"] .copy-all-btn,
        body[data-theme="dark"] .clear-all-btn {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        
        body[data-theme="dark"] .clear-all-btn {
            border-color: var(--error);
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        
        body[data-theme="dark"] .gs-blue,
        body[data-theme="dark"] .gs-green,
        body[data-theme="dark"] .gs-red,
        body[data-theme="dark"] .gs-purple {
            border-color: var(--dark-border-color);
        }
        
        body[data-theme="dark"] .online-users-list::-webkit-scrollbar-track,
        body[data-theme="dark"] .top-users-list::-webkit-scrollbar-track,
        body[data-theme="dark"] .generated-cards-container::-webkit-scrollbar-track {
            background: var(--dark-secondary-bg);
        }
        
        body[data-theme="dark"] .online-users-list::-webkit-scrollbar-thumb,
        body[data-theme="dark"] .top-users-list::-webkit-scrollbar-thumb,
        body[data-theme="dark"] .generated-cards-container::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
        }
        
        /* Keep gateway settings white in both themes */
        .settings-content {
            background: #ffffff !important;
            color: #212529 !important;
        }
        
        .settings-header,
        .settings-footer {
            border-color: #dee2e6 !important;
        }
        
        .gateway-option {
            background: #f8f9fa !important;
            border-color: #dee2e6 !important;
        }
        
        .gateway-option:hover {
            border-color: #3b82f6 !important;
        }
        
        .gateway-option-name {
            color: #212529 !important;
        }
        
        .gateway-option-desc {
            color: #6c757d !important;
        }
        
        .settings-title {
            color: #212529 !important;
        }
        
        .settings-close {
            background: #f8f9fa !important;
            color: #6c757d !important;
        }
        
        .settings-close:hover {
            background: #ef4444 !important;
            color: white !important;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #3b82f6, #06b6d4) !important;
            color: white !important;
        }
        
        .btn-cancel {
            background: #f8f9fa !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
        }
        
        .gateway-group-title {
            color: #212529 !important;
        }
        
        .badge-charge {
            background: rgba(245,158,11,0.15) !important;
            color: #f59e0b !important;
        }
        
        .badge-auth {
            background: rgba(6,182,212,0.15) !important;
            color: #06b6d4 !important;
        }
        
        .badge-maintenance {
            background-color: #ef4444 !important;
            color: white !important;
        }
        
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: #1a1a1a; /* Always dark header */
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem; display: flex; justify-content: space-between;
            align-items: center; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1);
            height: 50px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .navbar-brand {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 1.2rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
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
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            left: 2px; transition: transform 0.3s; display: flex;
            align-items: center; justify-content: center; color: white; font-size: 0.5rem;
        }
        
        [data-theme="dark"] .theme-toggle-slider { transform: translateX(18px); }
        .user-info {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.2rem 0.5rem; background: rgba(0,0,0,0.7); /* Always dark user info */
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
            font-weight: 600; color: #ffffff; /* Always white text */
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap; font-size: 0.85rem;
        }
        .user-username {
            font-size: 0.7rem; color: rgba(255,255,255,0.7); /* Always light text */
            max-width: 80px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap;
        }
        .menu-toggle {
            color: #ffffff !important; font-size: 1.2rem; 
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px; background: rgba(0,0,0,0.5);
            flex-shrink: 0; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.1);
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
        
        .sidebar-link:hover {
            background: rgba(59,130,246,0.1); color: var(--accent-blue);
            transform: translateX(5px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
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
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        
        /* Welcome banner */
        .welcome-banner {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
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
            position: relative;
            z-index: 1;
        }
        
        .welcome-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .welcome-text h2 {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        /* Progress Counters with Online Users - Two Column Layout */
        .dashboard-top {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
        }
        
        .stats-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 0.6rem; 
        }
        
        .stat-card {
            background: var(--card-bg); 
            border-radius: 10px; 
            padding: 0.6rem; 
            position: relative;
            transition: all 0.3s; 
            box-shadow: var(--shadow); 
            min-height: 65px; /* Further reduced from 75px */
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
            height: 3px;
        }
        
        .stat-card.charged::before { background: var(--stat-charged); }
        .stat-card.approved::before { background: var(--stat-approved); }
        .stat-card.declined::before { background: var(--stat-declined); }
        .stat-card.checked::before { background: var(--stat-checked); }
        .stat-card.online::before { background: var(--stat-online); }
        
        .stat-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 30px var(--shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.3rem; /* Reduced from 0.4rem */
            position: relative;
            z-index: 1;
        }
        
        .stat-icon {
            width: 22px; /* Reduced from 25px */
            height: 22px; /* Reduced from 25px */
            border-radius: 6px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 0.8rem; /* Reduced from 0.9rem */
            color: white;
        }
        
        .stat-card.charged .stat-icon { background: var(--stat-charged); }
        .stat-card.approved .stat-icon { background: var(--stat-approved); }
        .stat-card.declined .stat-icon { background: var(--stat-declined); }
        .stat-card.checked .stat-icon { background: var(--stat-checked); }
        .stat-card.online .stat-icon { background: var(--stat-online); }
        
        .stat-value { 
            font-size: 1.3rem; /* Reduced from 1.4rem */
            font-weight: 700; 
            margin-bottom: 0.2rem;
            line-height: 1;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            color: var(--text-secondary); 
            font-size: 0.6rem; /* Reduced from 0.65rem */
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
        .stat-card.online .stat-value { color: var(--accent-cyan); }
        
        .stat-indicator {
            position: absolute;
            bottom: 6px;
            right: 6px;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
        }
        
        .stat-card.charged .stat-indicator { background: rgba(245, 87, 108, 0.7); }
        .stat-card.approved .stat-indicator { background: rgba(0, 242, 254, 0.7); }
        .stat-card.declined .stat-indicator { background: rgba(239, 68, 68, 0.7); }
        .stat-card.checked .stat-indicator { background: rgba(48, 207, 208, 0.7); }
        .stat-card.online .stat-indicator { background: rgba(79, 172, 254, 0.7); }
        
        /* Global Statistics with Top Users - Two Column Layout */
        .dashboard-bottom {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 0.8rem;
        }
        
        /* Global Statistics Section - Line Layout */
        .gs-panel{
            border-radius:10px; padding:10px;
            background: var(--card-bg);
            border:1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .gs-head{display:flex;align-items:center;gap:8px;margin-bottom:8px}
        .gs-chip{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;
            background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.25)}
        .gs-title{font-weight:600;color:var(--text-primary);font-size:0.85rem}
        .gs-sub{font-size:10px;color:var(--text-secondary);margin-top:2px}
        .gs-grid{display:flex;gap:8px; justify-content: space-between;}

        .gs-card{
            position:relative;border-radius:8px;padding:14px 8px; /* Increased padding from 10px to 14px */
            border:1px solid var(--border-color);
            box-shadow: var(--shadow);
            color:var(--text-primary);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            min-height: 110px; /* Added min-height to increase overall height */
        }
        
        .gs-card .gs-icon{
            width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;
            margin-bottom:8px;border:1px solid var(--border-color) /* Increased margin from 6px to 8px */
        }
        .gs-card .gs-icon svg{width:14px;height:14px;display:block;opacity:.95}
        .gs-num{font-weight:800;font-size:16px;line-height:1}
        .gs-label{font-size:9px;color:var(--text-secondary);margin-top:3px}
        
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
        
        /* Enhanced Online Users Section */
        .online-users-section {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
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
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
        }
        
        .online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            position: relative;
            z-index: 1;
        }
        
        .online-users-title {
            font-size: 1rem;
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
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .online-users-count i {
            color: var(--success-green);
            font-size: 0.6rem;
        }
        
        .online-users-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 0.2rem;
            position: relative;
            z-index: 1;
        }
        
        /* Custom scrollbar */
        .online-users-list::-webkit-scrollbar {
            width: 3px;
        }
        
        .online-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg);
            border-radius: 2px;
        }
        
        .online-users-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 2px;
        }
        
        .online-user-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--secondary-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        .online-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        .online-user-avatar-container {
            position: relative;
        }
        
        .online-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
        }
        
        .online-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 8px;
            height: 8px;
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
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
        }
        
        .online-user-username {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-bottom: 0.1rem;
        }
        
        /* Hide any potential role elements */
        .online-user-role,
        .role-badge,
        .user-role {
            display: none !important;
        }
        
        /* Top Users Section */
        .top-users-section {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            height: fit-content;
            position: relative;
            overflow: hidden;
        }
        
        .top-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-purple), var(--accent-blue), var(--accent-cyan));
        }
        
        .top-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            position: relative;
            z-index: 1;
        }
        
        .top-users-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .top-users-title i {
            color: var(--accent-purple);
        }
        
        .top-users-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 0.2rem;
            position: relative;
            z-index: 1;
        }
        
        /* Custom scrollbar */
        .top-users-list::-webkit-scrollbar {
            width: 3px;
        }
        
        .top-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg);
            border-radius: 2px;
        }
        
        .top-users-list::-webkit-scrollbar-thumb {
            background: var(--accent-purple);
            border-radius: 2px;
        }
        
        .top-user-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--secondary-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        .top-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-purple);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
        }
        
        .top-user-avatar-container {
            position: relative;
        }
        
        .top-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-purple);
            flex-shrink: 0;
        }
        
        .top-user-info {
            flex: 1;
            min-width: 0;
        }
        
        .top-user-name {
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
        }
        
        .top-user-username {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-bottom: 0.1rem;
        }
        
        .top-user-hits {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 0.1rem 0.3rem;
            border-radius: 5px;
            background-color: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        .checker-section, .generator-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 0.8rem; margin-bottom: 0.8rem;
            box-shadow: var(--shadow);
        }
        
        .checker-header, .generator-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .checker-title, .generator-title {
            font-size: 1.1rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        .checker-title i, .generator-title i { color: var(--accent-cyan); font-size: 0.9rem; }
        .settings-btn {
            padding: 0.25rem 0.5rem; border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-primary);
            cursor: pointer; font-weight: 500; display: flex;
            align-items: center; gap: 0.3rem; font-size: 0.75rem;
        }
        
        .settings-btn:hover {
            border-color: var(--accent-blue); color: var(--accent-blue);
            transform: translateY(-2px);
        }
        
        .input-section { margin-bottom: 0.8rem; }
        .input-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .input-label { font-weight: 600; font-size: 0.85rem; }
        .card-textarea {
            width: 100%; min-height: 120px; background: var(--secondary-bg);
            border: 1px solid var(--border-color); border-radius: 6px;
            padding: 0.6rem; color: var(--text-primary);
            font-family: 'Courier New', monospace; resize: vertical;
            font-size: 0.85rem; transition: all 0.3s;
        }
        
        .card-textarea:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        
        .form-group {
            margin-bottom: 0.8rem;
        }
        .form-control {
            width: 100%; padding: 0.6rem; background: var(--secondary-bg);
            border: 1px solid var(--border-color); border-radius: 6px;
            color: var(--text-primary); font-size: 0.85rem; transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none; border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }
        
        .form-row {
            display: flex; gap: 0.8rem; flex-wrap: wrap;
        }
        .form-col {
            flex: 1; min-width: 100px;
        }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; }
        .btn {
            padding: 0.4rem 0.8rem; border-radius: 6px; border: none;
            font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 0.3rem; min-width: 90px;
            font-size: 0.85rem; transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
        }
        
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); color: var(--text-primary);
        }
        
        .btn-secondary:hover { transform: translateY(-2px); }
        
        .results-section {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 0.8rem; margin-bottom: 0.8rem;
            box-shadow: var(--shadow);
        }
        
        .results-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .results-title {
            font-size: 1.1rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        .results-title i { color: var(--accent-green); font-size: 0.9rem; }
        .results-filters { display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.25rem 0.5rem; border-radius: 5px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-secondary);
            cursor: pointer; font-size: 0.65rem; transition: all 0.3s;
        }
        
        .filter-btn:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .filter-btn.active {
            background: var(--accent-blue); border-color: var(--accent-blue); color: white;
        }
        
        .empty-state {
            text-align: center; padding: 1.2rem 0.5rem; color: var(--text-secondary);
        }
        .empty-state i { font-size: 1.8rem; margin-bottom: 0.4rem; opacity: 0.3; }
        .empty-state h3 { font-size: 0.9rem; margin-bottom: 0.2rem; }
        .settings-popup {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            display: none; align-items: center; justify-content: center; z-index: 10000;
        }
        .settings-popup.active { display: flex; }
        .settings-content {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 0.8rem; max-width: 90vw; width: 90%;
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
            margin-bottom: 0.8rem; padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .settings-title {
            font-size: 1rem; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        .settings-close {
            width: 22px; height: 22px; border-radius: 5px; border: none;
            background: var(--secondary-bg); color: var(--text-secondary);
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 0.8rem; transition: all 0.3s;
        }
        
        .settings-close:hover {
            background: var(--error); color: white; transform: rotate(90deg);
        }
        .gateway-group { margin-bottom: 0.8rem; }
        .gateway-group-title {
            font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;
            display: flex; align-items: center; gap: 0.3rem;
        }
        .gateway-options { display: grid; gap: 0.5rem; }
        .gateway-option {
            display: flex; align-items: center; padding: 0.5rem;
            background: var(--secondary-bg); border: 1px solid var(--border-color);
            border-radius: 6px; cursor: pointer; transition: all 0.3s;
            position: relative;
        }
        
        .gateway-option:hover {
            border-color: var(--accent-blue); transform: translateX(3px);
        }
        
        .gateway-option input[type="radio"] {
            width: 14px; height: 14px; margin-right: 0.5rem;
            cursor: pointer; accent-color: var(--accent-blue);
        }
        .gateway-option-content { flex: 1; }
        .gateway-option-name {
            font-weight: 600; display: flex; align-items: center;
            gap: 0.3rem; margin-bottom: 0.2rem; font-size: 0.85rem;
        }
        .gateway-option-desc { font-size: 0.65rem; color: var(--text-secondary); }
        .gateway-badge {
            padding: 0.15rem 0.4rem; border-radius: 3px;
            font-size: 0.55rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-charge { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-auth { background: rgba(6,182,212,0.15); color: var(--accent-cyan); }
        .badge-maintenance {
            background-color: #ef4444;
            color: white;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 4px;
        }
        .settings-footer {
            display: flex; gap: 0.5rem; margin-top: 0.8rem;
            padding-top: 0.5rem; border-top: 1px solid var(--border-color);
        }
        .btn-save {
            flex: 1; padding: 0.4rem; border-radius: 6px; border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white; font-weight: 600; cursor: pointer; font-size: 0.85rem;
        }
        
        .btn-save:hover { transform: translateY(-2px); }
        .btn-cancel {
            flex: 1; padding: 0.4rem; border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); color: var(--text-primary);
            font-weight: 600; cursor: pointer; font-size: 0.85rem;
        }
        
        .btn-cancel:hover { transform: translateY(-2px); }
        
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
        #statusLog, #genStatusLog { margin-top: 0.5rem; color: var(--text-secondary); text-align: center; font-size: 0.75rem; }
        
        /* Fixed: Changed color for declined cards to red in results */
        .result-item.declined .stat-label { color: var(--declined-red); }
        .result-item.approved .stat-label, .result-item.charged .stat-label, .result-item.threeds .stat-label { color: var(--success-green); }
        
        .copy-btn { background: transparent; border: none; cursor: pointer; color: var(--accent-blue); font-size: 0.75rem; margin-left: auto; }
        .copy-btn:hover { color: var(--accent-purple); }
        .stat-content { display: flex; align-items: center; justify-content: space-between; }
        .sidebar-link.logout {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            margin-top: auto;
            margin-bottom: 1rem;
        }
        
        .sidebar-link.logout:hover {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            transform: translateX(5px);
        }
        
        .generated-cards-container {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.6rem;
            max-height: 250px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
        }
        
        .custom-select {
            position: relative;
            display: flex,
            width: 100%;
        }
        .custom-select select {
            appearance: none;
            width: 100%;
            padding: 0.6rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.85rem;
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
            right: 0.6rem;
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
            padding: 0.6rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px 0 0 6px;
            color: var(--text-primary);
            font-size: 0.85rem;
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
            padding: 0 0.6rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 6px 6px 0;
            color: var(--text-secondary);
            font-size: 0.85rem;
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
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.75rem;
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
        .results-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Profile Page Styles - BEAST LEVEL */
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        
        /* Profile Header with Glassmorphism */
        .profile-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(6, 182, 212, 0.1));
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
            animation: gradientShift 5s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar-container {
            position: relative;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-blue);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-status {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background-color: var(--success-green);
            border: 2px solid var(--card-bg);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.4rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }
        
        .profile-username {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 0.8rem;
        }
        .profile-badges {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        
        .profile-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 16px;
            font-size: 0.75rem;
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
        
        .badge-active {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success-green);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        /* Compact Stats Section */
        .profile-stats-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .profile-stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .profile-stats-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-stats-title i {
            color: var(--accent-cyan);
        }
        
        /* User Stats Column Layout - Updated to match online users style */
        .user-stats-column {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            position: relative;
            z-index: 1;
        }
        
        .user-stat-item {
            display: flex;
            align-items: center;
            padding: 0.6rem;
            background: var(--secondary-bg);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        .user-stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 3px;
            border-radius: 3px 0 0 3px;
        }
        
        .user-stat-item.charged::before { background: var(--stat-charged); }
        .user-stat-item.approved::before { background: var(--stat-approved); }
        .user-stat-item.declined::before { background: var(--stat-declined); }
        
        .user-stat-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .user-stat-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            margin-right: 0.8rem;
        }
        
        .user-stat-item.charged .user-stat-icon { background: var(--stat-charged); }
        .user-stat-item.approved .user-stat-icon { background: var(--stat-approved); }
        .user-stat-item.declined .user-stat-icon { background: var(--stat-declined); }
        
        .user-stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .user-stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .user-stat-item.charged .user-stat-value { color: var(--success-green); }
        .user-stat-item.approved .user-stat-value { color: var(--success-green); }
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
            
            /* Mobile layout for dashboard */
            .dashboard-top {
                grid-template-columns: 1fr;
            }
            .dashboard-bottom {
                grid-template-columns: 1fr;
            }
            
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .stat-card { padding: 0.6rem; min-height: 60px; }
            .stat-icon { width: 20px; height: 20px; font-size: 0.7rem; }
            .stat-value { font-size: 1.1rem; }
            .stat-label { font-size: 0.55rem; }
            .welcome-banner { padding: 0.6rem; }
            .welcome-icon { width: 30px; height: 30px; font-size: 0.9rem; }
            .welcome-text h2 { font-size: 1rem; }
            .welcome-text p { font-size: 0.75rem; }
            .checker-section, .generator-section { padding: 0.6rem; }
            .checker-title, .generator-title { font-size: 0.9rem; }
            .checker-title i, .generator-title i { font-size: 0.7rem; }
            .settings-btn { padding: 0.2rem 0.3rem; font-size: 0.65rem; }
            .input-label { font-size: 0.75rem; }
            .card-textarea { min-height: 90px; padding: 0.4rem; font-size: 0.75rem; }
            .btn { padding: 0.35rem 0.7rem; min-width: 70px; font-size: 0.75rem; }
            .results-section { padding: 0.6rem; }
            .results-title { font-size: 0.9rem; }
            .results-title i { font-size: 0.7rem; }
            .filter-btn { padding: 0.2rem 0.3rem; font-size: 0.6rem; }
            .generated-cards-container { max-height: 180px; font-size: 0.65rem; padding: 0.4rem; }
            .copy-all-btn, .clear-all-btn { padding: 0.25rem 0.5rem; font-size: 0.65rem; }
            .form-row { flex-direction: column; gap: 0.5rem; }
            .form-col { min-width: 100%; }
            .settings-content { max-width: 95vw; }
            .gateway-option { padding: 0.4rem; }
            .gateway-option-name { font-size: 0.75rem; }
            .gateway-option-desc { font-size: 0.6rem; }
            .menu-toggle {
                position: absolute;
                left: 0.5rem;
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 30px;
            }
            .navbar-brand {
                margin-left: 2rem;
            }
            .theme-toggle {
                width: 30px;
                height: 15px;
            }
            .theme-toggle-slider {
                width: 11px;
                height: 11px;
                left: 2px;
            }
            [data-theme="light"] .theme-toggle-slider { transform: translateX(13px); }
            .user-info {
                padding: 0.1rem 0.2rem;
                gap: 0.2rem;
            }
            .online-users-section, .top-users-section {
                margin-top: 0.5rem;
            }
            .online-users-list, .top-users-list {
                max-height: 180px;
            }
            .online-user-avatar, .top-user-avatar {
                width: 25px;
                height: 25px;
            }
            .online-user-name, .top-user-name {
                font-size: 0.75rem;
            }
            
            /* Profile page mobile adjustments */
            .profile-header {
                padding: 1.2rem;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-name {
                font-size: 1.6rem;
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
            .menu-toggle { width: 28px; height: 28px; font-size: 0.9rem; }
            .sidebar { width: 85vw; }
            .page-title { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.4rem; }
            .stat-card { padding: 0.5rem; min-height: 55px; }
            .stat-value { font-size: 1rem; }
            .stat-label { font-size: 0.5rem; }
            .btn { padding: 0.3rem 0.6rem; min-width: 60px; font-size: 0.7rem; }
            
            /* Profile page for very small screens */
            .profile-header {
                padding: 1rem;
            }
            
            .profile-avatar {
                width: 70px;
                height: 70px;
            }
            
            .profile-name {
                font-size: 1.4rem;
            }
            
            .profile-username {
                font-size: 0.8rem;
            }
            
            .user-stats-column {
                gap: 0.4rem;
            }
            
            .user-stat-item {
                padding: 0.5rem;
            }
            
            .user-stat-icon {
                width: 25px;
                height: 25px;
                font-size: 0.8rem;
            }
            
            .user-stat-value {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
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
                    <!-- Progress Counters with Online Users -->
                    <div class="dashboard-top">
                        <div class="stats-grid" id="statsGrid">
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
                        
                        <!-- Online Users Section -->
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

                    <!-- Global Statistics with Top Users -->
                    <div class="dashboard-bottom">
                        <!-- Global Statistics Section - Line Layout -->
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
                                            <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1z"/>
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
                        
                        <!-- Top Users Section -->
                        <div class="top-users-section">
                            <div class="top-users-header">
                                <div class="top-users-title">
                                    <i class="fas fa-trophy"></i> Top Users
                                </div>
                            </div>
                            <div class="top-users-list" id="topUsersList">
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>No Top Users</h3>
                                    <p>No top users data available</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section" id="page-checking">
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑬𝑬𝑹</h1>
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
            <p class="page-subtitle">𝐆𝐞𝐧𝐫𝐚 𝐯𝐚𝐥𝐥𝐥 𝐰𝐢𝐢𝐥𝐥𝐬 𝐰𝐢𝐡𝐧𝐡</p>

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
        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check for saved theme preference or default to light
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme icon based on current theme
            const themeIcon = document.querySelector('.theme-toggle-slider i');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
            
            // Set profile information
            document.getElementById('profileAvatar').src = '<?php echo htmlspecialchars($userPhotoUrl); ?>';
            document.getElementById('profileName').textContent = '<?php echo htmlspecialchars($userName); ?>';
            document.getElementById('profileUsername').textContent = '<?php echo htmlspecialchars($formattedUsername); ?>';
            
            // Disable Razorpay 0.10$ gateway and show maintenance popup
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
        
        // Theme toggle function
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update theme icon
            const icon = document.querySelector('.theme-toggle-slider i');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
            
            // Show notification
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `${newTheme === 'light' ? 'Light' : 'Dark'} Mode`,
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    </script>
</body>
</html>
