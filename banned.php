<?php
// banned.php
session_start();

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

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // === CHECK TELEGRAM SESSION ===
    if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $userId = $_SESSION['user']['id'] ?? null;

    if ($userId) {
        $stmt = $pdo->prepare("SELECT 1 FROM banned_users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are banned from this service']);
            exit;
        }
    }

} catch (Exception $e) {
    error_log("Banned check DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
