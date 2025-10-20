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
    
    // Update current user's last_activity timestamp
    $telegramId = $_SESSION['user']['id'];
    $name = $_SESSION['user']['name'];
    $photoUrl = $_SESSION['user']['photo_url'] ?? null;
    
    $updateStmt = $pdo->prepare("
        INSERT INTO online_users (telegram_id, name, photo_url, last_activity)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (telegram_id) DO UPDATE SET
            name = EXCLUDED.name,
            photo_url = EXCLUDED.photo_url,
            last_activity = CURRENT_TIMESTAMP
    ");
    $updateStmt->execute([$telegramId, $name, $photoUrl]);
    
    // Clean up users not active in the last 10 minutes
    $cleanupStmt = $pdo->prepare("
        DELETE FROM online_users
        WHERE last_activity < NOW() - INTERVAL '10 minutes'
    ");
    $cleanupStmt->execute();
    
    // Get count of online users
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 minutes'
    ");
    $countStmt->execute();
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => $count]);
    
} catch (Exception $e) {
    error_log("Database error in update_activity.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
