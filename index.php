<?php
require_once 'maintenance_check.php';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MAINTENANCE MODE CHECK
// Maintenance flag file path - using /tmp/.maintenance as specified
if (!defined('MAINTENANCE_FLAG')) {
    define('MAINTENANCE_FLAG', '/tmp/.maintenance');
}

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; user-select: none; }
        
        /* Beast Level Color Variables */
        :root {
            /* Light theme colors */
            --primary-bg: #f8fafc; 
            --secondary-bg: #f1f5f9; 
            --card-bg: #ffffff;
            --text-primary: #0f172a; 
            --text-secondary: #475569; 
            --border-color: #e2e8f0;
            --accent-blue: #3b82f6; 
            --accent-cyan: #06b6d4;
            --accent-green: #10b981; 
            --accent-purple: #8b5cf6;
            --error: #ef4444; 
            --warning: #f59e0b; 
            --success-green: #22c55e; 
            --declined-red: #ef4444;
            
            /* Dark theme colors - Enhanced visibility */
            --dark-primary-bg: #0f172a;
            --dark-secondary-bg: #1e293b;
            --dark-card-bg: #1e293b;
            --dark-text-primary: #f1f5f9;
            --dark-text-secondary: #cbd5e1;
            --dark-border-color: #334155;
            --dark-accent-bg: #334155;
            
            /* Beast Mode Gradients */
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-dark: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            
            /* Enhanced Stat Gradients */
            --stat-charged: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --stat-approved: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --stat-threeds: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --stat-declined: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --stat-checked: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
            /* Beast Shadows */
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 32px rgba(0,0,0,0.2);
            --shadow-beast: 0 20px 40px rgba(0,0,0,0.25);
            
            /* User item colors */
            --user-item-bg-light: rgba(59, 130, 246, 0.08);
            --user-item-border-light: rgba(59, 130, 246, 0.25);
            --user-item-bg-dark: rgba(59, 130, 246, 0.12);
            --user-item-border-dark: rgba(59, 130, 246, 0.35);
            
            /* Admin user colors */
            --admin-item-bg-light: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(236, 72, 153, 0.15));
            --admin-item-border-light: rgba(168, 85, 247, 0.4);
            --admin-item-bg-dark: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(236, 72, 153, 0.2));
            --admin-item-border-dark: rgba(168, 85, 247, 0.5);
            
            /* Hits colors */
            --hits-light: #fbbf24;
            --hits-dark: #fbbf24;
        }
        
        /* Smooth transitions for all elements - Removed transform from global */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, 
                        box-shadow 0.3s ease, opacity 0.3s ease;
        }
        
        body {
            font-family: Inter, sans-serif; 
            background: var(--primary-bg);
            color: var(--text-primary); 
            min-height: 100vh; 
            overflow-x: hidden;
            position: relative;
            font-size: 14px;
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        /* Dark mode styles */
        body[data-theme="dark"] {
            background: var(--dark-primary-bg);
            color: var(--dark-text-primary);
        }
        
        body[data-theme="dark"]::before {
            background: radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
        }
        
        /* Beast Level Navbar - Reduced size */
        .navbar {
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            padding: 0.5rem 0.8rem;
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            z-index: 1000; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
            height: 50px;
            box-shadow: var(--shadow-md);
        }
        
        .navbar-brand {
            display: flex; 
            align-items: center; 
            gap: 0.6rem;
            font-size: 1.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
            text-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .navbar-brand i { 
            font-size: 1.2rem;
        }
        
        .navbar-actions { 
            display: flex; 
            align-items: center; 
            gap: 0.6rem;
        }
        
        .theme-toggle {
            width: 40px;
            height: 20px;
            background: var(--dark-accent-bg);
            border-radius: 10px;
            cursor: pointer; 
            border: 1px solid var(--dark-border-color);
            position: relative; 
            transition: all 0.3s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .theme-toggle-slider {
            position: absolute; 
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            left: 2px; 
            top: 2px;
            transition: transform 0.3s ease; 
            display: flex;
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-size: 0.5rem;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }
        
        [data-theme="dark"] .theme-toggle-slider { 
            transform: translateX(18px);
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
        }
        
        .user-info {
            display: flex; 
            align-items: center; 
            gap: 0.6rem;
            padding: 0.3rem 0.6rem;
            background: rgba(15, 23, 42, 0.8); 
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        
        .user-info:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-blue);
        }
        
        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .user-name {
            font-weight: 700; 
            color: #ffffff; 
            max-width: 80px;
            overflow: hidden; 
            text-overflow: ellipsis;
            white-space: nowrap; 
            font-size: 0.8rem;
        }
        
        .user-username {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.7); 
            max-width: 80px;
            overflow: hidden; 
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Remove animation from menu toggle */
        .menu-toggle {
            color: #ffffff !important; 
            font-size: 1.1rem;
            transition: color 0.3s; /* Only transition color, not transform */
            display: flex; 
            align-items: center; 
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.2);
            flex-shrink: 0; 
            cursor: pointer;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .menu-toggle:hover { 
            background: var(--accent-blue); 
            color: white !important;
            /* Removed transform and box-shadow to prevent animation */
        }
        
        /* Beast Level Sidebar - Optimized for performance and reduced size */
        .sidebar {
            position: fixed; 
            left: 0; 
            top: 50px;
            bottom: 0; 
            width: 260px;
            background: rgba(30, 41, 59, 0.95); 
            border-right: 1px solid var(--dark-border-color);
            padding: 1rem 0;
            z-index: 999; 
            overflow-y: auto;
            transform: translateX(-100%); 
            transition: transform 0.3s ease-out;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
            will-change: transform;
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .sidebar-menu { 
            list-style: none; 
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 0.8rem;
        }
        
        .sidebar-item { 
            margin: 0.3rem 0;
        }
        
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 0.6rem;
            padding: 0.6rem 0.8rem;
            color: var(--dark-text-secondary);
            border-radius: 10px;
            cursor: pointer; 
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-link:hover {
            background: rgba(59,130,246,0.15); 
            color: var(--accent-blue);
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .sidebar-link i { 
            width: 18px;
            text-align: center; 
            font-size: 0.9rem;
        }
        
        .sidebar-divider { 
            height: 1px; 
            background: var(--dark-border-color); 
            margin: 1rem 0.8rem;
        }
        
        /* Beast Level Main Content - Reduced size */
        .main-content {
            margin-left: 0; 
            margin-top: 50px;
            padding: 1rem;
            min-height: calc(100vh - 50px);
            position: relative; 
            z-index: 1;
            transition: margin-left 0.3s ease-out;
            margin-right: 420px;
        }
        
        .main-content.sidebar-open { 
            margin-left: 260px;
        }
        
        .page-section { 
            display: none; 
            animation: fadeInUp 0.3s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-section.active { 
            display: block; 
        }
        
        .page-title {
            font-size: 1.6rem;
            font-weight: 900; 
            margin-bottom: 0.6rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
        }
        
        .page-subtitle { 
            color: var(--text-secondary); 
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        body[data-theme="dark"] .page-subtitle {
            color: var(--dark-text-secondary);
        }
        
        /* Beast Level Dashboard - Reduced size */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .welcome-banner {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.2rem;
            position: relative;
            overflow: hidden;
        }
        
        body[data-theme="dark"] .welcome-banner {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }
        
        .welcome-text h2 {
            font-size: 1.3rem;
            margin-bottom: 0.2rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        body[data-theme="dark"] .welcome-text p {
            color: var(--dark-text-secondary);
        }
        
        /* Beast Level Stats Grid - Better Spacing and Reduced Size */
        .stats-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--card-bg); 
            border-radius: 14px;
            padding: 1.2rem;
            position: relative;
            transition: all 0.3s ease; 
            box-shadow: var(--shadow-md); 
            min-height: 100px;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .stat-card {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
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
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -15px;
            right: -15px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            opacity: 0.1;
            transform: scale(0);
            transition: transform 0.3s ease;
        }
        
        .stat-card.charged::after { background: var(--stat-charged); }
        .stat-card.approved::after { background: var(--stat-approved); }
        .stat-card.declined::after { background: var(--stat-declined); }
        .stat-card.checked::after { background: var(--stat-checked); }
        
        .stat-card:hover::after {
            transform: scale(1);
        }
        
        .stat-card:hover { 
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
            position: relative;
            z-index: 1;
        }
        
        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1rem;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .stat-card.charged .stat-icon { background: var(--stat-charged); }
        .stat-card.approved .stat-icon { background: var(--stat-approved); }
        .stat-card.declined .stat-icon { background: var(--stat-declined); }
        .stat-card.checked .stat-icon { background: var(--stat-checked); }
        
        .stat-value { 
            font-size: 1.8rem;
            font-weight: 900; 
            margin-bottom: 0.4rem;
            line-height: 1;
            position: relative;
            z-index: 1;
        }
        
        .stat-card.charged .stat-value { color: var(--success-green); }
        .stat-card.approved .stat-value { color: var(--success-green); }
        .stat-card.declined .stat-value { color: var(--declined-red); }
        .stat-card.checked .stat-value { color: var(--accent-purple); }
        
        .stat-label {
            color: var(--text-secondary); 
            font-size: 0.7rem;
            text-transform: uppercase; 
            font-weight: 700;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }
        
        body[data-theme="dark"] .stat-label {
            color: var(--dark-text-secondary);
        }
        
        /* Beast Level Global Stats - Better Spacing and Dark Mode Fix */
        .gs-panel{
            border-radius:14px;
            padding:1.5rem;
            background: linear-gradient(135deg, rgba(59,130,246,0.05), rgba(139,92,246,0.05));
            border:1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        body[data-theme="dark"] .gs-panel {
            background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.15));
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .gs-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
        }
        
        .gs-head{display:flex;align-items:center;gap:10px;margin-bottom:1.5rem;}
        .gs-chip{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;
          background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.25)}
        .gs-title{font-weight:800;color:var(--text-primary);font-size:1.1rem}
        .gs-sub{font-size:0.8rem;color:var(--text-secondary);margin-top:2px}
        .gs-grid{display:grid;gap:1.2rem}
        @media (min-width:640px){.gs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1280px){.gs-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}

        .gs-card{
          position:relative;border-radius:10px;padding:1.2rem;
          border:1px solid var(--border-color);
          box-shadow: var(--shadow-sm);
          color:var(--text-primary);
          transition: all 0.3s ease;
        }
        
        body[data-theme="dark"] .gs-card {
            background: rgba(30, 41, 59, 0.7);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .gs-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .gs-card .gs-icon{
          width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;
          margin-bottom:0.8rem;border:1px solid var(--border-color)
        }
        .gs-card .gs-icon svg{width:16px;height:16px;display:block;opacity:.95}
        .gs-num{font-weight:800;font-size:1.8rem;line-height:1}
        .gs-label{font-size:0.8rem;color:var(--text-secondary);margin-top:0.6rem}
        
        body[data-theme="dark"] .gs-label {
            color: var(--dark-text-secondary);
        }
        
        .gs-blue   { background: linear-gradient(135deg, rgba(59,130,246,0.3), rgba(59,130,246,0.2)); }
        .gs-green  { background: linear-gradient(135deg, rgba(16,185,129,0.3), rgba(16,185,129,0.2)); }
        .gs-red    { background: linear-gradient(135deg, rgba(239,68,68,0.3), rgba(239,68,68,0.2)); }
        .gs-purple { background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(139,92,246,0.2)); }
        
        body[data-theme="dark"] .gs-blue   { background: linear-gradient(135deg, rgba(59,130,246,0.4), rgba(59,130,246,0.3)); }
        body[data-theme="dark"] .gs-green  { background: linear-gradient(135deg, rgba(16,185,129,0.4), rgba(16,185,129,0.3)); }
        body[data-theme="dark"] .gs-red    { background: linear-gradient(135deg, rgba(239,68,68,0.4), rgba(239,68,68,0.3)); }
        body[data-theme="dark"] .gs-purple { background: linear-gradient(135deg, rgba(139,92,246,0.4), rgba(139,92,246,0.3)); }
        
        /* Beast Level Right Sidebar - Light Mode Fix and Increased Width */
        .right-sidebar {
            position: fixed;
            right: 0;
            top: 50px;
            bottom: 0;
            width: 420px;
            background: var(--card-bg);
            border-left: 1px solid var(--border-color);
            padding: 1.2rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            overflow: hidden;
            z-index: 998;
            backdrop-filter: blur(20px);
        }
        
        body[data-theme="dark"] .right-sidebar {
            background: rgba(30, 41, 59, 0.95);
            border-left-color: var(--dark-border-color);
        }
        
        /* Online Users Section - Beast Level - Light Mode Fix and Reduced Size */
        .online-users-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        body[data-theme="dark"] .online-users-section {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .online-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
            animation: gradientShift 3s ease infinite;
        }
        
        .online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .online-users-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        body[data-theme="dark"] .online-users-title {
            color: var(--dark-text-primary);
        }
        
        .online-users-title i {
            color: var(--accent-cyan);
        }
        
        .online-users-count {
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.4rem 0.6rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        body[data-theme="dark"] .online-users-count {
            color: var(--dark-text-secondary);
            background: rgba(59, 130, 246, 0.2);
        }
        
        .online-users-count i {
            color: var(--success-green);
            font-size: 0.5rem;
        }
        
        .online-users-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.3rem;
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
        
        body[data-theme="dark"] .online-users-list::-webkit-scrollbar-track {
            background: var(--dark-primary-bg);
        }
        
        .online-users-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 2px;
        }
        
        .online-user-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 0.8rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        body[data-theme="dark"] .online-user-item {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .online-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .online-user-avatar-container {
            position: relative;
        }
        
        .online-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
        }
        
        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background-color: var(--success-green);
            border: 2px solid var(--card-bg);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        body[data-theme="dark"] .online-indicator {
            border-color: var(--dark-card-bg);
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            70% {
                box-shadow: 0 0 0 5px rgba(34, 197, 94, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
        
        .online-user-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .online-user-name {
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        body[data-theme="dark"] .online-user-name {
            color: #ffffff;
        }
        
        .online-user-username {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        
        body[data-theme="dark"] .online-user-username {
            color: #ffffff;
        }
        
        /* Top Users Section - Beast Level - Light Mode Fix and Reduced Size */
        .top-users-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        body[data-theme="dark"] .top-users-section {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .top-users-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-purple), var(--accent-blue), var(--accent-cyan));
            animation: gradientShift 3s ease infinite;
        }
        
        .top-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .top-users-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        body[data-theme="dark"] .top-users-title {
            color: var(--dark-text-primary);
        }
        
        .top-users-title i {
            color: var(--accent-purple);
        }
        
        .top-users-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.3rem;
            position: relative;
            z-index: 1;
        }
        
        .top-users-list::-webkit-scrollbar {
            width: 3px;
        }
        
        .top-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg);
            border-radius: 2px;
        }
        
        body[data-theme="dark"] .top-users-list::-webkit-scrollbar-track {
            background: var(--dark-primary-bg);
        }
        
        .top-users-list::-webkit-scrollbar-thumb {
            background: var(--accent-purple);
            border-radius: 2px;
        }
        
        .top-user-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 0.8rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        body[data-theme="dark"] .top-user-item {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .top-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-purple);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }
        
        .top-user-avatar-container {
            position: relative;
        }
        
        .top-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-purple);
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(139, 92, 246, 0.4);
        }
        
        .top-user-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .top-user-name {
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        body[data-theme="dark"] .top-user-name {
            color: #ffffff;
        }
        
        .top-user-username {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-bottom: 0.1rem;
        }
        
        body[data-theme="dark"] .top-user-username {
            color: #ffffff;
        }
        
        .top-user-hits {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--hits-light);
            background-color: rgba(251, 191, 36, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.3);
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            text-align: center;
        }
        
        body[data-theme="dark"] .top-user-hits {
            color: var(--hits-dark) !important;
            background-color: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.3);
        }
        
        /* Admin user styling */
        .online-user-item.admin {
            background: var(--admin-item-bg-light) !important;
            border: 1px solid var(--admin-item-border-light) !important;
        }
        
        body[data-theme="dark"] .online-user-item.admin {
            background: var(--admin-item-bg-dark) !important;
            border-color: var(--admin-item-border-dark) !important;
        }
        
        .top-user-item.admin {
            background: var(--admin-item-bg-light) !important;
            border: 1px solid var(--admin-item-border-light) !important;
        }
        
        body[data-theme="dark"] .top-user-item.admin {
            background: var(--admin-item-bg-dark) !important;
            border-color: var(--admin-item-border-dark) !important;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #a855f7, #ec4899) !important;
            color: white !important;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 5px;
            box-shadow: 0 2px 4px rgba(168, 85, 247, 0.3);
        }
        
        /* Mobile Online Users Section - Fixed to match top users */
        .mobile-online-users {
            margin-top: 1.5rem;
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: none;
        }
        
        body[data-theme="dark"] .mobile-online-users {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .mobile-online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .mobile-online-users-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        body[data-theme="dark"] .mobile-online-users-title {
            color: var(--dark-text-primary);
        }
        
        .mobile-online-users-title i {
            color: var(--accent-cyan);
        }
        
        .mobile-online-users-count {
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        
        body[data-theme="dark"] .mobile-online-users-count {
            color: var(--dark-text-secondary);
            background: rgba(59, 130, 246, 0.2);
        }
        
        .mobile-online-users-count i {
            color: var(--success-green);
            font-size: 0.5rem;
        }
        
        .mobile-online-users-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        
        /* Fix for mobile online users display - make it match top users */
        .mobile-online-user-item {
            display: flex;
            align-items: center;
            gap: 0.8rem; /* Increased gap to match top users */
            padding: 0.8rem; /* Increased padding to match top users */
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            transition: all 0.3s;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
        }
        
        body[data-theme="dark"] .mobile-online-user-item {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .mobile-online-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .mobile-online-user-avatar {
            width: 36px; /* Increased size to match top users */
            height: 36px; /* Increased size to match top users */
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
        }
        
        .mobile-online-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 1;
        }
        
        .mobile-online-user-name {
            font-weight: 700;
            font-size: 0.85rem; /* Increased font size to match top users */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
            max-width: 100%;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        body[data-theme="dark"] .mobile-online-user-name {
            color: #ffffff;
        }
        
        .mobile-online-user-username {
            font-size: 0.7rem; /* Increased font size to match top users */
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        
        body[data-theme="dark"] .mobile-online-user-username {
            color: #ffffff;
        }
        
        /* Mobile Top Users Section - Added */
        .mobile-top-users {
            margin-top: 1.5rem;
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: none;
        }
        
        body[data-theme="dark"] .mobile-top-users {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .mobile-top-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .mobile-top-users-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        body[data-theme="dark"] .mobile-top-users-title {
            color: var(--dark-text-primary);
        }
        
        .mobile-top-users-title i {
            color: var(--accent-purple);
        }
        
        .mobile-top-users-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        
        .mobile-top-user-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            transition: all 0.3s;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        body[data-theme="dark"] .mobile-top-user-item {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .mobile-top-user-item:hover {
            transform: translateX(3px);
            border-color: var(--accent-purple);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }
        
        .mobile-top-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-purple);
        }
        
        .mobile-top-user-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .mobile-top-user-name {
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        body[data-theme="dark"] .mobile-top-user-name {
            color: #ffffff;
        }
        
        .mobile-top-user-username {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-bottom: 0.1rem;
        }
        
        body[data-theme="dark"] .mobile-top-user-username {
            color: #ffffff;
        }
        
        .mobile-top-user-hits {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--hits-light);
            background-color: rgba(251, 191, 36, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.3);
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            text-align: center;
        }
        
        body[data-theme="dark"] .mobile-top-user-hits {
            color: var(--hits-dark) !important;
            background-color: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.3);
        }
        
        /* Beast Level Profile Page - Restored and Enhanced */
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        
        .profile-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        body[data-theme="dark"] .profile-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.15));
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
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
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-status {
            position: absolute;
            bottom: 6px;
            right: 6px;
            width: 20px;
            height: 20px;
            background-color: var(--success-green);
            border: 2px solid var(--card-bg);
            border-radius: 50%;
        }
        
        body[data-theme="dark"] .profile-status {
            border-color: var(--dark-card-bg);
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 0.4rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
        }
        
        .profile-username {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 0.8rem;
        }
        
        body[data-theme="dark"] .profile-username {
            color: var(--dark-text-secondary);
        }
        
        .profile-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .profile-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        
        .profile-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
        
        .profile-stats-container {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }
        
        body[data-theme="dark"] .profile-stats-container {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
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
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .profile-stats-title {
            color: var(--dark-text-primary);
        }
        
        .profile-stats-title i {
            color: var(--accent-cyan);
        }
        
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
            padding: 1rem;
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
        }
        
        body[data-theme="dark"] .user-stat-item {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
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
        .user-stat-item.threeds::before { background: var(--stat-threeds); }
        .user-stat-item.checked::before { background: var(--stat-checked); }
        
        .user-stat-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .user-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            margin-right: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .user-stat-item.charged .user-stat-icon { background: var(--stat-charged); }
        .user-stat-item.approved .user-stat-icon { background: var(--stat-approved); }
        .user-stat-item.declined .user-stat-icon { background: var(--stat-declined); }
        .user-stat-item.threeds .user-stat-icon { background: var(--stat-threeds); }
        .user-stat-item.checked .user-stat-icon { background: var(--stat-checked); }
        
        .user-stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        body[data-theme="dark"] .user-stat-label {
            color: var(--dark-text-secondary);
        }
        
        .user-stat-value {
            font-size: 1.4rem;
            font-weight: 900;
            line-height: 1;
        }
        
        .user-stat-item.charged .user-stat-value { color: var(--success-green); }
        .user-stat-item.approved .user-stat-value { color: var(--success-green); }
        .user-stat-item.declined .user-stat-value { color: var(--declined-red); }
        .user-stat-item.threeds .user-stat-value { color: var(--accent-cyan); }
        .user-stat-item.checked .user-stat-value { color: var(--accent-purple); }
        
        /* Other sections styling - Reduced Size */
        .checker-section, .generator-section {
            background: var(--card-bg); 
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            box-shadow: var(--shadow-md);
        }
        
        body[data-theme="dark"] .checker-section, 
        body[data-theme="dark"] .generator-section {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .checker-header, .generator-header {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap; 
            gap: 0.6rem;
            position: relative;
            z-index: 1;
        }
        
        .checker-title, .generator-title {
            font-size: 1.1rem;
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .checker-title, 
        body[data-theme="dark"] .generator-title {
            color: var(--dark-text-primary);
        }
        
        .checker-title i, .generator-title i { 
            color: var(--accent-cyan); 
            font-size: 1rem;
        }
        
        .settings-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            cursor: pointer; 
            font-weight: 600; 
            display: flex;
            align-items: center; 
            gap: 0.3rem;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .settings-btn {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .settings-btn:hover {
            border-color: var(--accent-blue); 
            color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .input-section { 
            margin-bottom: 1rem;
        }
        
        .input-header {
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            margin-bottom: 0.6rem;
            flex-wrap: wrap; 
            gap: 0.6rem;
        }
        
        .input-label { 
            font-weight: 700; 
            font-size: 0.85rem;
        }
        
        body[data-theme="dark"] .input-label {
            color: var(--dark-text-primary);
        }
        
        .card-textarea {
            width: 100%; 
            min-height: 120px;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); 
            border-radius: 10px;
            padding: 0.8rem;
            color: var(--text-primary);
            font-family: 'Courier New', monospace; 
            resize: vertical;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .card-textarea {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .card-textarea:focus {
            outline: none; 
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-control {
            width: 100%; 
            padding: 0.6rem 0.8rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); 
            border-radius: 10px;
            color: var(--text-primary); 
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .form-control {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .form-control:focus {
            outline: none; 
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .form-row {
            display: flex; 
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        
        .form-col {
            flex: 1; 
            min-width: 100px;
        }
        
        .action-buttons { 
            display: flex; 
            gap: 0.6rem;
            flex-wrap: wrap; 
            justify-content: center; 
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 700; 
            cursor: pointer; 
            display: flex;
            align-items: center; 
            gap: 0.4rem;
            min-width: 100px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); 
            color: var(--text-primary);
        }
        
        body[data-theme="dark"] .btn-secondary {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .btn-secondary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .results-section {
            background: var(--card-bg); 
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            box-shadow: var(--shadow-md);
        }
        
        body[data-theme="dark"] .results-section {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .results-header {
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            margin-bottom: 1rem;
            flex-wrap: wrap; 
            gap: 0.6rem;
            position: relative;
            z-index: 1;
        }
        
        .results-title {
            font-size: 1.1rem;
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .results-title {
            color: var(--dark-text-primary);
        }
        
        .results-title i { 
            color: var(--accent-green); 
            font-size: 1rem;
        }
        
        .results-filters { 
            display: flex; 
            gap: 0.4rem;
            flex-wrap: wrap; 
        }
        
        .filter-btn {
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-secondary);
            cursor: pointer; 
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .filter-btn {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-secondary);
        }
        
        .filter-btn:hover { 
            border-color: var(--accent-blue); 
            color: var(--accent-blue);
            transform: translateY(-2px);
        }
        
        .filter-btn.active {
            background: var(--accent-blue); 
            border-color: var(--accent-blue); 
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .empty-state {
            text-align: center; 
            padding: 1.5rem 0.8rem;
            color: var(--text-secondary);
        }
        
        body[data-theme="dark"] .empty-state {
            color: var(--dark-text-secondary);
        }
        
        .empty-state i { 
            font-size: 2rem;
            margin-bottom: 0.6rem;
            opacity: 0.3; 
        }
        
        .empty-state h3 { 
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
            font-weight: 700;
        }
        
        /* Settings Popup - Two-Level Gateway Structure - Reduced Size */
        .settings-popup {
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0;
            background: rgba(0,0,0,0.8); 
            backdrop-filter: blur(10px);
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
            border-radius: 14px;
            padding: 1.2rem;
            max-width: 90vw; 
            width: 90%;
            max-height: 80vh; 
            overflow-y: auto;
            box-shadow: var(--shadow-beast);
            animation: slideUp 0.3s ease;
        }
        
        body[data-theme="dark"] .settings-content {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .settings-header {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .settings-header {
            border-color: var(--dark-border-color);
        }
        
        .settings-title {
            font-size: 1.1rem;
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .settings-title {
            color: var(--dark-text-primary);
        }
        
        .settings-close {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: none;
            background: var(--secondary-bg); 
            color: var(--text-secondary);
            cursor: pointer; 
            display: flex; 
            align-items: center;
            justify-content: center; 
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .settings-close {
            background: var(--dark-accent-bg);
            color: var(--dark-text-secondary);
        }
        
        .settings-close:hover {
            background: var(--error); 
            color: white; 
            transform: rotate(90deg);
        }
        
        /* Gateway Provider Selection - Improved Structure */
        .gateway-providers {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .gateway-provider {
            flex: 1;
            min-width: 200px;
            padding: 1.5rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-md);
        }
        
        body[data-theme="dark"] .gateway-provider {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .gateway-provider:hover {
            border-color: var(--accent-blue);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .gateway-provider.active {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--accent-blue);
        }
        
        .gateway-provider-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--accent-blue);
        }
        
        .gateway-provider-name {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--text-primary);
        }
        
        body[data-theme="dark"] .gateway-provider-name {
            color: var(--dark-text-primary);
        }
        
        /* Gateway Options - Improved Structure */
        .gateway-options {
            display: none;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .gateway-options.active {
            display: flex;
        }
        
        .gateway-option {
            display: flex; 
            align-items: center; 
            padding: 1.5rem;
            background: var(--secondary-bg); 
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer; 
            transition: all 0.3s;
            position: relative;
            margin-bottom: 0;
        }
        
        body[data-theme="dark"] .gateway-option {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .gateway-option:hover {
            border-color: var(--accent-blue); 
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }
        
        .gateway-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 1rem;
            cursor: pointer; 
            accent-color: var(--accent-blue);
        }
        
        .gateway-option-content { 
            flex: 1; 
        }
        
        .gateway-option-name {
            font-weight: 700; 
            display: flex; 
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        body[data-theme="dark"] .gateway-option-name {
            color: var(--dark-text-primary);
        }
        
        .gateway-option-desc { 
            font-size: 0.8rem;
            color: var(--text-secondary); 
        }
        
        body[data-theme="dark"] .gateway-option-desc {
            color: var(--dark-text-secondary);
        }
        
        .gateway-badge {
            padding: 0.3rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700; 
            text-transform: uppercase;
        }
        
        .badge-charge { 
            background: rgba(245,158,11,0.15); 
            color: var(--warning); 
        }
        
        .badge-auth { 
            background: rgba(6,182,212,0.15); 
            color: var(--accent-cyan); 
        }
        
        .badge-maintenance {
            background-color: #ef4444;
            color: white;
            padding: 0.3rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        
        .settings-footer {
            display: flex; 
            gap: 0.8rem;
            margin-top: 1.2rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .settings-footer {
            border-color: var(--dark-border-color);
        }
        
        .btn-save {
            flex: 1; 
            padding: 0.6rem;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-save:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-back {
            flex: 1; 
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        
        body[data-theme="dark"] .btn-back {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .btn-back:hover { 
            transform: translateY(-2px); 
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        
        .btn-cancel {
            flex: 1; 
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem;
        }
        
        body[data-theme="dark"] .btn-cancel {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .btn-cancel:hover { 
            transform: translateY(-2px); 
        }
        
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #ec4899;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 12px auto;
            display: none;
        }
        
        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
        
        #statusLog, #genStatusLog { 
            margin-top: 0.6rem;
            color: var(--text-secondary); 
            text-align: center; 
            font-size: 0.8rem;
        }
        
        body[data-theme="dark"] #statusLog, 
        body[data-theme="dark"] #genStatusLog {
            color: var(--dark-text-secondary);
        }
        
        .copy-btn { 
            background: transparent; 
            border: none; 
            cursor: pointer; 
            color: var(--accent-blue); 
            font-size: 0.7rem;
            margin-left: auto; 
        }
        
        .copy-btn:hover { 
            color: var(--accent-purple); 
        }
        
        .stat-content { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }
        
        .sidebar-link.logout {
            color: var(--error);
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--error);
            margin-top: auto;
            margin-bottom: 1.2rem;
        }
        
        .sidebar-link.logout:hover {
            background: rgba(239, 68, 68, 0.25);
            color: var(--error);
            transform: translateX(5px);
        }
        
        .generated-cards-container {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.8rem;
            max-height: 240px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        body[data-theme="dark"] .generated-cards-container {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .custom-select {
            position: relative;
            display: flex;
            width: 100%;
        }
        
        .custom-select select {
            appearance: none;
            width: 100%;
            padding: 0.6rem 0.8rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .custom-select select {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .custom-select select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .custom-select::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 0.8rem;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-secondary);
        }
        
        body[data-theme="dark"] .custom-select::after {
            color: var(--dark-text-secondary);
        }
        
        .custom-input-group {
            display: flex;
            width: 100%;
        }
        
        .custom-input-group input {
            flex: 1;
            padding: 0.6rem 0.8rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px 0 0 10px;
            color: var(--text-primary);
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .custom-input-group input {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .custom-input-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .custom-input-group .input-group-append {
            display: flex;
        }
        
        .custom-input-group .input-group-text {
            display: flex;
            align-items: center;
            padding: 0 0.8rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 10px 10px 0;
            color: var(--text-secondary);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .custom-input-group .input-group-text {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-secondary);
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
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .copy-all-btn:hover, .clear-all-btn:hover {
            background: var(--accent-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        .clear-all-btn {
            border-color: var(--error);
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        
        .results-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        
        /* Hide role elements */
        .profile-role,
        .role-badge,
        .user-role {
            display: none !important;
        }
        
        /* Hide undefined elements */
        .profile-info:empty,
        .profile-name:empty,
        .profile-username:empty,
        [data-undefined],
        [undefined] {
            display: none !important;
        }
        
        /* Mobile-specific styles - Beast Level */
        @media (max-width: 768px) {
            body { font-size: 13px; }
            
            .navbar { 
                padding: 0.4rem 0.6rem;
                height: 46px;
            }
            
            .navbar-brand { 
                font-size: 1rem;
                margin-left: 0.6rem;
            }
            
            .navbar-brand i { font-size: 1rem; }
            
            .user-avatar { width: 24px; height: 24px; }
            
            .user-name { 
                max-width: 60px;
                font-size: 0.7rem;
            }
            
            .user-username {
                max-width: 60px;
                font-size: 0.6rem;
            }
            
            .sidebar { 
                width: 240px;
                top: 46px;
            }
            
            .page-title { font-size: 1.3rem; }
            
            .page-subtitle { font-size: 0.8rem; }
            
            .main-content {
                margin-top: 46px;
                padding: 0.8rem;
                margin-right: 0;
            }
            
            /* Mobile stats grid */
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.8rem;
            }
            
            .stat-card { 
                padding: 1rem;
                min-height: 90px;
            }
            
            .stat-icon { 
                width: 28px;
                height: 28px;
                font-size: 0.9rem;
            }
            
            .stat-value { 
                font-size: 1.6rem;
            }
            
            .stat-label { 
                font-size: 0.6rem;
            }
            
            .welcome-banner { 
                padding: 1rem;
            }
            
            .welcome-icon { 
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .welcome-text h2 { 
                font-size: 1.1rem;
            }
            
            .welcome-text p { 
                font-size: 0.8rem;
            }
            
            .checker-section, .generator-section { 
                padding: 1rem;
            }
            
            .checker-title, .generator-title { 
                font-size: 1rem;
            }
            
            .checker-title i, .generator-title i { 
                font-size: 0.8rem;
            }
            
            .settings-btn { 
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .input-label { 
                font-size: 0.8rem;
            }
            
            .card-textarea { 
                min-height: 100px;
                padding: 0.6rem;
                font-size: 0.8rem;
            }
            
            .btn { 
                padding: 0.4rem 0.8rem;
                min-width: 80px;
                font-size: 0.8rem;
            }
            
            .results-section { 
                padding: 1rem;
            }
            
            .results-title { 
                font-size: 1rem;
            }
            
            .results-title i { 
                font-size: 0.8rem;
            }
            
            .filter-btn { 
                padding: 0.2rem 0.5rem;
                font-size: 0.6rem;
            }
            
            .generated-cards-container { 
                max-height: 180px;
                font-size: 0.7rem;
                padding: 0.6rem;
            }
            
            .copy-all-btn, .clear-all-btn { 
                padding: 0.3rem 0.6rem;
                font-size: 0.7rem;
            }
            
            .form-row { 
                flex-direction: column; 
                gap: 0.6rem;
            }
            
            .form-col { 
                min-width: 100%; 
            }
            
            .settings-content { 
                max-width: 95vw; 
                padding: 1rem;
            }
            
            .gateway-option { 
                padding: 0.8rem;
            }
            
            .gateway-option-name { 
                font-size: 0.85rem;
            }
            
            .gateway-option-desc { 
                font-size: 0.7rem;
            }
            
            .menu-toggle {
                position: absolute;
                left: 0.6rem;
                top: 50%;
                transform: translateY(-50%);
                width: 32px;
                height: 32px;
            }
            
            .navbar-brand {
                margin-left: 2rem;
            }
            
            .theme-toggle {
                width: 36px;
                height: 18px;
            }
            
            .theme-toggle-slider {
                width: 14px;
                height: 14px;
                left: 2px;
            }
            
            [data-theme="dark"] .theme-toggle-slider { 
                transform: translateX(16px);
            }
            
            .user-info {
                padding: 0.2rem 0.3rem;
                gap: 0.3rem;
            }
            
            /* Show mobile sections instead of right sidebar */
            .right-sidebar {
                display: none;
            }
            
            .mobile-online-users {
                display: block !important;
            }
            
            .mobile-top-users {
                display: block !important;
            }
            
            /* Profile page mobile adjustments */
            .profile-header {
                padding: 1rem;
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
                gap: 0.6rem;
            }
            
            .user-stat-item {
                padding: 0.8rem;
            }
            
            .user-stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .user-stat-value {
                font-size: 1.2rem;
            }
            
            /* Mobile Online Users Section - Fixed sizing */
            .mobile-online-user-item {
                padding: 0.8rem; /* Consistent with top users */
            }
            
            .mobile-online-user-avatar {
                width: 36px; /* Consistent with top users */
                height: 36px;
            }
            
            .mobile-online-user-name {
                font-size: 0.85rem; /* Consistent with top users */
            }
            
            .mobile-online-user-username {
                font-size: 0.7rem; /* Consistent with top users */
            }
            
            /* Mobile Top Users Section */
            .mobile-top-user-item {
                padding: 0.6rem;
            }
            
            .mobile-top-user-avatar {
                width: 30px;
                height: 30px;
            }
            
            .mobile-top-user-name {
                font-size: 0.75rem;
            }
            
            .mobile-top-user-username {
                font-size: 0.6rem;
            }
            
            .mobile-top-user-hits {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
                min-width: 50px;
            }
        }
        
        /* For very small screens */
        @media (max-width: 480px) {
            body { font-size: 12px; }
            
            .navbar { 
                padding: 0.3rem 0.5rem;
                height: 42px;
            }
            
            .navbar-brand { 
                font-size: 0.9rem;
            }
            
            .user-avatar { 
                width: 20px;
                height: 20px;
            }
            
            .user-name { 
                max-width: 50px;
                font-size: 0.65rem;
            }
            
            .user-username {
                max-width: 50px;
                font-size: 0.55rem;
            }
            
            .menu-toggle { 
                width: 28px;
                height: 28px;
                font-size: 0.9rem;
            }
            
            .sidebar { 
                width: 220px;
                top: 42px;
            }
            
            .main-content {
                margin-top: 42px;
                padding: 0.6rem;
            }
            
            .page-title { 
                font-size: 1.1rem;
            }
            
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.6rem;
            }
            
            .stat-card { 
                padding: 0.8rem;
                min-height: 80px;
            }
            
            .stat-value { 
                font-size: 1.4rem;
            }
            
            .stat-label { 
                font-size: 0.55rem;
            }
            
            .btn { 
                padding: 0.3rem 0.6rem;
                min-width: 70px;
                font-size: 0.7rem;
            }
            
            /* Profile page for very small screens */
            .profile-header {
                padding: 0.8rem;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
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
                gap: 0.5rem;
            }
            
            .user-stat-item {
                padding: 0.6rem;
            }
            
            .user-stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .user-stat-value {
                font-size: 1rem;
            }
            
            /* Mobile Online Users for very small screens */
            .mobile-online-user-item {
                padding: 0.6rem; /* Slightly smaller but still readable */
            }
            
            .mobile-online-user-avatar {
                width: 32px; /* Slightly smaller but still visible */
                height: 32px;
            }
            
            .mobile-online-user-name {
                font-size: 0.8rem; /* Still readable */
            }
            
            .mobile-online-user-username {
                font-size: 0.65rem; /* Still readable */
            }
            
            /* Mobile Top Users for very small screens */
            .mobile-top-user-item {
                padding: 0.5rem;
            }
            
            .mobile-top-user-avatar {
                width: 26px;
                height: 26px;
            }
            
            .mobile-top-user-name {
                font-size: 0.7rem;
            }
            
            .mobile-top-user-username {
                font-size: 0.55rem;
            }
            
            .mobile-top-user-hits {
                font-size: 0.6rem;
                padding: 0.2rem 0.3rem;
                min-width: 45px;
            }
        }
        
        /* Enhanced Gateway Selection Modal */
        .gateway-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .gateway-modal.active {
            display: flex;
            opacity: 1;
        }
        
        .gateway-modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-beast);
            transform: translateY(20px);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
        }
        
        .gateway-modal.active .gateway-modal-content {
            transform: translateY(0);
            opacity: 1;
        }
        
        body[data-theme="dark"] .gateway-modal-content {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
        }
        
        .gateway-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .gateway-modal-header {
            border-color: var(--dark-border-color);
        }
        
        .gateway-modal-title {
            font-size: 1.3rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .gateway-modal-title {
            color: var(--dark-text-primary);
        }
        
        .gateway-modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: var(--secondary-bg);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .gateway-modal-close {
            background: var(--dark-accent-bg);
            color: var(--dark-text-secondary);
        }
        
        .gateway-modal-close:hover {
            background: var(--error);
            color: white;
            transform: rotate(90deg);
        }
        
        /* Provider Selection View */
        .provider-selection {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .provider-selection.hidden {
            display: none;
        }
        
        .provider-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .provider-group-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .provider-group-title {
            color: var(--dark-text-primary);
        }
        
        .provider-group-title i {
            color: var(--accent-blue);
        }
        
        .provider-options {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .provider-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .provider-option {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
        }
        
        .provider-option:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .provider-option-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--text-primary);
            font-size: 1.2rem;
        }
        
        body[data-theme="dark"] .provider-option-icon {
            color: var(--dark-text-primary);
        }
        
        .provider-option-content {
            flex: 1;
        }
        
        .provider-option-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .provider-option-name {
            color: var(--dark-text-primary);
        }
        
        .provider-option-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        body[data-theme="dark"] .provider-option-desc {
            color: var(--dark-text-secondary);
        }
        
        /* Gateway Selection View */
        .gateway-selection {
            display: none;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .gateway-selection.active {
            display: flex;
        }
        
        .gateway-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .gateway-group-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .gateway-group-title {
            color: var(--dark-text-primary);
        }
        
        .gateway-group-title i {
            color: var(--accent-blue);
        }
        
        .gateway-options {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .gateway-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--user-item-bg-light);
            border: 1px solid var(--user-item-border-light);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .gateway-option {
            background: var(--user-item-bg-dark);
            border-color: var(--user-item-border-dark);
        }
        
        .gateway-option:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .gateway-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 1rem;
            cursor: pointer;
            accent-color: var(--accent-blue);
        }
        
        .gateway-option-content {
            flex: 1;
        }
        
        .gateway-option-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }
        
        body[data-theme="dark"] .gateway-option-name {
            color: var(--dark-text-primary);
        }
        
        .gateway-option-name i {
            font-size: 1.1rem;
        }
        
        .gateway-option-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        body[data-theme="dark"] .gateway-option-desc {
            color: var(--dark-text-secondary);
        }
        
        .gateway-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-charge {
            background: rgba(245,158,11,0.15);
            color: var(--warning);
        }
        
        .badge-auth {
            background: rgba(6,182,212,0.15);
            color: var(--accent-cyan);
        }
        
        .badge-maintenance {
            background-color: #ef4444;
            color: white;
        }
        
        .gateway-modal-footer {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .gateway-modal-footer {
            border-color: var(--dark-border-color);
        }
        
        .gateway-btn-back {
            flex: 1;
            padding: 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg);
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        body[data-theme="dark"] .gateway-btn-back {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .gateway-btn-back:hover {
            transform: translateY(-2px);
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        
        .gateway-btn-save {
            flex: 1;
            padding: 0.8rem;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .gateway-btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .gateway-btn-cancel {
            flex: 1;
            padding: 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--secondary-bg);
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        body[data-theme="dark"] .gateway-btn-cancel {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary);
        }
        
        .gateway-btn-cancel:hover {
            transform: translateY(-2px);
        }
        
        /* Fix for Global Statistics text in dark mode */
        body[data-theme="dark"] .gs-title {
            color: var(--dark-text-primary) !important;
        }
        
        body[data-theme="dark"] .gs-sub {
            color: var(--dark-text-secondary) !important;
        }
        
        /* Status indicator for active gateways */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 0.5rem;
        }
        
        .status-active {
            background-color: var(--success-green);
        }
        
        .status-inactive {
            background-color: var(--error);
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

                <!-- Progress Counters - Beast Level with Better Spacing -->
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card charged">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                        </div>
                        <div id="charged-value" class="stat-value">0</div>
                        <div class="stat-label">HIT|CHARGED</div>
                    </div>
                    <div class="stat-card approved">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div id="approved-value" class="stat-value">0</div>
                        <div class="stat-label">LIVE|APPROVED</div>
                    </div>
                    <div class="stat-card declined">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <div id="declined-value" class="stat-value">0</div>
                        <div class="stat-label">DEAD|DECLINED</div>
                    </div>
                    <div class="stat-card checked">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-check-double"></i>
                            </div>
                        </div>
                        <div id="checked-value" class="stat-value">0 / 0</div>
                        <div class="stat-label">CHECKED</div>
                    </div>
                </div>

                <!-- Global Statistics Section - Better Spacing -->
                <div class="gs-panel mt-6">
                    <div class="gs-head">
                        <div class="gs-chip">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
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
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.89 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                            </div>
                            <div id="gTotalUsers" class="gs-num">—</div>
                            <div class="gs-label">Total Users</div>
                        </div>

                        <div class="gs-card gs-purple">
                            <div class="gs-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1-.18 1-.18 1-.18 1-.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1z"/>
                                </svg>
                            </div>
                            <div id="gTotalHits" class="gs-num">—</div>
                            <div class="gs-label">Total Checked Cards</div>
                        </div>

                        <div class="gs-card gs-red">
                            <div class="gs-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
                                </svg>
                            </div>
                            <div id="gChargeCards" class="gs-num">—</div>
                            <div class="gs-label">Charge Cards</div>
                        </div>

                        <div class="gs-card gs-green">
                            <div class="gs-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 13h3l2-6 4 12 2-6h5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div id="gLiveCards" class="gs-num">—</div>
                            <div class="gs-label">Live Cards</div>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Online Users Section -->
                <div class="mobile-online-users" id="mobileOnlineUsers" style="display: none;">
                    <div class="mobile-online-users-header">
                        <div class="mobile-online-users-title">
                            <i class="fas fa-users"></i> Online Users
                        </div>
                        <div class="mobile-online-users-count" id="mobileOnlineUsersCount">
                            <i class="fas fa-circle"></i>
                            <span id="mobileOnlineCount">0</span> online
                        </div>
                    </div>
                    <div class="mobile-online-users-list" id="mobileOnlineUsersList">
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <h3>No Users Online</h3>
                            <p>No other users are currently online</p>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Top Users Section -->
                <div class="mobile-top-users" id="mobileTopUsers" style="display: none;">
                    <div class="mobile-top-users-header">
                        <div class="mobile-top-users-title">
                            <i class="fas fa-trophy"></i> Top Users
                        </div>
                    </div>
                    <div class="mobile-top-users-list" id="mobileTopUsersList">
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <h3>No Top Users</h3>
                            <p>No top users data available</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section" id="page-checking">
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
            <p class="page-subtitle">𝐂𝐡𝐞𝐜𝐤 𝐲𝐨𝐮𝐫 𝐜𝐚𝐫𝐝𝐬 𝐨𝐧 𝐦𝐮𝐥𝐭𝐢𝐩𝐥𝐞 𝐠𝐚𝐭𝐞𝐰𝐚𝐲𝐬</p>

            <div class="checker-section">
                <div class="checker-header">
                    <div class="checker-title">
                        <i class="fas fa-shield-alt"></i> Card Checker
                    </div>
                    <button class="settings-btn" onclick="openGatewayModal()">
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
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</h1>
            <p class="page-subtitle">𝐆𝐞𝐧𝐞𝐫𝐚𝐭𝐞 𝐂𝐚𝐫𝐝𝐬</p>

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
                        
                        <div class="user-stat-item checked">
                            <div class="user-stat-icon">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <div class="user-stat-content">
                                <div class="user-stat-label">Total Checked</div>
                                <div class="user-stat-value" id="profile-checked-value">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Right Sidebar with Online Users and Top Users -->
    <aside class="right-sidebar" id="rightSidebar">
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
    </aside>

    <!-- Enhanced Gateway Selection Modal -->
    <div class="gateway-modal" id="gatewayModal">
        <div class="gateway-modal-content">
            <div class="gateway-modal-header">
                <div class="gateway-modal-title">
                    <i class="fas fa-cog"></i> Gateway Settings
                </div>
                <button class="gateway-modal-close" onclick="closeGatewayModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Provider Selection View -->
            <div class="provider-selection" id="providerSelection">
                <div class="provider-group">
                    <div class="provider-group-title">
                        <i class="fas fa-bolt"></i> Payment Providers
                    </div>
                    <div class="provider-options">
                        <div class="provider-option" onclick="showProviderGateways('stripe')">
                            <div class="provider-option-icon">
                                <i class="fab fa-stripe"></i>
                            </div>
                            <div class="provider-option-content">
                                <div class="provider-option-name">
                                    Stripe
                                </div>
                                <div class="provider-option-desc">Payment processing with multiple options</div>
                            </div>
                        </div>
                        <div class="provider-option" onclick="showProviderGateways('shopify')">
                            <div class="provider-option-icon">
                                <i class="fab fa-shopify"></i>
                            </div>
                            <div class="provider-option-content">
                                <div class="provider-option-name">
                                    Shopify
                                </div>
                                <div class="provider-option-desc">E-commerce payment processing</div>
                            </div>
                        </div>
                        <div class="provider-option" onclick="showProviderGateways('paypal')">
                            <div class="provider-option-icon">
                                <i class="fab fa-paypal"></i>
                            </div>
                            <div class="provider-option-content">
                                <div class="provider-option-name">
                                    PayPal
                                </div>
                                <div class="provider-option-desc">Online payment gateway</div>
                            </div>
                        </div>
                        <div class="provider-option" onclick="showProviderGateways('razorpay')">
                            <div class="provider-option-icon">
                                <img src="https://cdn.razorpay.com/logo.svg" alt="Razorpay" 
                                    style="width:20px; height:20px; object-fit:contain;">
                            </div>
                            <div class="provider-option-content">
                                <div class="provider-option-name">
                                    Razorpay
                                </div>
                                <div class="provider-option-desc">Indian payment gateway</div>
                            </div>
                        </div>
                        <div class="provider-option" onclick="showProviderGateways('authnet')">
                            <div class="provider-option-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="provider-option-content">
                                <div class="provider-option-name">
                                    Authnet
                                </div>
                                <div class="provider-option-desc">Authorize.net payment gateway</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateway Selection View -->
            <div class="gateway-selection" id="gatewaySelection">
                <!-- Stripe Gateways -->
                <div class="gateway-group" id="stripe-gateways" style="display: none;">
                    <div class="gateway-group-title">
                        <i class="fab fa-stripe"></i> Stripe Gateways
                    </div>
                    <div class="gateway-options">
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/stripe1$.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Stripe
                                    <span class="gateway-badge badge-charge">1$ Charge</span>
                                </div>
                                <div class="gateway-option-desc">Payment processing with $1 charge</div>
                            </div>
                        </label>
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/stripe5$.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Stripe
                                    <span class="gateway-badge badge-charge">5$ Charge</span>
                                </div>
                                <div class="gateway-option-desc">Payment processing with $5 charge</div>
                            </div>
                        </label>
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/stripeauth.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Stripe
                                    <span class="gateway-badge badge-auth">Auth</span>
                                </div>
                                <div class="gateway-option-desc">Authorization only, no charge</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Shopify Gateways -->
                <div class="gateway-group" id="shopify-gateways" style="display: none;">
                    <div class="gateway-group-title">
                        <i class="fab fa-shopify"></i> Shopify Gateways
                    </div>
                    <div class="gateway-options">
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/shopify1$.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Shopify
                                    <span class="gateway-badge badge-charge">1$ Charge</span>
                                </div>
                                <div class="gateway-option-desc">E-commerce payment processing</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- PayPal Gateways -->
                <div class="gateway-group" id="paypal-gateways" style="display: none;">
                    <div class="gateway-group-title">
                        <i class="fab fa-paypal"></i> PayPal Gateways
                    </div>
                    <div class="gateway-options">
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/paypal0.1$.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    PayPal
                                    <span class="gateway-badge badge-charge">0.1$ Charge</span>
                                </div>
                                <div class="gateway-option-desc">Payment processing with $0.1 charge</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Razorpay Gateways -->
                <div class="gateway-group" id="razorpay-gateways" style="display: none;">
                    <div class="gateway-group-title">
                        <img src="https://cdn.razorpay.com/logo.svg" alt="Razorpay" 
                            style="width:20px; height:20px; object-fit:contain;"> Razorpay Gateways
                    </div>
                    <div class="gateway-options">
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/razorpay0.10$.php" disabled>
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Razorpay
                                    <span class="gateway-badge badge-charge">0.10$ Charge</span>
                                    <span class="gateway-badge badge-maintenance">Under Maintenance</span>
                                </div>
                                <div class="gateway-option-desc">Indian payment gateway</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Authnet Gateways -->
                <div class="gateway-group" id="authnet-gateways" style="display: none;">
                    <div class="gateway-group-title">
                        <i class="fas fa-credit-card"></i> Authnet Gateways
                    </div>
                    <div class="gateway-options">
                        <label class="gateway-option">
                            <input type="radio" name="gateway" value="gate/authnet1$.php">
                            <div class="gateway-option-content">
                                <div class="gateway-option-name">
                                    Authnet
                                    <span class="gateway-badge badge-charge">1$ Charge</span>
                                </div>
                                <div class="gateway-option-desc">Authorize.net payment gateway</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="gateway-modal-footer">
                <button class="gateway-btn-back" id="gatewayBtnBack" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button class="gateway-btn-save" id="gatewayBtnSave">
                    <i class="fas fa-check"></i> Save & Apply
                </button>
                <button class="gateway-btn-cancel" id="gatewayBtnCancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="indeex.js?v=<?= time(); ?>"></script>
    <script>
        // Performance optimization for smooth sidebar animation
        document.addEventListener('DOMContentLoaded', function() {
            // Use requestAnimationFrame for smoother animations
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuToggle = document.getElementById('menuToggle');
            let isAnimating = false;
            
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent any default behavior
                if (isAnimating) return;
                isAnimating = true;
                
                if (sidebar.classList.contains('open')) {
                    // Close sidebar
                    sidebar.classList.remove('open');
                    mainContent.classList.remove('sidebar-open');
                } else {
                    // Open sidebar
                    sidebar.classList.add('open');
                    mainContent.classList.add('sidebar-open');
                }
                
                // Reset animation flag after animation completes
                setTimeout(() => {
                    isAnimating = false;
                }, 300);
            });
            
            // Optimize scrolling performance
            let ticking = false;
            function updateScrollPosition() {
                // Add any scroll-based updates here
                ticking = false;
            }
            
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(updateScrollPosition);
                    ticking = true;
                }
            });
            
            // Initialize gateway settings
            initializeGatewaySettings();
            
            // Check for admin user and add admin badge
            checkForAdminUser();
            
            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            const icon = document.querySelector('.theme-toggle-slider i');
            if (icon) {
                icon.className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
        
        // Gateway settings functions
        function initializeGatewaySettings() {
            const gatewayBtnBack = document.getElementById('gatewayBtnBack');
            const gatewayBtnSave = document.getElementById('gatewayBtnSave');
            const gatewayBtnCancel = document.getElementById('gatewayBtnCancel');
            
            // Add click events to buttons
            gatewayBtnBack.addEventListener('click', function() {
                showProviderSelection();
            });
            
            gatewayBtnSave.addEventListener('click', function() {
                saveGatewaySettings();
            });
            
            gatewayBtnCancel.addEventListener('click', function() {
                closeGatewayModal();
            });
            
            // Load saved gateway settings
            loadSavedGatewaySettings();
        }
        
        function loadSavedGatewaySettings() {
            const savedGateway = localStorage.getItem('selectedGateway');
            if (savedGateway) {
                const radioInput = document.querySelector(`input[name="gateway"][value="${savedGateway}"]`);
                if (radioInput) {
                    radioInput.checked = true;
                }
            }
        }
        
        function saveGatewaySettings() {
            const selectedGateway = document.querySelector('input[name="gateway"]:checked');
            
            if (!selectedGateway) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Gateway Selected',
                    text: 'Please select a gateway first',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
            
            // Save settings
            localStorage.setItem('selectedGateway', selectedGateway.value);
            
            // Get gateway name for display
            const gatewayName = selectedGateway.parentElement.querySelector('.gateway-option-name').textContent.trim();
            
            // Close modal immediately
            closeGatewayModal();
            
            // Show success message after modal is closed
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Gateway Settings Updated!',
                    text: `Now using: ${gatewayName}`,
                    confirmButtonColor: '#10b981',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 300); // Small delay to ensure modal is fully closed
        }
        
        function openGatewayModal() {
            document.getElementById('gatewayModal').classList.add('active');
            showProviderSelection();
            loadSavedGatewaySettings();
        }
        
        function closeGatewayModal() {
            const modal = document.getElementById('gatewayModal');
            modal.classList.remove('active');
            
            // Reset to provider selection view for next time
            setTimeout(() => {
                showProviderSelection();
            }, 300); // Wait for the modal to close completely
        }
        
        function showProviderSelection() {
            document.getElementById('providerSelection').classList.remove('hidden');
            document.getElementById('gatewaySelection').classList.remove('active');
            document.getElementById('gatewayBtnBack').style.display = 'none';
        }
        
        function showProviderGateways(provider) {
            // Hide all gateway groups
            const gatewayGroups = document.querySelectorAll('.gateway-group');
            gatewayGroups.forEach(group => {
                group.style.display = 'none';
            });
            
            // Show the selected provider's gateways
            document.getElementById(`${provider}-gateways`).style.display = 'block';
            
            // Switch views
            document.getElementById('providerSelection').classList.add('hidden');
            document.getElementById('gatewaySelection').classList.add('active');
            document.getElementById('gatewayBtnBack').style.display = 'flex';
        }
        
        // Function to get saved gateway settings
        function getSavedGatewaySettings() {
            const gateway = localStorage.getItem('selectedGateway');
            
            if (gateway) {
                return { gateway };
            }
            
            return null;
        }
        
        // Function to get current API key with fallback
        function getCurrentApiKey() {
            if (!window.API_KEY) {
                console.warn('API key is not set!');
            }
            return window.API_KEY || '';
        }
        
        // Theme toggle function
        function toggleTheme() {
            const body = document.body;
            const theme = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = document.querySelector('.theme-toggle-slider i');
            if (icon) {
                icon.className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            }
            
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'success',
                title: `${theme === 'light' ? 'Light' : 'Dark'} Mode`,
                showConfirmButton: false, 
                timer: 1500
            });
        }
        
        // Page navigation function
        function showPage(pageName) {
            // Add a small delay to ensure smooth transition
            setTimeout(() => {
                document.querySelectorAll('.page-section').forEach(page => {
                    page.classList.remove('active');
                });
                const pageElement = document.getElementById('page-' + pageName);
                if (pageElement) {
                    pageElement.classList.add('active');
                    
                    // Load profile data when profile page is shown
                    if (pageName === 'profile') {
                        loadUserProfile();
                    }
                }
                
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                if (event && event.target) {
                    const eventTarget = event.target.closest('.sidebar-link');
                    if (eventTarget) {
                        eventTarget.classList.add('active');
                    }
                }
            }, 50); // Small delay for smoother transition
        }
        
        // Sidebar functions
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            if (sidebar) sidebar.classList.remove('open');
            if (mainContent) mainContent.classList.remove('sidebar-open');
        }
        
        // Logout function
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
                    localStorage.clear(); // Clear user stats on logout
                    window.location.href = 'login.php';
                }
            });
        }
        
        // Function to load user profile data
        function loadUserProfile() {
            // Get user data from session
            const userData = {
                name: document.querySelector('.user-name') ? document.querySelector('.user-name').textContent : 'Unknown User',
                username: document.querySelector('.user-username') ? document.querySelector('.user-username').textContent : '@unknown',
                photo_url: document.querySelector('.user-avatar') ? document.querySelector('.user-avatar').src : 'https://ui-avatars.com/api/?name=U&background=3b82f6&color=fff&size=120'
            };
            
            // Update profile information
            const profileName = document.getElementById('profileName');
            const profileUsername = document.getElementById('profileUsername');
            const profileAvatar = document.getElementById('profileAvatar');
            
            if (profileName) profileName.textContent = userData.name || 'Unknown User';
            if (profileUsername) profileUsername.textContent = userData.username || '@unknown';
            if (profileAvatar) profileAvatar.src = userData.photo_url || 'https://ui-avatars.com/api/?name=U&background=3b82f6&color=fff&size=120';
            
            // Load user statistics
            loadUserStatistics();
        }
        
        // Function to load user statistics
        function loadUserStatistics() {
            // Get statistics from localStorage or fetch from server
            const stats = getUserStatistics();
            
            // Update statistics values
            updateProfileStat('charged', stats.charged || 0);
            updateProfileStat('approved', stats.approved || 0);
            updateProfileStat('threeds', stats.threeds || 0);
            updateProfileStat('declined', stats.declined || 0);
            updateProfileStat('checked', stats.total || 0);
        }
        
        // Function to get user statistics
        function getUserStatistics() {
            // Try to get from localStorage first
            let stats = localStorage.getItem('userStats');
            if (stats) {
                return JSON.parse(stats);
            }
            
            // Default statistics
            return {
                total: 0,
                charged: 0,
                approved: 0,
                threeds: 0,
                declined: 0
            };
        }
        
        // Function to update a single profile statistic
        function updateProfileStat(type, value) {
            const element = document.getElementById(`profile-${type}-value`);
            if (element) {
                element.textContent = value;
                
                // Add animation effect
                element.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 300);
            }
        }
        
        // Function to check for admin user and add admin badge
        function checkForAdminUser() {
            // This function is now called after updating the user lists
            // The admin badge is already added in the update functions above
        }
        
        // Make functions globally accessible
        window.toggleTheme = toggleTheme;
        window.showPage = showPage;
        window.closeSidebar = closeSidebar;
        window.openGatewayModal = openGatewayModal;
        window.closeGatewayModal = closeGatewayModal;
        window.showProviderSelection = showProviderSelection;
        window.showProviderGateways = showProviderGateways;
        window.loadSavedGatewaySettings = loadSavedGatewaySettings;
        window.saveGatewaySettings = saveGatewaySettings;
        window.logout = logout;
        window.loadUserProfile = loadUserProfile;
    </script>
</body>
</html>
