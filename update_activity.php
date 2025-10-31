<?php

// update_activity.php
// Session-based authentication + return users data

// Set timeout and memory limits
set_time_limit(30); // 30 seconds max execution time
ini_set('memory_limit', '128M'); // 128MB memory limit

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === SESSION-BASED AUTHENTICATION ===
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
    
    // Set connection timeout with extended options and SSL mode
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15, // Increased timeout
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
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
        // Get the photo URL or fetch from Telegram if not available
        $avatar = $u['photo_url'] ?: getTelegramProfilePicture($u['telegram_id'], null, $u['name']);
        
        // Get username from database or fetch from Telegram
        $username = getTelegramUsername($pdo, $u['telegram_id'], $u['username']);
        
        $formatted[] = [
            'name' => $u['name'],
            'username' => $username,
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
    echo json_encode(['success' => false, 'message' => 'Database connection error', 'debug' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("General Error in update_activity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug' => $e->getMessage()]);
}

// Function to get Telegram username
function getTelegramUsername($pdo, $telegramId, $dbUsername = null) {
    // If we already have a username in the database, use it
    if (!empty($dbUsername)) {
        return '@' . trim($dbUsername, '@');
    }
    
    // Try to get the username from Telegram API
    $botToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';
    
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getChat?chat_id={$telegramId}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        
        if (isset($data['ok']) && $data['ok'] === true && isset($data['result']['username'])) {
            $username = $data['result']['username'];
            
            // Update the database with the username
            try {
                $updateStmt = $pdo->prepare("UPDATE users SET username = ? WHERE telegram_id = ?");
                $updateStmt->execute([$username, $telegramId]);
                
                // Also update online_users table
                $updateOnlineStmt = $pdo->prepare("UPDATE online_users SET username = ? WHERE telegram_id = ?");
                $updateOnlineStmt->execute([$username, $telegramId]);
            } catch (PDOException $e) {
                error_log("Failed to update username in database: " . $e->getMessage());
            }
            
            return '@' . $username;
        }
    }
    
    // If no username found, return null
    return null;
}

// Function to get Telegram profile picture
function getTelegramProfilePicture($telegramId, $photoUrl = null, $name = 'User') {
    // If we already have a photo URL, use it
    if (!empty($photoUrl)) {
        return $photoUrl;
    }
    
    // Try to get the profile picture from Telegram API
    $botToken = '8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A';
    
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getUserProfilePhotos?user_id={$telegramId}&limit=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        
        if (isset($data['ok']) && $data['ok'] === true && 
            isset($data['result']['photos']) && 
            !empty($data['result']['photos'])) {
            
            $photo = $data['result']['photos'][0][0]; // Get the highest resolution photo
            $fileId = $photo['file_id'];
            
            // Get file path
            $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fileUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $fileResponse = curl_exec($ch);
            $fileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($fileHttpCode === 200 && !empty($fileResponse)) {
                $fileData = json_decode($fileResponse, true);
                
                if (isset($fileData['ok']) && $fileData['ok'] === true && 
                    isset($fileData['result']['file_path'])) {
                    
                    $filePath = $fileData['result']['file_path'];
                    return "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
                }
            }
        }
    }
    
    // If all else fails, generate an avatar
    return generateAvatar($name);
}

// Helper function to generate avatar URL
function generateAvatar($name) {
    // Handle non-Latin characters by using the first character of the name
    $firstChar = mb_substr(trim($name), 0, 1);
    
    // If the first character is not a letter, use 'U' as default
    if (!preg_match('/\p{L}/u', $firstChar)) {
        $firstChar = 'U';
    }
    
    return 'https://ui-avatars.com/api/?name=' . urlencode($firstChar) . '&background=3b82f6&color=fff&size=64';
}
?>
