<?php
// maintenance_check.php
session_start();

// Maintenance flag file path
define('MAINTENANCE_FLAG', '/tmp/.maintenance');

// Check if maintenance mode is enabled
if (file_exists(MAINTENANCE_FLAG)) {
    // Allow access if admin has bypass (authenticated from maintenance page)
    if (isset($_SESSION['maintenance_bypass']) && $_SESSION['maintenance_bypass'] === true) {
        // Admin is authenticated, allow access to all pages
        return;
    }
    
    // Get current page name
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Define pages that are always accessible during maintenance
    $allowed_pages = [
        'adminaccess_panel.php',
        'maintenance.php',
        'login.php',  // Add login.php to allowed pages for direct access
        'logout.php'  // Also allow logout
    ];
    
    // If not on an allowed page, redirect to maintenance page
    if (!in_array($current_page, $allowed_pages)) {
        header("Location: /maintenance.php");
        exit();
    }
}
?>
