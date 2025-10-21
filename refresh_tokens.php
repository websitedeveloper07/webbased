<?php
// refresh_tokens.php - Refreshes security tokens

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
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

// Verify CSRF token if provided (for form submissions)
if (isset($_POST['csrf_token']) && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF token'
    ]);
    exit;
}

// Log the token refresh event
logSecurityEvent('token_refresh', 'Security tokens refreshed');

// Refresh the tokens
 $newTokens = refreshSecurityTokens();

// Return the new tokens
echo json_encode([
    'success' => true,
    'securityToken' => $newTokens['securityToken'],
    'apiKey' => $newTokens['apiKey'],
    'signatureKey' => $newTokens['signatureKey'],
    'csrfToken' => $newTokens['csrfToken'],
    'message' => 'Tokens refreshed successfully'
]);
?>
