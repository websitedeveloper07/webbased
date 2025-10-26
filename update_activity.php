<?php

// update_activity.php
// Static API key validation + return users data

// Set timeout and memory limits
set_time_limit(30); // 30 seconds max execution time
ini_set('memory_limit', '128M'); // 128MB memory limit

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === STATIC API KEY ===
 $STATIC_API_KEY = 'A8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTuA8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTu'; // Replace with your static key

// === VALIDATE API KEY ===
 $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKeyHeader) || $apiKeyHeader !== $STATIC_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

// === KEY IS VALID → CONTINUE ===
if (!session_start()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Session start failed']);
    exit;
}

// Check Telegram session
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// === DATABASE CONNECTION ===
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

try {
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

    // Set connection timeout
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10, // 10 seconds connection timeout
            PDO::ATTR_PERSISTENT => false // Disable persistent connections
        ]
    );

    // === TABLE SETUP ===
    $tableExists = $pdo->query("SELECT to_regclass('public.online_users')")->fetchColumn();

    if (!$tableExists) {
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
    
    if (!$updateStmt->execute([$sessionId, $name, $photoUrl, $telegramId, $username])) {
        throw new Exception("Failed to update user activity");
    }

    // === CLEANUP OLD USERS ===
    $cleanupStmt = $pdo->prepare("DELETE FROM online_users WHERE last_activity < NOW() - INTERVAL '10 seconds'");
    if (!$cleanupStmt->execute()) {
        throw new Exception("Failed to cleanup old users");
    }

    // === GET ONLINE USERS ===
    $stmt = $pdo->query("
        SELECT session_id, name, photo_url, telegram_id, username
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 seconds'
        ORDER BY last_activity DESC
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to fetch online users");
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === FORMAT RESPONSE ===
    $formatted = [];
    foreach ($users as $u) {
        // Generate avatar URL if not available
        $avatar = $u['photo_url'] ?: generateAvatar($u['name']);
        
        $formatted[] = [
            'name' => $u['name'],
            'username' => $u['username'] ? '@' . $u['username'] : null,
            'photo_url' => $avatar,
            'is_currently_online' => ($u['session_id'] === $sessionId)
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($formatted),
        'users' => $formatted
    ]);

} catch (PDOException $e) {
    error_log("Database PDO Error in update_activity.php: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
} catch (Exception $e) {
    error_log("General Error in update_activity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
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
