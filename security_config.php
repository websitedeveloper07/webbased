<?php
// security_config.php - Full-featured secure session management

// Prevent direct file access
if (!defined('SECURITY_CONFIG_LOADED') && !defined('ALLOWED_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access denied');
}

// Strict security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour session lifetime
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Start secure session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
} elseif (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Check for session hijacking attempt
if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== $_SERVER['REMOTE_ADDR']) {
    // IP changed - possible session hijacking
    session_unset();
    session_destroy();
    header('Location: /login.php?error=session_expired');
    exit;
}

// Store current IP for next check
 $_SESSION['last_ip'] = $_SERVER['REMOTE_ADDR'];

// Update last activity time
 $_SESSION['last_activity'] = time();

// Check for session timeout (30 minutes of inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: /login.php?error=session_timeout');
    exit;
}

// Generate security tokens if they don't exist
if (!isset($_SESSION['security_token'])) {
    $_SESSION['security_token'] = bin2hex(random_bytes(32));
    $_SESSION['token_generated'] = time();
    $_SESSION['token_expires'] = time() + 3600; // 1 hour expiration
}

// Generate API key if not exists
if (!isset($_SESSION['api_key'])) {
    $_SESSION['api_key'] = 'ak_' . bin2hex(random_bytes(16));
}

// Generate signature key if not exists
if (!isset($_SESSION['signature_key'])) {
    $_SESSION['signature_key'] = bin2hex(random_bytes(16));
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to refresh tokens
function refreshSecurityTokens() {
    $_SESSION['security_token'] = bin2hex(random_bytes(32));
    $_SESSION['token_generated'] = time();
    $_SESSION['token_expires'] = time() + 3600;
    $_SESSION['api_key'] = 'ak_' . bin2hex(random_bytes(16));
    $_SESSION['signature_key'] = bin2hex(random_bytes(16));
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    return [
        'securityToken' => $_SESSION['security_token'],
        'apiKey' => $_SESSION['api_key'],
        'signatureKey' => $_SESSION['signature_key'],
        'csrfToken' => $_SESSION['csrf_token']
    ];
}

// Function to validate API request
function validateApiRequest() {
    // Check if security token exists in session
    if (!isset($_SESSION['security_token']) || !isset($_SESSION['signature_key'])) {
        sendSecurityError('Invalid session', 403);
    }
    
    // Validate security token
    if (!isset($_POST['security_token']) || $_POST['security_token'] !== $_SESSION['security_token']) {
        sendSecurityError('Invalid security token', 403);
    }
    
    // Validate domain
    $posted_domain = isset($_POST['domain']) ? strtolower($_POST['domain']) : '';
    $server_host = strtolower($_SERVER['HTTP_HOST']);
    $posted_domain = preg_replace('/^www\./', '', $posted_domain);
    $server_host = preg_replace('/^www\./', '', $server_host);
    
    if ($posted_domain !== $server_host) {
        sendSecurityError('Domain validation failed', 403);
    }
    
    // Validate session ID
    if (!isset($_POST['session_id']) || $_POST['session_id'] !== session_id()) {
        sendSecurityError('Invalid session ID', 403);
    }
    
    // Validate timestamp (request must be within 5 minutes)
    $timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;
    if (abs(time() - $timestamp) > 300) {
        sendSecurityError('Request expired', 403);
    }
    
    // Validate API key
    if (!isset($_POST['api_key']) || $_POST['api_key'] !== $_SESSION['api_key']) {
        sendSecurityError('Invalid API key', 403);
    }
    
    // Validate signature
    if (!isset($_POST['signature'])) {
        sendSecurityError('Missing signature', 403);
    }
    
    // Recreate the signature from the request data
    $requestData = [
        'card_number' => $_POST['card']['number'] ?? '',
        'exp_month' => $_POST['card']['exp_month'] ?? '',
        'exp_year' => $_POST['card']['exp_year'] ?? '',
        'cvc' => $_POST['card']['cvc'] ?? '',
        'security_token' => $_POST['security_token'],
        'domain' => $_POST['domain'],
        'session_id' => $_POST['session_id'],
        'timestamp' => $timestamp,
        'api_key' => $_POST['api_key']
    ];
    
    // Sort parameters alphabetically
    ksort($requestData);
    $sortedParams = [];
    foreach ($requestData as $key => $value) {
        $sortedParams[] = $key . '=' . $value;
    }
    $stringToSign = implode('&', $sortedParams) . $_SESSION['signature_key'];
    
    // Create the same hash as the client
    $hash = 0;
    for ($i = 0; $i < strlen($stringToSign); $i++) {
        $char = ord($stringToSign[$i]);
        $hash = (($hash << 5) - $hash) + $char;
        $hash = $hash & $hash; // Convert to 32-bit integer
    }
    $expectedSignature = abs($hash) . '';
    
    if ($_POST['signature'] !== $expectedSignature) {
        sendSecurityError('Invalid signature', 403);
    }
    
    // Check rate limit
    $rate_limit_key = 'api_rate_limit_' . session_id();
    $rate_limit_time = 60; // 60 seconds
    $rate_limit_max = 30; // 30 requests per minute
    
    // Initialize rate limit if not exists
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [
            'count' => 0,
            'reset_time' => time() + $rate_limit_time
        ];
    }
    
    // Check if rate limit reset time has passed
    if (time() > $_SESSION[$rate_limit_key]['reset_time']) {
        $_SESSION[$rate_limit_key] = [
            'count' => 1,
            'reset_time' => time() + $rate_limit_time
        ];
    } else {
        // Increment count
        $_SESSION[$rate_limit_key]['count']++;
        
        // Check if rate limit exceeded
        if ($_SESSION[$rate_limit_key]['count'] > $rate_limit_max) {
            sendSecurityError('Rate limit exceeded. Please try again later.', 429);
        }
    }
    
    // If we get here, the request is valid
    return true;
}

// Function to validate CSRF token
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        sendSecurityError('Invalid CSRF token', 403);
    }
    return true;
}

// Function to send security error response
function sendSecurityError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    
    // Log security events
    error_log("Security Error: $message | IP: " . $_SERVER['REMOTE_ADDR'] . 
              " | Session: " . session_id() . 
              " | User-Agent: " . $_SERVER['HTTP_USER_AGENT']);
    
    echo json_encode(['error' => $message]);
    exit;
}

// Function to get security parameters for JavaScript
function getSecurityParams() {
    return [
        'securityToken' => $_SESSION['security_token'],
        'apiKey' => $_SESSION['api_key'],
        'signatureKey' => $_SESSION['signature_key'],
        'csrfToken' => $_SESSION['csrf_token'],
        'siteDomain' => $_SERVER['HTTP_HOST'],
        'sessionId' => session_id()
    ];
}

// Function to set security headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none';");
    
    // HSTS (if using HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// Function to check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['user']) && 
           isset($_SESSION['user']['id']) && 
           isset($_SESSION['user']['auth_provider']) &&
           $_SESSION['user']['auth_provider'] === 'telegram';
}

// Function to require authentication
function requireAuthentication() {
    if (!isAuthenticated()) {
        sendSecurityError('Unauthorized access', 401);
    }
}

// Function to log security events
function logSecurityEvent($event, $details = '') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'session_id' => session_id(),
        'user_id' => $_SESSION['user']['id'] ?? 'guest'
    ];
    
    // Log to file (make sure logs directory exists and is writable)
    file_put_contents(__DIR__ . '/logs/security.log', json_encode($logEntry) . PHP_EOL, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("Security Event: $event | Details: $details");
}

// Function to clean expired sessions
function cleanExpiredSessions() {
    $sessionPath = session_save_path();
    if (is_dir($sessionPath)) {
        foreach (glob("$sessionPath/sess_*") as $file) {
            if (filemtime($file) + 3600 < time()) {
                unlink($file);
            }
        }
    }
}

// Clean expired sessions on 1% of requests
if (rand(1, 100) === 1) {
    cleanExpiredSessions();
}

// Initialize security headers
setSecurityHeaders();

// Log session start
if (!isset($_SESSION['session_started'])) {
    logSecurityEvent('session_start', 'New session initiated');
    $_SESSION['session_started'] = true;
}

// Check for suspicious activity
if (isset($_SESSION['request_count'])) {
    $_SESSION['request_count']++;
} else {
    $_SESSION['request_count'] = 1;
}

// If too many requests in a short time, log it
if ($_SESSION['request_count'] > 100 && $_SESSION['request_count'] % 50 === 0) {
    logSecurityEvent('high_request_count', "Session has made {$_SESSION['request_count']} requests");
}

// Define constant to prevent direct access
define('SECURITY_CONFIG_LOADED', true);
?>
