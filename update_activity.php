<?php
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Load environment variables manually
 $envFile = __DIR__ . '/.env';
 $_ENV = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Database connection
try {
    if (!isset($_ENV['DATABASE_URL'])) {
        throw new Exception("DATABASE_URL not set in .env file");
    }
    
    $dbUrl = parse_url($_ENV['DATABASE_URL']);
    if (!$dbUrl || !isset($dbUrl['host'], $dbUrl['port'], $dbUrl['user'], $dbUrl['pass'], $dbUrl['path'])) {
        throw new Exception("Invalid DATABASE_URL format");
    }
    
    $pdo = new PDO(
        "pgsql:host={$dbUrl['host']};port={$dbUrl['port']};dbname=" . ltrim($dbUrl['path'], '/'),
        $dbUrl['user'],
        $dbUrl['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get current user information
    $telegramId = $_SESSION['user']['id'];
    $name = $_SESSION['user']['name'];
    $photoUrl = $_SESSION['user']['photo_url'] ?? null;
    
    // Update current user's last_activity timestamp
    $updateStmt = $pdo->prepare("
        INSERT INTO online_users (telegram_id, name, photo_url, last_activity)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (telegram_id) DO UPDATE SET
            name = EXCLUDED.name,
            photo_url = EXCLUDED.photo_url,
            last_activity = CURRENT_TIMESTAMP
    ");
    $updateStmt->execute([$telegramId, $name, $photoUrl]);
    
    // Clean up users not active in the last 3 minutes
    $cleanupStmt = $pdo->prepare("
        DELETE FROM online_users
        WHERE last_activity < NOW() - INTERVAL '3 minutes'
    ");
    $cleanupStmt->execute();
    
    // Get all online users (active in the last 3 minutes)
    $usersStmt = $pdo->prepare("
        SELECT telegram_id, name, photo_url, last_activity
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '3 minutes'
        ORDER BY last_activity DESC
    ");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format user data for response
    $formattedUsers = [];
    foreach ($users as $user) {
        // Generate avatar URL if not available
        $avatarUrl = $user['photo_url'];
        if (empty($avatarUrl)) {
            // Generate initials from name
            $initials = '';
            $words = explode(' ', trim($user['name']));
            foreach ($words as $word) {
                if (!empty($word)) {
                    $initials .= strtoupper(substr($word, 0, 1));
                    if (strlen($initials) >= 2) break;
                }
            }
            if (empty($initials)) $initials = 'U';
            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=3b82f6&color=fff&size=64';
        }
        
        // Format last activity time
        $lastActivity = new DateTime($user['last_activity']);
        $now = new DateTime();
        $interval = $now->diff($lastActivity);
        
        if ($interval->days > 0) {
            $timeAgo = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $timeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $timeAgo = 'just now';
        }
        
        $formattedUsers[] = [
            'telegram_id' => $user['telegram_id'],
            'name' => $user['name'],
            'photo_url' => $avatarUrl,
            'time_ago' => $timeAgo,
            'is_current_user' => ($user['telegram_id'] == $telegramId)
        ];
    }
    
    // Get count of online users excluding current user
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM online_users
        WHERE telegram_id != ? AND last_activity >= NOW() - INTERVAL '3 minutes'
    ");
    $countStmt->execute([$telegramId]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'users' => $formattedUsers,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    error_log("Database error in update_activity.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
