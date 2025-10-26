<?php

// update_activity.php
// Static API key validation + return users data

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timeout and memory limits
set_time_limit(30); // 30 seconds max execution time
ini_set('memory_limit', '256M'); // Increased memory limit

// Add logging function
function logMessage($message) {
    $logFile = __DIR__ . '/api_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Script started");

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    logMessage("OPTIONS request handled");
    exit;
}

// === STATIC API KEY ===
 $STATIC_API_KEY = 'A8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTuA8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTu';

// === VALIDATE API KEY ===
 $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
logMessage("API Key validation attempt");

if (empty($apiKeyHeader)) {
    http_response_code(401);
    logMessage("Empty API key");
    echo json_encode(['success' => false, 'message' => 'Missing API key']);
    exit;
}

if ($apiKeyHeader !== $STATIC_API_KEY) {
    http_response_code(401);
    logMessage("Invalid API key");
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

logMessage("API key validated successfully");

// === KEY IS VALID → CONTINUE ===
if (!session_start()) {
    http_response_code(500);
    logMessage("Session start failed");
    echo json_encode(['success' => false, 'message' => 'Session start failed']);
    exit;
}

logMessage("Session started");

// Check Telegram session
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    http_response_code(401);
    logMessage("Unauthorized access attempt");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

logMessage("User session validated: " . json_encode($_SESSION['user']));

// === DATABASE CONNECTION ===
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

try {
    logMessage("Attempting database connection");
    
    $dbUrl = parse_url($databaseUrl);
    $host = $dbUrl['host'] ?? null;
    $port = $dbUrl['port'] ?? 5432;
    $user = $dbUrl['user'] ?? null;
    $pass = $dbUrl['pass'] ?? null;
    $path = $dbUrl['path'] ?? null;

    if (!$host || !$user || !$pass || !$path) {
        throw new Exception("Missing DB connection parameters");
    }

    $dbName = ltrim($path, '/');
    
    // Set connection timeout with extended options
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15, // Increased timeout
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    logMessage("Database connection successful");

    // === TABLE SETUP ===
    $tableExists = $pdo->query("SELECT to_regclass('public.online_users')")->fetchColumn();

    if (!$tableExists) {
        logMessage("Creating online_users table");
        $pdo->exec("
            CREATE TABLE online_users (
                id SERIAL PRIMARY KEY,
                session_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                photo_url VARCHAR(255),
                telegram_id BIGINT,
                username VARCHAR(255),
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(session_id)
            );
        ");
    }

    // === USER DATA ===
    $sessionId = session_id();
    $name = $_SESSION['user']['name'] ?? 'Unknown User';
    $photoUrl = $_SESSION['user']['photo_url'] ?? null;
    $telegramId = $_SESSION['user']['id'] ?? null;
    $username = $_SESSION['user']['username'] ?? null;
    
    logMessage("Processing user: $name, session: $sessionId");

    // === UPDATE ACTIVITY ===
    $updateStmt = $pdo->prepare("
        INSERT INTO online_users (session_id, name, photo_url, telegram_id, username, last_activity)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (session_id) DO UPDATE SET
            name = EXCLUDED.name,
            photo_url = EXCLUDED.photo_url,
            telegram_id = EXCLUDED.telegram_id,
            username = EXCLUDED.username,
            last_activity = CURRENT_TIMESTAMP
    ");
    
    $updateResult = $updateStmt->execute([$sessionId, $name, $photoUrl, $telegramId, $username]);
    logMessage("User activity update result: " . ($updateResult ? "success" : "failed"));

    // === CLEANUP OLD USERS ===
    $cleanupStmt = $pdo->prepare("DELETE FROM online_users WHERE last_activity < NOW() - INTERVAL '10 seconds'");
    $cleanupResult = $cleanupStmt->execute();
    logMessage("Cleanup result: " . ($cleanupResult ? "success" : "failed"));

    // === GET ONLINE USERS ===
    $stmt = $pdo->query("
        SELECT session_id, name, photo_url, telegram_id, username
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 seconds'
        ORDER BY last_activity DESC
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage("Fetched " . count($users) . " online users");

    // === FORMAT RESPONSE ===
    $formatted = [];
    foreach ($users as $u) {
        $avatar = $u['photo_url'] ?: generateAvatar($u['name']);
        
        $formatted[] = [
            'name' => $u['name'],
            'username' => $u['username'] ? '@' . $u['username'] : null,
            'photo_url' => $avatar,
            'is_currently_online' => ($u['session_id'] === $sessionId)
        ];
    }

    logMessage("Sending successful response with " . count($formatted) . " users");
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($formatted),
        'users' => $formatted
    ]);

} catch (PDOException $e) {
    $errorMessage = "Database PDO Error: " . $e->getMessage();
    logMessage($errorMessage);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection error', 'debug' => $errorMessage]);
} catch (Exception $e) {
    $errorMessage = "General Error: " . $e->getMessage();
    logMessage($errorMessage);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug' => $errorMessage]);
}

// Helper function to generate avatar URL
function generateAvatar($name) {
    $initials = '';
    foreach (explode(' ', trim($name)) as $word) {
        if ($word) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($initials ?: 'U') . '&background=3b82f6&color=fff&size=64';
}
?>
