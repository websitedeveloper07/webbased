<?php
// session_manager.php
class SessionManager {
    private static $instance = null;
    private $session_lifetime = 1800; // 30 minutes in seconds
    private $cookie_lifetime = 0;    // Session cookie (expires when browser closes)
    
    private function __construct() {
        // Configure session security settings
        ini_set('session.use_strict_mode', 1);          // Prevent session fixation
        ini_set('session.use_cookies', 1);               // Use cookies for session ID
        ini_set('session.use_only_cookies', 1);          // Prevent session ID in URL
        ini_set('session.cookie_httponly', 1);          // Prevent JavaScript access
        ini_set('session.cookie_secure', $this->isHttps()); // HTTPS-only in production
        ini_set('session.cookie_samesite', 'Strict');    // Prevent CSRF
        ini_set('session.gc_maxlifetime', $this->session_lifetime); // Server-side timeout
        ini_set('session.gc_probability', 1);            // Enable garbage collection
        ini_set('session.gc_divisor', 100);              // Collect expired sessions
        
        // Set custom session name
        session_name('APP_SECURE_SESSION');
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->cookie_lifetime,
            'path' => '/',
            'domain' => $this->getCookieDomain(),
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Start session
        session_start();
        
        // Initialize new session
        if (!isset($_SESSION['initiated'])) {
            $this->initializeNewSession();
        }
        
        // Validate existing session
        $this->validateSession();
        
        // Update activity timestamp
        $this->updateActivity();
    }
    
    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Initialize a new session
    private function initializeNewSession() {
        session_regenerate_id(true); // Generate new ID to prevent fixation
        $_SESSION = [
            'initiated' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => time(),
            'last_activity' => time(),
            'logged_in' => false
        ];
    }
    
    // Validate session integrity
    private function validateSession() {
        // Check if session has expired
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $this->session_lifetime)) {
            $this->destroySession();
            $this->redirectWithMessage('login.php', 'timeout', 'Your session expired. Please login again.');
        }
        
        // Validate IP address (allow for changes in some networks)
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            $this->destroySession();
            $this->redirectWithMessage('login.php', 'security', 'Security alert: IP address changed');
        }
        
        // Validate user agent
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->destroySession();
            $this->redirectWithMessage('login.php', 'security', 'Security alert: Browser changed');
        }
    }
    
    // Update last activity timestamp
    private function updateActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    // Create login session
    public function createLoginSession($userId, $userRole, $userData = []) {
        // Clear any existing session data
        session_unset();
        
        // Regenerate ID to prevent fixation
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION = [
            'initiated' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => time(),
            'last_activity' => time(),
            'logged_in' => true,
            'user_id' => $userId,
            'user_role' => $userRole,
            'user_data' => $userData
        ];
    }
    
    // Destroy session securely
    public function destroySession() {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && 
               $_SESSION['logged_in'] === true &&
               isset($_SESSION['user_id']);
    }
    
    // Require login for protected pages
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->redirectWithMessage('login.php', 'auth', 'Please login to continue');
        }
    }
    
    // Require specific role
    public function requireRole($requiredRole) {
        $this->requireLogin();
        
        if ($_SESSION['user_role'] !== $requiredRole) {
            header('HTTP/1.0 403 Forbidden');
            echo '<h1>403 Forbidden</h1><p>You don\'t have permission to access this resource.</p>';
            exit;
        }
    }
    
    // Get current user data
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'role' => $_SESSION['user_role'],
                'data' => $_SESSION['user_data'] ?? []
            ];
        }
        return null;
    }
    
    // Check if HTTPS is active
    private function isHttps() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443;
    }
    
    // Get cookie domain
    private function getCookieDomain() {
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        // Remove port number if present
        $domain = preg_replace('/:\d+$/', '', $domain);
        // Remove subdomain for wildcard cookies (optional)
        // $domain = substr_count($domain, '.') > 1 ? substr($domain, strpos($domain, '.') + 1) : $domain;
        return $domain;
    }
    
    // Redirect with message
    private function redirectWithMessage($url, $type, $message) {
        $params = [
            'msg' => urlencode($message),
            'type' => $type
        ];
        $redirectUrl = $url . '?' . http_build_query($params);
        header("Location: $redirectUrl");
        exit;
    }
    
    // Handle session timeout gracefully
    public function handleTimeout() {
        if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
            return '<div class="alert alert-warning">Your session expired. Please login again.</div>';
        }
        return '';
    }
    
    // Handle security alerts
    public function handleSecurityAlert() {
        if (isset($_GET['security']) && $_GET['security'] == 1) {
            return '<div class="alert alert-danger">Security alert detected. Please login again.</div>';
        }
        return '';
    }
    
    // Handle authentication messages
    public function handleAuthMessage() {
        if (isset($_GET['auth']) && $_GET['auth'] == 1) {
            return '<div class="alert alert-info">Please login to continue</div>';
        }
        return '';
    }
}
?>
