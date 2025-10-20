<?php
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Hardcoded database connection information
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

// Database connection
try {
    // Parse the database URL
    $dbUrl = parse_url($databaseUrl);
    
    // Debug: Log parsed URL components
    error_log("Parsed URL: " . print_r($dbUrl, true));
    
    // Check if URL was parsed correctly
    if (!$dbUrl) {
        throw new Exception("Failed to parse database URL");
    }
    
    // Extract components with defaults
    $host = $dbUrl['host'] ?? null;
    $port = $dbUrl['port'] ?? 5432; // Default PostgreSQL port
    $user = $dbUrl['user'] ?? null;
    $pass = $dbUrl['pass'] ?? null;
    $path = $dbUrl['path'] ?? null;
    
    // Validate required components
    if (!$host || !$user || !$pass || !$path) {
        throw new Exception("Missing required database connection parameters. Host: " . 
                          ($host ? 'set' : 'missing') . 
                          ", User: " . ($user ? 'set' : 'missing') . 
                          ", Password: " . ($pass ? 'set' : 'missing') . 
                          ", Path: " . ($path ? 'set' : 'missing'));
    }
    
    // Remove leading slash from path to get database name
    $dbName = ltrim($path, '/');
    
    // Debug: Log connection parameters
    error_log("Connecting to: host=$host, port=$port, dbname=$dbName, user=$user");
    
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create online_users table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS online_users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            photo_url VARCHAR(255),
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(telegram_id)
        );
    ");
    
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
    
    // Clean up users not active in the last 10 seconds
    $cleanupStmt = $pdo->prepare("
        DELETE FROM online_users
        WHERE last_activity < NOW() - INTERVAL '10 seconds'
    ");
    $cleanupStmt->execute();
    
    // Get all online users (active in the last 10 seconds)
    $usersStmt = $pdo->prepare("
        SELECT telegram_id, name, photo_url, last_activity
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 seconds'
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
        
        if ($interval->s < 10) {
            $timeAgo = 'just now';
        } elseif ($interval->i > 0) {
            $timeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            $timeAgo = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
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
        WHERE telegram_id != ? AND last_activity >= NOW() - INTERVAL '10 seconds'
    ");
    $countStmt->execute([$telegramId]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'users' => $formattedUsers,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s'),
        'interval' => '10 seconds'
    ]);
    
} catch (Exception $e) {
    error_log("Database error in update_activity.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
