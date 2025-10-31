<?php
require_once __DIR__ . '/globalstats.php';

// Database connection
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr4433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

try {
    $dbUrl = parse_url($databaseUrl);
    $host = $dbUrl['host'] ?? null;
    $port = $dbUrl['port'] ?? 5432;
    $user = $dbUrl['user'] ?? null;
    $pass = $dbUrl['pass'] ?? null;
    $path = $dbUrl['path'] ?? null;

    $dbName = ltrim($path, '/');
    
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
    
    // Start session
    session_start();
    
    // Create a test user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([123456789]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $stmt = $pdo->prepare("
            INSERT INTO users (telegram_id, name, username, photo_url) 
            VALUES (?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([123456789, 'Test User', 'testuser', 'https://example.com/avatar.jpg']);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $user['id'];
    }
    
    // Record a test CHARGED card
    $stmt = $pdo->prepare("
        INSERT INTO card_checks (user_id, card_number, status, response) 
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([$userId, '42424242424242', 'CHARGED', 'Test charged card']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Test CHARGED card recorded successfully',
            'user_id' => $userId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to record test card',
            'error' => print_r($stmt->errorInfo(), true)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
