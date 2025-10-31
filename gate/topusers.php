<?php
// Set headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

// === STATIC API KEY ===
 $STATIC_API_KEY = 'A8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTuA8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTu';

// === VALIDATE API KEY ===
 $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKeyHeader) || $apiKeyHeader !== $STATIC_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
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
            PDO::ATTR_TIMEOUT => 15,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // === GET TOP USERS ===
    // Query to get top users with most successful cards (CHARGED, APPROVED, LIVE)
    $stmt = $pdo->query("
        SELECT u.id, u.telegram_id, u.name, u.username, u.photo_url, COUNT(c.id) as total_hits
        FROM users u
        JOIN card_checks c ON u.id = c.user_id
        WHERE c.status IN ('CHARGED', 'APPROVED', 'LIVE')
        GROUP BY u.id, u.telegram_id, u.name, u.username, u.photo_url
        ORDER BY total_hits DESC
        LIMIT 10
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to fetch top users");
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // === FORMAT RESPONSE ===
    $formatted = [];
    foreach ($users as $u) {
        // Get the photo URL or fetch from Telegram if not available
        $photoUrl = getTelegramProfilePicture($u['telegram_id'], $u['photo_url'], $u['name']);
        
        // Get username from database or fetch from Telegram
        $username = getTelegramUsername($pdo, $u['telegram_id'], $u['username']);
        
        $formatted[] = [
            'id' => $u['id'],
            'telegram_id' => $u['telegram_id'],
            'name' => $u['name'],
            'username' => $username,
            'photo_url' => $photoUrl,
            'total_hits' => (int)$u['total_hits']
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($formatted),
        'users' => $formatted
    ]);

} catch (PDOException $e) {
    error_log("Database PDO Error in topusers.php: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
} catch (Exception $e) {
    error_log("General Error in topusers.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
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
    
    return 'https://ui-avatars.com/api/?name=' . urlencode($firstChar) . '&background=8b5cf6&color=fff&size=64';
}
?>
