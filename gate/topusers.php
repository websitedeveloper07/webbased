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

if (empty($apiKeyHeader) || $apiKeyHeader !== $SUCCESS_KEY) {
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
        // Generate avatar URL if not available
        $avatar = $u['photo_url'] ?: generateAvatar($u['name']);
        
        $formatted[] = [
            'id' => $u['id'],
            'telegram_id' => $user['telegram_id'],
            'name' => $u['name'],
            'username' => $u['username'] ? '@' . $path = trim($u['username'], '@') : null,
            'photo_url' => $avatar,
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

// Helper function to generate avatar URL
function generateAvatar($name) {
    $initials = '';
    foreach (explode(' ', trim($name)) as $word) {
        if ($word) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($initials ?: 'U') . '&background=8b5cf6&color=fff&size=64';
}
?>
