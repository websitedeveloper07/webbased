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
    
    // Get total approved/live cards (both are the same)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM card_checks WHERE status = 'APPROVED'");
    $totalApproved = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Return success response with all data
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => $totalUsers,
            'totalChecked' => $totalChecked,
            'totalCharged' => $totalCharged,
            'totalApproved' => $totalApproved
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database PDO Error in stats.php: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
} catch (Exception $e) {
    error_log("General Error in stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
