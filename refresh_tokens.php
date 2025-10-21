<?php
// refresh_tokens.php - Refreshes security tokens with proper validation

// Prevent direct access
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access denied');
}

// Include security configuration
require_once 'security_config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated
if (!isAuthenticated()) {
    logSecurityEvent('unauthorized_token_refresh', 'Attempted token refresh without authentication');
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logSecurityEvent('invalid_method_token_refresh', 'Token refresh attempted with ' . $_SERVER['REQUEST_METHOD'] . ' method');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

// Verify CSRF token if provided (for form submissions)
if (isset($_POST['csrf_token']) && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    logSecurityEvent('invalid_csrf_token_refresh', 'Token refresh attempted with invalid CSRF token');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF token'
    ]);
    exit;
}

// Check if the session is still active
if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 1800)) {
    logSecurityEvent('expired_session_token_refresh', 'Token refresh attempted with expired session');
    echo json_encode([
        'success' => false,
        'error' => 'Session expired'
    ]);
    exit;
}

// Check rate limiting for token refresh
 $refresh_limit_key = 'token_refresh_limit_' . session_id();
 $refresh_limit_time = 300; // 5 minutes
 $refresh_limit_max = 3; // 3 refreshes per 5 minutes

// Initialize rate limit if not exists
if (!isset($_SESSION[$refresh_limit_key])) {
    $_SESSION[$refresh_limit_key] = [
        'count' => 0,
        'reset_time' => time() + $refresh_limit_time
    ];
}

// Check if rate limit reset time has passed
if (time() > $_SESSION[$refresh_limit_key]['reset_time']) {
    $_SESSION[$refresh_limit_key] = [
        'count' => 1,
        'reset_time' => time() + $refresh_limit_time
    ];
} else {
    // Increment count
    $_SESSION[$refresh_limit_key]['count']++;
    
    // Check if rate limit exceeded
    if ($_SESSION[$refresh_limit_key]['count'] > $refresh_limit_max) {
        logSecurityEvent('rate_limit_token_refresh', 'Token refresh rate limit exceeded');
        echo json_encode([
            'success' => false,
            'error' => 'Too many token refresh attempts. Please wait before trying again.'
        ]);
        exit;
    }
}

// Check if tokens actually need refreshing
 $needsRefresh = false;
if (time() > $_SESSION['token_expires']) {
    $needsRefresh = true;
    $reason = 'Token expired';
} else if (isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true') {
    // Allow forced refresh if explicitly requested and user is authenticated
    $needsRefresh = true;
    $reason = 'Forced refresh';
}

// If no refresh needed, return current tokens
if (!$needsRefresh) {
    echo json_encode([
        'success' => true,
        'refreshed' => false,
        'message' => 'Tokens are still valid',
        'expires_in' => $_SESSION['token_expires'] - time()
    ]);
    exit;
}

// Log the token refresh event with reason
logSecurityEvent('token_refresh', $reason);

// Refresh the tokens
 $newTokens = refreshSecurityTokens();

// Update last activity time
 $_SESSION['last_activity'] = time();

// Return the new tokens
echo json_encode([
    'success' => true,
    'refreshed' => true,
    'securityToken' => $newTokens['securityToken'],
    'apiKey' => $newTokens['apiKey'],
    'csrfToken' => $newTokens['csrfToken'],
    'expires_in' => $_SESSION['token_expires'] - time(),
    'message' => 'Tokens refreshed successfully'
]);
?>
