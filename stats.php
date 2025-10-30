<?php
// Set headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

// Database connection
 $host = 'localhost';
 $dbname = 'your_database_name';
 $username = 'your_username';
 $password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

try {
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total checked cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks");
    $totalChecked = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total charged cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = 'CHARGED'");
    $totalCharged = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total live cards (approved + charged)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status IN ('APPROVED', 'CHARGED')");
    $totalLive = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get top users (by number of charged cards)
    $stmt = $pdo->query("
        SELECT u.name, u.username, u.photo_url, COUNT(c.id) as hits
        FROM users u
        JOIN card_checks c ON u.id = c.user_id
        WHERE c.status = 'CHARGED'
        GROUP BY u.id
        ORDER BY hits DESC
        LIMIT 5
    ");
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format top users data
    $formattedTopUsers = [];
    foreach ($topUsers as $user) {
        $formattedTopUsers[] = [
            'name' => $user['name'],
            'username' => $user['username'],
            'photo_url' => $user['photo_url'],
            'hits' => $user['hits']
        ];
    }
    
    // Return success response with all data
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => $totalUsers,
            'totalChecked' => $totalChecked,
            'totalCharged' => $totalCharged,
            'totalLive' => $totalLive,
            'topUsers' => $formattedTopUsers
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query failed',
        'error' => $e->getMessage()
    ]);
}
?>
