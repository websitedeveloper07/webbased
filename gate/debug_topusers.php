<?php
// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Database connection
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

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
    
    // Debug: Check if we have any CHARGED cards at all
    $chargedCount = $pdo->query("SELECT COUNT(*) as count FROM card_checks WHERE status = 'CHARGED'")->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Check if we have any users at all
    $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Get all card checks with their status
    $statusCounts = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM card_checks 
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Get top users with their hit counts
    $topUsers = $pdo->query("
        SELECT 
            u.id, u.telegram_id, u.name, u.username, u.photo_url, COUNT(c.id) as total_hits
        FROM users u
        JOIN card_checks c ON u.id = c.user_id
        WHERE c.status = 'CHARGED'
        GROUP BY u.id, u.telegram_id, u.name, u.username, u.username, u.photo_url
        ORDER BY total_hits DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'debug' => [
            'charged_count' => (int)$chargedCount['count'],
            'user_count' => (int)$userCount['count'],
            'status_counts' => $statusCounts,
            'top_users' => $topUsers
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
