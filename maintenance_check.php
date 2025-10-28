<?php
// maintenance_check.php
session_start();

// Maintenance flag file path
define('MAINTENANCE_FLAG', '/tmp/.maintenance');

// Check if maintenance mode is enabled
if (file_exists(MAINTENANCE_FLAG)) {
    // Allow access to admin panel even during maintenance
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // If not on admin panel or maintenance page, redirect to maintenance page
    if ($current_page !== 'adminaccess_panel.php' && $current_page !== 'maintenance.php') {
        header("Location: /maintenance.php");
        exit();
    }
}
?>
