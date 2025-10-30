<?php
// Set headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

// Set timeout and memory limits
set_time_limit(30); // 30 seconds max execution time
ini_set('memory_limit', '128M'); // 128MB memory limit

// === STATIC API KEY ===
 $STATIC_API_KEY = 'A8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTuA8xk2nX4DqYpZ0b3RjLTm5W9eG7CsVnHfQ1zPRaUy6EwSdBJl0tOMiNgKhIoFcTu'; // Replace with your static key

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
            PDO::ATTR_TIMEOUT => 15, // Increased timeout
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // === TABLE SETUP ===
    // Check if card_checks table exists
    $tableExists = $pdo->query("SELECT to_regclass('public.card_checks')")->fetchColumn();
    
    if (!$tableExists) {
        // Create card_checks table if it doesn't exist
        $pdo->exec("
            CREATE TABLE card_checks (
                id SERIAL PRIMARY KEY,
                user_id BIGINT,
                card_number VARCHAR(255),
                status VARCHAR(50),
                response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
    
    // Check if users table exists
    $usersTableExists = $pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
    
    if (!$usersTableExists) {
        // Create users table if it doesn't exist
        $pdo->exec("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                telegram_id BIGINT UNIQUE,
                name VARCHAR(255),
                username VARCHAR(255),
                photo_url VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
    
    // === GLOBAL STATISTICS CALCULATION ===
    
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total checked cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks");
    $totalChecked = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total charged cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = 'CHARGED'");
    $totalCharged = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total live cards (only approved, not charged)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = 'APPROVED'");
    $totalLive = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total 3DS cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = '3DS'");
    $total3DS = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total declined cards
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = 'DECLINED'");
    $totalDeclined = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate success rate
    $successRate = $totalChecked > 0 ? round((($totalCharged + $totalLive + $total3DS) / $totalChecked) * 100, 2) : 0;
    
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
    
    // Get today's statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as today_total,
            COUNT(CASE WHEN status = 'CHARGED' THEN 1 END) as today_charged,
            COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as today_approved,
            COUNT(CASE WHEN status = '3DS' THEN 1 END) as today_threeds,
            COUNT(CASE WHEN status = 'DECLINED' THEN 1 END) as today_declined
        FROM card_checks 
        WHERE DATE(created_at) = CURRENT_DATE
    ");
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get this week's statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as week_total,
            COUNT(CASE WHEN status = 'CHARGED' THEN 1 END) as week_charged,
            COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as week_approved,
            COUNT(CASE WHEN status = '3DS' THEN 1 END) as week_threeds,
            COUNT(CASE WHEN status = 'DECLINED' THEN 1 END) as week_declined
        FROM card_checks 
        WHERE created_at >= DATE_TRUNC('week', CURRENT_DATE)
    ");
    $weekStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get this month's statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as month_total,
            COUNT(CASE WHEN status = 'CHARGED' THEN 1 END) as month_charged,
            COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as month_approved,
            COUNT(CASE WHEN status = '3DS' THEN 1 END) as month_threeds,
            COUNT(CASE WHEN status = 'DECLINED' THEN 1 END) as month_declined
        FROM card_checks 
        WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)
    ");
    $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return success response with all data
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => $totalUsers,
            'totalChecked' => $totalChecked,
            'totalCharged' => $totalCharged,
            'totalLive' => $totalLive,
            'total3DS' => $total3DS,
            'totalDeclined' => $totalDeclined,
            'successRate' => $successRate,
            'topUsers' => $formattedTopUsers,
            'todayStats' => [
                'total' => $todayStats['today_total'],
                'charged' => $todayStats['today_charged'],
                'approved' => $todayStats['today_approved'],
                'threeds' => $todayStats['today_threeds'],
                'declined' => $todayStats['today_declined']
            ],
            'weekStats' => [
                'total' => $weekStats['week_total'],
                'charged' => $weekStats['week_charged'],
                'approved' => $weekStats['week_approved'],
                'threeds' => $weekStats['week_threeds'],
                'declined' => $weekStats['week_declined']
            ],
            'monthStats' => [
                'total' => $monthStats['month_total'],
                'charged' => $monthStats['month_charged'],
                'approved' => $monthStats['month_approved'],
                'threeds' => $monthStats['month_threeds'],
                'declined' => $monthStats['month_declined']
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database PDO Error in stats.php: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection error', 'debug' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("General Error in stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug' => $e->getMessage()]);
}
?>
