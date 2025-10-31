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
            
            /* Admin color */
            --admin-color: #ef4444;
            --admin-bg: rgba(239, 68, 68, 0.15);
            --admin-border: rgba(239, 68, 68, 0.3);
        }
        
        /* Smooth transitions for all elements */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, 
                        box-shadow 0.3s ease, transform 0.2s ease, opacity 0.3s ease;
        }
        
        body {
            font-family: Inter, sans-serif; 
            background: var(--primary-bg);
            color: var(--text-primary); 
            min-height: 100vh; 
            overflow-x: hidden;
            position: relative;
            font-size: 14px; /* Reduced base font size */
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
            background: rgba(15, 23, 42, 0.95); /* Always dark with transparency */
            backdrop-filter: blur(20px);
            padding: 0.5rem 0.8rem; /* Reduced padding */
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            z-index: 1000; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
            height: 50px; /* Reduced height */
            box-shadow: var(--shadow-md);
        }
        
        .navbar-brand {
            display: flex; 
            align-items: center; 
            gap: 0.6rem; /* Reduced gap */
            font-size: 1.2rem; /* Reduced font size */
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
            font-size: 1.2rem; /* Reduced size */
        }
        
        .navbar-actions { 
            display: flex; 
            align-items: center; 
            gap: 0.6rem; /* Reduced gap */
        }
        
        .theme-toggle {
            width: 40px; /* Reduced size */
            height: 20px; /* Reduced size */
            background: var(--dark-accent-bg);
            border-radius: 10px; /* Reduced border radius */
            cursor: pointer; 
            border: 1px solid var(--dark-border-color);
            position: relative; 
            transition: all 0.3s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .theme-toggle-slider {
            position: absolute; 
            width: 16px; /* Reduced size */
            height: 16px; /* Reduced size */
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            left: 2px; 
            top: 2px;
            transition: transform 0.3s ease; 
            display: flex;
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-size: 0.5rem; /* Reduced font size */
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }
        
        [data-theme="dark"] .theme-toggle-slider { 
            transform: translateX(18px); /* Adjusted for smaller toggle */
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
        }
        
        .user-info {
            display: flex; 
            align-items: center; 
            gap: 0.6rem; /* Reduced gap */
            padding: 0.3rem 0.6rem; /* Reduced padding */
            background: rgba(15, 23, 42, 0.8); 
            border-radius: 10px; /* Reduced border radius */
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
            width: 28px; /* Reduced size */
            height: 28px; /* Reduced size */
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
            max-width: 80px; /* Reduced width */
            overflow: hidden; 
            text-overflow: ellipsis;
            white-space: nowrap; 
            font-size: 0.8rem; /* Reduced font size */
        }
        
        .user-username {
            font-size: 0.65rem; /* Reduced font size */
            color: rgba(255,255,255,0.7); 
            max-width: 80px; /* Reduced width */
            overflow: hidden; 
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .menu-toggle {
            color: #ffffff !important; 
            font-size: 1.1rem; /* Reduced font size */
            transition: all 0.3s; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            width: 36px; /* Reduced size */
            height: 36px; /* Reduced size */
            border-radius: 8px; /* Reduced border radius */
            background: rgba(59, 130, 246, 0.2);
            flex-shrink: 0; 
            cursor: pointer;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .menu-toggle:hover { 
            transform: scale(1.05); 
            background: var(--accent-blue); 
            color: white !important;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        
        /* Beast Level Sidebar - Optimized for performance and reduced size */
        .sidebar {
            position: fixed; 
            left: 0; 
            top: 50px; /* Adjusted for reduced navbar height */
            bottom: 0; 
            width: 260px; /* Reduced width */
            background: rgba(30, 41, 59, 0.95); 
            border-right: 1px solid var(--dark-border-color);
            padding: 1rem 0; /* Reduced padding */
            z-index: 999; 
            overflow-y: auto;
            transform: translateX(-100%); 
            transition: transform 0.3s ease-out; /* Increased duration for smoother animation */
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
            will-change: transform; /* Hardware acceleration hint */
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .sidebar-menu { 
            list-style: none; 
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 0.8rem; /* Reduced padding */
        }
        
        .sidebar-item { 
            margin: 0.3rem 0; /* Reduced margin */
        }
        
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 0.6rem; /* Reduced gap */
            padding: 0.6rem 0.8rem; /* Reduced padding */
            color: var(--dark-text-secondary);
            border-radius: 10px; /* Reduced border radius */
            cursor: pointer; 
            font-size: 0.85rem; /* Reduced font size */
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
            width: 18px; /* Reduced width */
            text-align: center; 
            font-size: 0.9rem; /* Reduced font size */
        }
        
        .sidebar-divider { 
            height: 1px; 
            background: var(--dark-border-color); 
            margin: 1rem 0.8rem; /* Reduced margin */
        }
        
        /* Beast Level Main Content - Reduced size */
        .main-content {
            margin-left: 0; 
            margin-top: 50px; /* Adjusted for reduced navbar height */
            padding: 1rem; /* Reduced padding */
            min-height: calc(100vh - 50px); /* Adjusted for reduced navbar height */
            position: relative; 
            z-index: 1;
            transition: margin-left 0.3s ease-out; /* Increased duration for smoother animation */
            margin-right: 380px; /* Reduced space for right sidebar */
        }
        
        .main-content.sidebar-open { 
            margin-left: 260px; /* Adjusted for reduced sidebar width */
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
            font-size: 1.6rem; /* Reduced font size */
            font-weight: 900; 
            margin-bottom: 0.6rem; /* Reduced margin */
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
        }
        
        .page-subtitle { 
            color: var(--text-secondary); 
            margin-bottom: 1.2rem; /* Reduced margin */
            font-size: 0.9rem; /* Reduced font size */
            font-weight: 500;
        }
        
        body[data-theme="dark"] .page-subtitle {
            color: var(--dark-text-secondary);
        }
        
        /* Beast Level Dashboard - Reduced size */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem; /* Reduced gap */
            margin-bottom: 1.5rem; /* Reduced margin */
        }
        
        .welcome-banner {
            background: var(--card-bg);
            border-radius: 16px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.2rem; /* Reduced gap */
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
            height: 3px; /* Reduced height */
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1.2rem; /* Reduced gap */
            position: relative;
            z-index: 1;
        }
        
        .welcome-icon {
            width: 40px; /* Reduced size */
            height: 40px; /* Reduced size */
            border-radius: 12px; /* Reduced border radius */
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem; /* Reduced font size */
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }
        
        .welcome-text h2 {
            font-size: 1.3rem; /* Reduced font size */
            margin-bottom: 0.2rem; /* Reduced margin */
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.9rem; /* Reduced font size */
        }
        
        body[data-theme="dark"] .welcome-text p {
            color: var(--dark-text-secondary);
        }
        
        /* Beast Level Stats Grid - Better Spacing and Reduced Size */
        .stats-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); /* Reduced min-width */
            gap: 1.2rem; /* Reduced gap */
            margin-bottom: 1.5rem; /* Reduced margin */
        }
        
        .stat-card {
            background: var(--card-bg); 
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            position: relative;
            transition: all 0.3s ease; 
            box-shadow: var(--shadow-md); 
            min-height: 100px; /* Reduced height */
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
            height: 3px; /* Reduced height */
        }
        
        .stat-card.charged::before { background: var(--stat-charged); }
        .stat-card.approved::before { background: var(--stat-approved); }
        .stat-card.declined::before { background: var(--stat-declined); }
        .stat-card.checked::before { background: var(--stat-checked); }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -15px; /* Adjusted for smaller card */
            right: -15px; /* Adjusted for smaller card */
            width: 60px; /* Reduced size */
            height: 60px; /* Reduced size */
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
            transform: translateY(-3px); /* Reduced transform */
            box-shadow: var(--shadow-lg);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem; /* Reduced margin */
            position: relative;
            z-index: 1;
        }
        
        .stat-icon {
            width: 32px; /* Reduced size */
            height: 32px; /* Reduced size */
            border-radius: 8px; /* Reduced border radius */
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1rem; /* Reduced font size */
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .stat-card.charged .stat-icon { background: var(--stat-charged); }
        .stat-card.approved .stat-icon { background: var(--stat-approved); }
        .stat-card.declined .stat-icon { background: var(--stat-declined); }
        .stat-card.checked .stat-icon { background: var(--stat-checked); }
        
        .stat-value { 
            font-size: 1.8rem; /* Reduced font size */
            font-weight: 900; 
            margin-bottom: 0.4rem; /* Reduced margin */
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
            font-size: 0.7rem; /* Reduced font size */
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
            border-radius:14px; /* Reduced border radius */
            padding:1.5rem; /* Reduced padding */
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
            height: 3px; /* Reduced height */
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
        }
        
        .gs-head{display:flex;align-items:center;gap:10px;margin-bottom:1.5rem; /* Reduced margin */}
        .gs-chip{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;
          background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.25)}
        .gs-title{font-weight:800;color:var(--text-primary);font-size:1.1rem} /* Reduced font size */
        .gs-sub{font-size:0.8rem;color:var(--text-secondary);margin-top:2px} /* Reduced font size */
        .gs-grid{display:grid;gap:1.2rem} /* Reduced gap */
        @media (min-width:640px){.gs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1280px){.gs-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}

        .gs-card{
          position:relative;border-radius:10px;padding:1.2rem; /* Reduced padding */
          border:1px solid var(--border-color);
          box-shadow: var(--shadow-sm);
          color:var(--text-primary);
          transition: all 0.3s ease;
        }
        
        body[data-theme="dark"] .gs-card {
            background: rgba(30, 41, 59, 0.7); /* Added background color in dark mode */
            border-color: var(--dark-border-color);
            color: var(--dark-text-primary); /* Fixed text color in dark mode */
        }
        
        .gs-card:hover {
            transform: translateY(-2px); /* Reduced transform */
            box-shadow: var(--shadow-md);
        }
        
        .gs-card .gs-icon{
          width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;
          margin-bottom:0.8rem;border:1px solid var(--border-color) /* Reduced margin */
        }
        .gs-card .gs-icon svg{width:16px;height:16px;display:block;opacity:.95} /* Reduced size */
        .gs-num{font-weight:800;font-size:1.8rem;line-height:1} /* Reduced font size */
        .gs-label{font-size:0.8rem;color:var(--text-secondary);margin-top:0.6rem} /* Reduced font size and margin */
        
        body[data-theme="dark"] .gs-label {
            color: var(--dark-text-secondary); /* Fixed label color in dark mode */
        }
        
        .gs-blue   { background: linear-gradient(135deg, rgba(59,130,246,0.3), rgba(59,130,246,0.2)); }
        .gs-green  { background: linear-gradient(135deg, rgba(16,185,129,0.3), rgba(16,185,129,0.2)); }
        .gs-red    { background: linear-gradient(135deg, rgba(239,68,68,0.3), rgba(239,68,68,0.2)); }
        .gs-purple { background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(139,92,246,0.2)); }
        
        body[data-theme="dark"] .gs-blue   { background: linear-gradient(135deg, rgba(59,130,246,0.4), rgba(59,130,246,0.3)); }
        body[data-theme="dark"] .gs-green  { background: linear-gradient(135deg, rgba(16,185,129,0.4), rgba(16,185,129,0.3)); }
        body[data-theme="dark"] .gs-red    { background: linear-gradient(135deg, rgba(239,68,68,0.4), rgba(239,68,68,0.3)); }
        body[data-theme="dark"] .gs-purple { background: linear-gradient(135deg, rgba(139,92,246,0.4), rgba(139,92,246,0.3)); }
        
        /* Beast Level Right Sidebar - Light Mode Fix and Reduced Size */
        .right-sidebar {
            position: fixed;
            right: 0;
            top: 50px; /* Adjusted for reduced navbar height */
            bottom: 0;
            width: 380px; /* Reduced width */
            background: var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-left: 1px solid var(--border-color); /* Changed to use border-color variable */
            padding: 1.2rem; /* Reduced padding */
            display: flex;
            flex-direction: column;
            gap: 1.2rem; /* Reduced gap */
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
            background: var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            border: 1px solid var(--border-color); /* Changed to use border-color variable */
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
            height: 3px; /* Reduced height */
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-green));
            animation: gradientShift 3s ease infinite;
        }
        
        .online-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem; /* Reduced margin */
            position: relative;
            z-index: 1;
        }
        
        .online-users-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            color: var(--text-primary); /* Changed to use text-primary variable for light mode */
            display: flex;
            align-items: center;
            gap: 0.4rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .online-users-title {
            color: var(--dark-text-primary);
        }
        
        .online-users-title i {
            color: var(--accent-cyan);
        }
        
        .online-users-count {
            font-size: 0.75rem; /* Reduced font size */
            color: var(--text-secondary); /* Changed to use text-secondary variable for light mode */
            background: rgba(59, 130, 246, 0.1);
            padding: 0.4rem 0.6rem; /* Reduced padding */
            border-radius: 16px; /* Reduced border radius */
            display: flex;
            align-items: center;
            gap: 0.3rem; /* Reduced gap */
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        body[data-theme="dark"] .online-users-count {
            color: var(--dark-text-secondary);
            background: rgba(59, 130, 246, 0.2);
        }
        
        .online-users-count i {
            color: var(--success-green);
            font-size: 0.5rem; /* Reduced font size */
        }
        
        .online-users-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.3rem; /* Reduced padding */
            position: relative;
            z-index: 1;
        }
        
        /* Custom scrollbar */
        .online-users-list::-webkit-scrollbar {
            width: 3px; /* Reduced width */
        }
        
        .online-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg); /* Changed to use secondary-bg variable for light mode */
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
            gap: 0.8rem; /* Reduced gap */
            padding: 0.8rem; /* Reduced padding */
            background: var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-radius: 10px; /* Reduced border radius */
            border: 1px solid var(--border-color); /* Changed to use border-color variable */
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 0.8rem; /* Reduced margin */
        }
        
        body[data-theme="dark"] .online-user-item {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
        }
        
        .online-user-item:hover {
            transform: translateX(3px); /* Reduced transform */
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .online-user-avatar-container {
            position: relative;
        }
        
        .online-user-avatar {
            width: 36px; /* Reduced size */
            height: 36px; /* Reduced size */
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
            width: 10px; /* Reduced size */
            height: 10px; /* Reduced size */
            background-color: var(--success-green);
            border: 2px solid var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-radius: 50%;
        }
        
        body[data-theme="dark"] .online-indicator {
            border-color: var(--dark-card-bg);
        }
        
        .online-user-info {
            flex: 1;
            min-width: 0;
        }
        
        .online-user-name {
            font-weight: 700;
            font-size: 0.85rem; /* Reduced font size */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary); /* Changed to use text-primary variable for light mode */
        }
        
        body[data-theme="dark"] .online-user-name {
            color: var(--dark-text-primary);
        }
        
        .online-user-username {
            font-size: 0.7rem; /* Reduced font size */
            color: var(--text-secondary); /* Changed to use text-secondary variable for light mode */
            margin-bottom: 0.1rem; /* Reduced margin */
        }
        
        body[data-theme="dark"] .online-user-username {
            color: var(--dark-text-secondary);
        }
        
        /* Top Users Section - Beast Level - Light Mode Fix and Reduced Size */
        .top-users-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            border: 1px solid var(--border-color); /* Changed to use border-color variable */
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
            height: 3px; /* Reduced height */
            background: linear-gradient(90deg, var(--accent-purple), var(--accent-blue), var(--accent-cyan));
            animation: gradientShift 3s ease infinite;
        }
        
        .top-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem; /* Reduced margin */
            position: relative;
            z-index: 1;
        }
        
        .top-users-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            color: var(--text-primary); /* Changed to use text-primary variable for light mode */
            display: flex;
            align-items: center;
            gap: 0.4rem; /* Reduced gap */
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
            padding-right: 0.3rem; /* Reduced padding */
            position: relative;
            z-index: 1;
        }
        
        .top-users-list::-webkit-scrollbar {
            width: 3px; /* Reduced width */
        }
        
        .top-users-list::-webkit-scrollbar-track {
            background: var(--secondary-bg); /* Changed to use secondary-bg variable for light mode */
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
            gap: 0.8rem; /* Reduced gap */
            padding: 0.8rem; /* Reduced padding */
            background: var(--card-bg); /* Changed to use card-bg variable for light mode */
            border-radius: 10px; /* Reduced border radius */
            border: 1px solid var(--border-color); /* Changed to use border-color variable */
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 0.8rem; /* Reduced margin */
        }
        
        body[data-theme="dark"] .top-user-item {
            background: var(--dark-card-bg);
            border-color: var(--dark-border-color);
        }
        
        .top-user-item:hover {
            transform: translateX(3px); /* Reduced transform */
            border-color: var(--accent-purple);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }
        
        .top-user-avatar-container {
            position: relative;
        }
        
        .top-user-avatar {
            width: 36px; /* Reduced size */
            height: 36px; /* Reduced size */
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-purple);
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(139, 92, 246, 0.4);
        }
        
        .top-user-info {
            flex: 1;
            min-width: 0;
        }
        
        .top-user-name {
            font-weight: 700;
            font-size: 0.85rem; /* Reduced font size */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary); /* Changed to use text-primary variable for light mode */
        }
        
        body[data-theme="dark"] .top-user-name {
            color: var(--dark-text-primary);
        }
        
        .top-user-username {
            font-size: 0.7rem; /* Reduced font size */
            color: var(--text-secondary); /* Changed to use text-secondary variable for light mode */
            margin-bottom: 0.1rem; /* Reduced margin */
        }
        
        body[data-theme="dark"] .top-user-username {
            color: var(--dark-text-secondary);
        }
        
        .top-user-hits {
            font-size: 0.75rem; /* Reduced font size */
            font-weight: 700;
            color: var(--text-primary); /* Changed to use text-primary variable for light mode */
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 0.2rem 0.4rem; /* Reduced padding */
            border-radius: 6px; /* Reduced border radius */
            background-color: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        
        body[data-theme="dark"] .top-user-hits {
            color: var(--dark-text-primary);
            background-color: rgba(139, 92, 246, 0.2);
        }
        
        /* Admin user styling */
        .online-user-item.admin {
            background: var(--admin-bg) !important;
            border: 1px solid var(--admin-border) !important;
        }
        
        .online-user-item.admin .online-user-name {
            color: var(--admin-color) !important;
            font-weight: 800 !important;
        }
        
        .online-user-item.admin .online-indicator {
            background-color: var(--admin-color) !important;
        }
        
        .admin-badge {
            background-color: var(--admin-color) !important;
            color: white !important;
            padding: 2px 4px; /* Reduced padding */
            border-radius: 3px; /* Reduced border radius */
            font-size: 0.5rem; /* Reduced font size */
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 4px; /* Reduced margin */
        }
        
        /* Beast Level Profile Page - Reduced Size */
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 1.2rem; /* Reduced gap */
        }
        
        .profile-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
            backdrop-filter: blur(20px);
            border-radius: 16px; /* Reduced border radius */
            padding: 1.5rem; /* Reduced padding */
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
            height: 3px; /* Reduced height */
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            animation: gradientShift 3s ease infinite;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 1.5rem; /* Reduced gap */
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar-container {
            position: relative;
        }
        
        .profile-avatar {
            width: 100px; /* Reduced size */
            height: 100px; /* Reduced size */
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-blue); /* Reduced border width */
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-status {
            position: absolute;
            bottom: 6px; /* Adjusted for smaller avatar */
            right: 6px; /* Adjusted for smaller avatar */
            width: 20px; /* Reduced size */
            height: 20px; /* Reduced size */
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
            font-size: 2rem; /* Reduced font size */
            font-weight: 900;
            margin-bottom: 0.4rem; /* Reduced margin */
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
        }
        
        .profile-username {
            font-size: 1rem; /* Reduced font size */
            color: var(--text-secondary);
            margin-bottom: 0.8rem; /* Reduced margin */
        }
        
        body[data-theme="dark"] .profile-username {
            color: var(--dark-text-secondary);
        }
        
        .profile-badges {
            display: flex;
            gap: 0.5rem; /* Reduced gap */
            flex-wrap: wrap;
        }
        
        .profile-badge {
            padding: 0.3rem 0.6rem; /* Reduced padding */
            border-radius: 16px; /* Reduced border radius */
            font-size: 0.7rem; /* Reduced font size */
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.3rem; /* Reduced gap */
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
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
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
            margin-bottom: 1.2rem; /* Reduced margin */
            position: relative;
            z-index: 1;
        }
        
        .profile-stats-title {
            font-size: 1.2rem; /* Reduced font size */
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem; /* Reduced gap */
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
            gap: 0.8rem; /* Reduced gap */
            position: relative;
            z-index: 1;
        }
        
        .user-stat-item {
            display: flex;
            align-items: center;
            padding: 1rem; /* Reduced padding */
            background: var(--card-bg);
            border-radius: 10px; /* Reduced border radius */
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
            width: 3px; /* Reduced width */
            border-radius: 3px 0 0 3px;
        }
        
        .user-stat-item.charged::before { background: var(--stat-charged); }
        .user-stat-item.approved::before { background: var(--stat-approved); }
        .user-stat-item.declined::before { background: var(--stat-declined); }
        .user-stat-item.threeds::before { background: var(--stat-threeds); }
        .user-stat-item.checked::before { background: var(--stat-checked); }
        
        .user-stat-item:hover {
            transform: translateX(3px); /* Reduced transform */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .user-stat-icon {
            width: 40px; /* Reduced size */
            height: 40px; /* Reduced size */
            border-radius: 8px; /* Reduced border radius */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem; /* Reduced font size */
            color: white;
            margin-right: 1rem; /* Reduced margin */
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
            font-size: 0.8rem; /* Reduced font size */
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        body[data-theme="dark"] .user-stat-label {
            color: var(--dark-text-secondary);
        }
        
        .user-stat-value {
            font-size: 1.4rem; /* Reduced font size */
            font-weight: 900;
            line-height: 1;
        }
        
        .user-stat-item.charged .user-stat-value { color: var(--success-green); }
        .user-stat-item.approved .user-stat-value { color: var(--success-green); }
        .user-stat-item.declined .user-stat-value { color: var(--declined-red); }
        .user-stat-item.threeds .user-stat-value { color: var(--accent-cyan); }
        .user-stat-item.checked .user-stat-value { color: var(--accent-purple); }
        
        /* Mobile Online Users Section - Reduced Size */
        .mobile-online-users {
            margin-top: 1.5rem; /* Reduced margin */
            background: var(--card-bg);
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
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
            margin-bottom: 1rem; /* Reduced margin */
        }
        
        .mobile-online-users-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .mobile-online-users-title {
            color: var(--dark-text-primary);
        }
        
        .mobile-online-users-title i {
            color: var(--accent-cyan);
        }
        
        .mobile-online-users-count {
            font-size: 0.75rem; /* Reduced font size */
            color: var(--text-secondary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.3rem 0.6rem; /* Reduced padding */
            border-radius: 16px; /* Reduced border radius */
            display: flex;
            align-items: center;
            gap: 0.2rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .mobile-online-users-count {
            color: var(--dark-text-secondary);
            background: rgba(59, 130, 246, 0.2);
        }
        
        .mobile-online-users-count i {
            color: var(--success-green);
            font-size: 0.5rem; /* Reduced font size */
        }
        
        .mobile-online-users-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem; /* Reduced gap */
        }
        
        .mobile-online-user-item {
            display: flex;
            align-items: center;
            gap: 0.5rem; /* Reduced gap */
            padding: 0.5rem; /* Reduced padding */
            background: var(--secondary-bg);
            border-radius: 8px; /* Reduced border radius */
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }
        
        body[data-theme="dark"] .mobile-online-user-item {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .mobile-online-user-avatar {
            width: 24px; /* Reduced size */
            height: 24px; /* Reduced size */
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-blue);
        }
        
        .mobile-online-user-name {
            font-weight: 700;
            font-size: 0.7rem; /* Reduced font size */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
            max-width: 70px; /* Reduced width */
        }
        
        body[data-theme="dark"] .mobile-online-user-name {
            color: var(--dark-text-primary);
        }
        
        /* Other sections styling - Reduced Size */
        .checker-section, .generator-section {
            background: var(--card-bg); 
            border: 1px solid var(--border-color);
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            margin-bottom: 1.2rem; /* Reduced margin */
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
            margin-bottom: 1rem; /* Reduced margin */
            flex-wrap: wrap; 
            gap: 0.6rem; /* Reduced gap */
            position: relative;
            z-index: 1;
        }
        
        .checker-title, .generator-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .checker-title, 
        body[data-theme="dark"] .generator-title {
            color: var(--dark-text-primary);
        }
        
        .checker-title i, .generator-title i { 
            color: var(--accent-cyan); 
            font-size: 1rem; /* Reduced font size */
        }
        
        .settings-btn {
            padding: 0.4rem 0.8rem; /* Reduced padding */
            border-radius: 8px; /* Reduced border radius */
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            cursor: pointer; 
            font-weight: 600; 
            display: flex;
            align-items: center; 
            gap: 0.3rem; /* Reduced gap */
            font-size: 0.8rem; /* Reduced font size */
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
            margin-bottom: 1rem; /* Reduced margin */
        }
        
        .input-header {
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            margin-bottom: 0.6rem; /* Reduced margin */
            flex-wrap: wrap; 
            gap: 0.6rem; /* Reduced gap */
        }
        
        .input-label { 
            font-weight: 700; 
            font-size: 0.85rem; /* Reduced font size */
        }
        
        body[data-theme="dark"] .input-label {
            color: var(--dark-text-primary);
        }
        
        .card-textarea {
            width: 100%; 
            min-height: 120px; /* Reduced height */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); 
            border-radius: 10px; /* Reduced border radius */
            padding: 0.8rem; /* Reduced padding */
            color: var(--text-primary);
            font-family: 'Courier New', monospace; 
            resize: vertical;
            font-size: 0.85rem; /* Reduced font size */
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
            margin-bottom: 1rem; /* Reduced margin */
        }
        
        .form-control {
            width: 100%; 
            padding: 0.6rem 0.8rem; /* Reduced padding */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color); 
            border-radius: 10px; /* Reduced border radius */
            color: var(--text-primary); 
            font-size: 0.85rem; /* Reduced font size */
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
            gap: 0.8rem; /* Reduced gap */
            flex-wrap: wrap;
        }
        
        .form-col {
            flex: 1; 
            min-width: 100px; /* Reduced min-width */
        }
        
        .action-buttons { 
            display: flex; 
            gap: 0.6rem; /* Reduced gap */
            flex-wrap: wrap; 
            justify-content: center; 
        }
        
        .btn {
            padding: 0.5rem 1rem; /* Reduced padding */
            border-radius: 8px; /* Reduced border radius */
            border: none;
            font-weight: 700; 
            cursor: pointer; 
            display: flex;
            align-items: center; 
            gap: 0.4rem; /* Reduced gap */
            min-width: 100px; /* Reduced min-width */
            font-size: 0.85rem; /* Reduced font size */
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
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
            margin-bottom: 1.2rem; /* Reduced margin */
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
            margin-bottom: 1rem; /* Reduced margin */
            flex-wrap: wrap; 
            gap: 0.6rem; /* Reduced gap */
            position: relative;
            z-index: 1;
        }
        
        .results-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .results-title {
            color: var(--dark-text-primary);
        }
        
        .results-title i { 
            color: var(--accent-green); 
            font-size: 1rem; /* Reduced font size */
        }
        
        .results-filters { 
            display: flex; 
            gap: 0.4rem; /* Reduced gap */
            flex-wrap: wrap; 
        }
        
        .filter-btn {
            padding: 0.3rem 0.6rem; /* Reduced padding */
            border-radius: 6px; /* Reduced border radius */
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-secondary);
            cursor: pointer; 
            font-size: 0.7rem; /* Reduced font size */
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
            padding: 1.5rem 0.8rem; /* Reduced padding */
            color: var(--text-secondary);
        }
        
        body[data-theme="dark"] .empty-state {
            color: var(--dark-text-secondary);
        }
        
        .empty-state i { 
            font-size: 2rem; /* Reduced font size */
            margin-bottom: 0.6rem; /* Reduced margin */
            opacity: 0.3; 
        }
        
        .empty-state h3 { 
            font-size: 0.9rem; /* Reduced font size */
            margin-bottom: 0.3rem; /* Reduced margin */
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
            border-radius: 14px; /* Reduced border radius */
            padding: 1.2rem; /* Reduced padding */
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
            margin-bottom: 1rem; /* Reduced margin */
            padding-bottom: 0.6rem; /* Reduced padding */
            border-bottom: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .settings-header {
            border-color: var(--dark-border-color);
        }
        
        .settings-title {
            font-size: 1.1rem; /* Reduced font size */
            font-weight: 800;
            display: flex; 
            align-items: center; 
            gap: 0.5rem; /* Reduced gap */
        }
        
        body[data-theme="dark"] .settings-title {
            color: var(--dark-text-primary);
        }
        
        .settings-close {
            width: 28px; /* Reduced size */
            height: 28px; /* Reduced size */
            border-radius: 6px; /* Reduced border radius */
            border: none;
            background: var(--secondary-bg); 
            color: var(--text-secondary);
            cursor: pointer; 
            display: flex; 
            align-items: center;
            justify-content: center; 
            font-size: 0.9rem; /* Reduced font size */
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
            flex-direction: column; /* Changed to column for vertical layout */
            gap: 1.5rem; /* Increased gap for better spacing */
            margin-bottom: 1.5rem; /* Increased margin */
        }
        
        .gateway-provider {
            flex: 1;
            min-width: 200px; /* Increased min-width */
            padding: 1.5rem; /* Increased padding for bigger appearance */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px; /* Increased border radius */
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-md); /* Added shadow for depth */
        }
        
        body[data-theme="dark"] .gateway-provider {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .gateway-provider:hover {
            border-color: var(--accent-blue);
            transform: translateY(-3px); /* Increased transform for better hover effect */
            box-shadow: var(--shadow-lg);
        }
        
        .gateway-provider.active {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--accent-blue);
        }
        
        .gateway-provider-icon {
            font-size: 2.5rem; /* Increased font size for bigger appearance */
            margin-bottom: 1rem; /* Increased margin */
            color: var(--accent-blue);
        }
        
        .gateway-provider-name {
            font-weight: 700;
            font-size: 1.3rem; /* Increased font size for bigger appearance */
            color: var(--text-primary);
        }
        
        body[data-theme="dark"] .gateway-provider-name {
            color: var(--dark-text-primary);
        }
        
        /* Gateway Options - Improved Structure */
        .gateway-options {
            display: none;
            flex-direction: column;
            gap: 1.5rem; /* Increased gap between gateways for proper spacing */
        }
        
        .gateway-options.active {
            display: flex;
        }
        
        .gateway-option {
            display: flex; 
            align-items: center; 
            padding: 1.5rem; /* Increased padding for better spacing */
            background: var(--secondary-bg); 
            border: 1px solid var(--border-color);
            border-radius: 12px; /* Increased border radius */
            cursor: pointer; 
            transition: all 0.3s;
            position: relative;
            margin-bottom: 0; /* Removed margin since we're using gap */
        }
        
        body[data-theme="dark"] .gateway-option {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
        }
        
        .gateway-option:hover {
            border-color: var(--accent-blue); 
            transform: translateX(5px); /* Increased transform */
            box-shadow: var(--shadow-md);
        }
        
        .gateway-option input[type="radio"] {
            width: 18px; /* Increased size */
            height: 18px; /* Increased size */
            margin-right: 1rem; /* Increased margin */
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
            gap: 0.5rem; /* Increased gap */
            margin-bottom: 0.5rem; /* Increased margin */
            font-size: 1rem; /* Increased font size */
        }
        
        body[data-theme="dark"] .gateway-option-name {
            color: var(--dark-text-primary);
        }
        
        .gateway-option-desc { 
            font-size: 0.8rem; /* Increased font size */
            color: var(--text-secondary); 
        }
        
        body[data-theme="dark"] .gateway-option-desc {
            color: var(--dark-text-secondary);
        }
        
        .gateway-badge {
            padding: 0.3rem 0.5rem; /* Increased padding */
            border-radius: 6px; /* Increased border radius */
            font-size: 0.7rem; /* Increased font size */
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
            padding: 0.3rem 0.5rem; /* Increased padding */
            border-radius: 6px; /* Increased border radius */
            font-size: 0.7rem; /* Increased font size */
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 0.5rem; /* Increased margin */
        }
        
        .settings-footer {
            display: flex; 
            gap: 0.8rem; /* Increased gap */
            margin-top: 1.2rem; /* Increased margin */
            padding-top: 0.8rem; /* Increased padding */
            border-top: 1px solid var(--border-color);
        }
        
        body[data-theme="dark"] .settings-footer {
            border-color: var(--dark-border-color);
        }
        
        .btn-save {
            flex: 1; 
            padding: 0.6rem; /* Increased padding */
            border-radius: 8px; /* Reduced border radius */
            border: none;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
            color: white; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem; /* Increased font size */
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-save:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-back {
            flex: 1; 
            padding: 0.6rem; /* Increased padding */
            border-radius: 8px; /* Reduced border radius */
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem; /* Increased font size */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem; /* Increased gap */
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
            padding: 0.6rem; /* Increased padding */
            border-radius: 8px; /* Reduced border radius */
            border: 1px solid var(--border-color);
            background: var(--secondary-bg); 
            color: var(--text-primary);
            font-weight: 700; 
            cursor: pointer; 
            font-size: 0.9rem; /* Increased font size */
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
            width: 24px; /* Reduced size */
            height: 24px; /* Reduced size */
            animation: spin 1s linear infinite;
            margin: 12px auto; /* Reduced margin */
            display: none;
        }
        
        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
        
        #statusLog, #genStatusLog { 
            margin-top: 0.6rem; /* Reduced margin */
            color: var(--text-secondary); 
            text-align: center; 
            font-size: 0.8rem; /* Reduced font size */
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
            font-size: 0.7rem; /* Reduced font size */
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
            margin-bottom: 1.2rem; /* Reduced margin */
        }
        
        .sidebar-link.logout:hover {
            background: rgba(239, 68, 68, 0.25);
            color: var(--error);
            transform: translateX(5px);
        }
        
        .generated-cards-container {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px; /* Reduced border radius */
            padding: 0.8rem; /* Reduced padding */
            max-height: 240px; /* Reduced height */
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem; /* Reduced font size */
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text-primary);
            margin-bottom: 1rem; /* Reduced margin */
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
            padding: 0.6rem 0.8rem; /* Reduced padding */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px; /* Reduced border radius */
            color: var(--text-primary);
            font-size: 0.85rem; /* Reduced font size */
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
            right: 0.8rem; /* Reduced padding */
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
            padding: 0.6rem 0.8rem; /* Reduced padding */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px 0 0 10px; /* Reduced border radius */
            color: var(--text-primary);
            font-size: 0.85rem; /* Reduced font size */
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
            padding: 0 0.8rem; /* Reduced padding */
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 10px 10px 0; /* Reduced border radius */
            color: var(--text-secondary);
            font-size: 0.85rem; /* Reduced font size */
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
            padding: 0.4rem 0.8rem; /* Increased padding */
            border-radius: 8px; /* Increased border radius */
            font-size: 0.75rem; /* Increased font size */
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.4rem; /* Increased gap */
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
            gap: 0.8rem; /* Increased gap */
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
            body { font-size: 13px; } /* Reduced base font size */
            
            .navbar { 
                padding: 0.4rem 0.6rem; /* Reduced padding */
                height: 46px; /* Reduced height */
            }
            
            .navbar-brand { 
                font-size: 1rem; /* Reduced font size */
                margin-left: 0.6rem; /* Reduced margin */
            }
            
            .navbar-brand i { font-size: 1rem; } /* Reduced font size */
            
            .user-avatar { width: 24px; height: 24px; } /* Reduced size */
            
            .user-name { 
                max-width: 60px; /* Reduced width */
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .user-username {
                max-width: 60px; /* Reduced width */
                font-size: 0.6rem; /* Reduced font size */
            }
            
            .sidebar { 
                width: 240px; /* Reduced width */
                top: 46px; /* Adjusted for reduced navbar height */
            }
            
            .page-title { font-size: 1.3rem; } /* Reduced font size */
            
            .page-subtitle { font-size: 0.8rem; } /* Reduced font size */
            
            .main-content {
                margin-top: 46px; /* Adjusted for reduced navbar height */
                padding: 0.8rem; /* Reduced padding */
                margin-right: 0; /* Hide right sidebar on mobile */
            }
            
            /* Mobile stats grid */
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.8rem; /* Reduced gap */
            }
            
            .stat-card { 
                padding: 1rem; /* Reduced padding */
                min-height: 90px; /* Reduced height */
            }
            
            .stat-icon { 
                width: 28px; /* Reduced size */
                height: 28px; /* Reduced size */
                font-size: 0.9rem; /* Reduced font size */
            }
            
            .stat-value { 
                font-size: 1.6rem; /* Reduced font size */
            }
            
            .stat-label { 
                font-size: 0.6rem; /* Reduced font size */
            }
            
            .welcome-banner { 
                padding: 1rem; /* Reduced padding */
            }
            
            .welcome-icon { 
                width: 36px; /* Reduced size */
                height: 36px; /* Reduced size */
                font-size: 1rem; /* Reduced font size */
            }
            
            .welcome-text h2 { 
                font-size: 1.1rem; /* Reduced font size */
            }
            
            .welcome-text p { 
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .checker-section, .generator-section { 
                padding: 1rem; /* Reduced padding */
            }
            
            .checker-title, .generator-title { 
                font-size: 1rem; /* Reduced font size */
            }
            
            .checker-title i, .generator-title i { 
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .settings-btn { 
                padding: 0.3rem 0.5rem; /* Reduced padding */
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .input-label { 
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .card-textarea { 
                min-height: 100px; /* Reduced height */
                padding: 0.6rem; /* Reduced padding */
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .btn { 
                padding: 0.4rem 0.8rem; /* Reduced padding */
                min-width: 80px; /* Reduced min-width */
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .results-section { 
                padding: 1rem; /* Reduced padding */
            }
            
            .results-title { 
                font-size: 1rem; /* Reduced font size */
            }
            
            .results-title i { 
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .filter-btn { 
                padding: 0.2rem 0.5rem; /* Reduced padding */
                font-size: 0.6rem; /* Reduced font size */
            }
            
            .generated-cards-container { 
                max-height: 180px; /* Reduced height */
                font-size: 0.7rem; /* Reduced font size */
                padding: 0.6rem; /* Reduced padding */
            }
            
            .copy-all-btn, .clear-all-btn { 
                padding: 0.3rem 0.6rem; /* Reduced padding */
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .form-row { 
                flex-direction: column; 
                gap: 0.6rem; /* Reduced gap */
            }
            
            .form-col { 
                min-width: 100%; 
            }
            
            .settings-content { 
                max-width: 95vw; 
                padding: 1rem; /* Reduced padding */
            }
            
            .gateway-option { 
                padding: 0.8rem; /* Increased padding for mobile */
            }
            
            .gateway-option-name { 
                font-size: 0.85rem; /* Slightly reduced font size */
            }
            
            .gateway-option-desc { 
                font-size: 0.7rem; /* Slightly reduced font size */
            }
            
            .menu-toggle {
                position: absolute;
                left: 0.6rem; /* Reduced margin */
                top: 50%;
                transform: translateY(-50%);
                width: 32px; /* Reduced size */
                height: 32px; /* Reduced size */
            }
            
            .navbar-brand {
                margin-left: 2rem; /* Reduced margin */
            }
            
            .theme-toggle {
                width: 36px; /* Reduced size */
                height: 18px; /* Reduced size */
            }
            
            .theme-toggle-slider {
                width: 14px; /* Reduced size */
                height: 14px; /* Reduced size */
                left: 2px;
            }
            
            [data-theme="dark"] .theme-toggle-slider { 
                transform: translateX(16px); /* Adjusted for smaller toggle */
            }
            
            .user-info {
                padding: 0.2rem 0.3rem; /* Reduced padding */
                gap: 0.3rem; /* Reduced gap */
            }
            
            /* Hide right sidebar on mobile */
            .right-sidebar {
                display: none;
            }
            
            /* Profile page mobile adjustments */
            .profile-header {
                padding: 1rem; /* Reduced padding */
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                width: 80px; /* Reduced size */
                height: 80px; /* Reduced size */
            }
            
            .profile-name {
                font-size: 1.6rem; /* Reduced font size */
            }
            
            .profile-username {
                font-size: 0.9rem; /* Reduced font size */
            }
            
            .user-stats-column {
                gap: 0.6rem; /* Reduced gap */
            }
            
            .user-stat-item {
                padding: 0.8rem; /* Reduced padding */
            }
            
            .user-stat-icon {
                width: 36px; /* Reduced size */
                height: 36px; /* Reduced size */
                font-size: 1rem; /* Reduced font size */
            }
            
            .user-stat-value {
                font-size: 1.2rem; /* Reduced font size */
            }
            
            /* Mobile Online Users Section */
            .mobile-online-users {
                display: block !important;
            }
            
            .mobile-online-users-header {
                margin-bottom: 0.8rem; /* Reduced margin */
            }
            
            .mobile-online-users-title {
                font-size: 1rem; /* Reduced font size */
            }
            
            .mobile-online-users-count {
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .mobile-online-users-list {
                gap: 0.5rem; /* Reduced gap */
            }
            
            .mobile-online-user-item {
                padding: 0.4rem; /* Reduced padding */
            }
            
            .mobile-online-user-avatar {
                width: 20px; /* Reduced size */
                height: 20px; /* Reduced size */
            }
            
            .mobile-online-user-name {
                font-size: 0.65rem; /* Reduced font size */
                max-width: 60px; /* Reduced width */
            }
        }
        
        /* For very small screens */
        @media (max-width: 480px) {
            body { font-size: 12px; } /* Further reduced base font size */
            
            .navbar { 
                padding: 0.3rem 0.5rem; /* Further reduced padding */
                height: 42px; /* Further reduced height */
            }
            
            .navbar-brand { 
                font-size: 0.9rem; /* Further reduced font size */
            }
            
            .user-avatar { 
                width: 20px; /* Further reduced size */
                height: 20px; /* Further reduced size */
            }
            
            .user-name { 
                max-width: 50px; /* Further reduced width */
                font-size: 0.65rem; /* Further reduced font size */
            }
            
            .user-username {
                max-width: 50px; /* Further reduced width */
                font-size: 0.55rem; /* Further reduced font size */
            }
            
            .menu-toggle { 
                width: 28px; /* Further reduced size */
                height: 28px; /* Further reduced size */
                font-size: 0.9rem; /* Further reduced font size */
            }
            
            .sidebar { 
                width: 220px; /* Further reduced width */
                top: 42px; /* Adjusted for further reduced navbar height */
            }
            
            .main-content {
                margin-top: 42px; /* Adjusted for further reduced navbar height */
                padding: 0.6rem; /* Further reduced padding */
            }
            
            .page-title { 
                font-size: 1.1rem; /* Further reduced font size */
            }
            
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.6rem; /* Further reduced gap */
            }
            
            .stat-card { 
                padding: 0.8rem; /* Further reduced padding */
                min-height: 80px; /* Further reduced height */
            }
            
            .stat-value { 
                font-size: 1.4rem; /* Further reduced font size */
            }
            
            .stat-label { 
                font-size: 0.55rem; /* Further reduced font size */
            }
            
            .btn { 
                padding: 0.3rem 0.6rem; /* Further reduced padding */
                min-width: 70px; /* Further reduced min-width */
                font-size: 0.7rem; /* Further reduced font size */
            }
            
            /* Profile page for very small screens */
            .profile-header {
                padding: 0.8rem; /* Further reduced padding */
            }
            
            .profile-avatar {
                width: 70px; /* Further reduced size */
                height: 70px; /* Further reduced size */
            }
            
            .profile-name {
                font-size: 1.4rem; /* Further reduced font size */
            }
            
            .profile-username {
                font-size: 0.8rem; /* Further reduced font size */
            }
            
            .user-stats-column {
                gap: 0.5rem; /* Further reduced gap */
            }
            
            .user-stat-item {
                padding: 0.6rem; /* Further reduced padding */
            }
            
            .user-stat-icon {
                width: 32px; /* Further reduced size */
                height: 32px; /* Further reduced size */
                font-size: 0.9rem; /* Further reduced font size */
            }
            
            .user-stat-value {
                font-size: 1rem; /* Further reduced font size */
            }
        }
        
        /* New Gateway Selection Modal - Simplified */
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
        }
        
        .gateway-modal.active {
            display: flex;
        }
        
        .gateway-modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 2rem;
            max-width: 650px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-beast);
            animation: slideUp 0.3s ease;
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
        
        .gateway-group {
            margin-bottom: 1.5rem;
        }
        
        .gateway-group-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
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
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        body[data-theme="dark"] .gateway-option {
            background: var(--dark-accent-bg);
            border-color: var(--dark-border-color);
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

                <div class="dashboard-content">
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
                                        <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1-1-.45-1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1-1-.45-1-1z"/>
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
                </div>
            </div>
        </section>

        <section class="page-section" id="page-checking">
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑬𝑬𝑬𝑬</h1>
            <p class="page-subtitle">𝐂𝐡𝐞𝐜𝐤 𝐲𝐨𝐮𝐫 𝐜𝐚𝐫𝐝𝐬 𝐨𝐧 𝐦𝐮𝐥𝐭𝐢𝐥𝐥</p>

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
            <h1 class="page-title">𝑪𝑨𝑹𝑫 ✘ 𝑮𝑬𝑵𝑬𝑬𝑬𝑬</h1>
            <p class="page-subtitle">𝐆𝐞𝐧𝐫𝐚 𝐯𝐚𝐥𝐥𝐰𝐢𝐥𝐥𝐰𝐢𝐧𝐡</p>

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

    <!-- Simplified Gateway Selection Modal -->
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
            
            menuToggle.addEventListener('click', function() {
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
        });
        
        // Gateway settings functions
        function initializeGatewaySettings() {
            const gatewayBtnBack = document.getElementById('gatewayBtnBack');
            const gatewayBtnSave = document.getElementById('gatewayBtnSave');
            const gatewayBtnCancel = document.getElementById('gatewayBtnCancel');
            
            // Add click events to buttons
            gatewayBtnBack.addEventListener('click', function() {
                // No back functionality needed since we're showing all gateways at once
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
            
            // Update UI
            Swal.fire({
                icon: 'success',
                title: 'Gateway Updated!',
                text: `Now using: ${gatewayName}`,
                confirmButtonColor: '#10b981'
            });
            
            closeGatewayModal();
        }
        
        function openGatewayModal() {
            document.getElementById('gatewayModal').classList.add('active');
            loadSavedGatewaySettings();
        }
        
        function closeGatewayModal() {
            document.getElementById('gatewayModal').classList.remove('active');
        }
        
        // Function to get saved gateway settings
        function getSavedGatewaySettings() {
            const gateway = localStorage.getItem('selectedGateway');
            
            if (gateway) {
                return { gateway };
            }
            
            return null;
        }
    </script>
</body>
</html>
