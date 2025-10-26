<?php
// ban_user.php
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

    // === CHECK ADMIN PRIVILEGES ===
    // Replace this with your actual admin check logic
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }

    // === BAN USER ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['telegram_id'])) {
        $telegramId = $_POST['telegram_id'];

        // Validate Telegram ID (basic check, adjust as needed)
        if (empty($telegramId) || !is_string($telegramId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Telegram ID']);
            exit;
        }

        // Check if user is already banned
        $stmt = $pdo->prepare("SELECT 1 FROM banned_users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is already banned']);
            exit;
        }

        // Ban the user
        $stmt = $pdo->prepare("INSERT INTO banned_users (telegram_id) VALUES (?)");
        $stmt->execute([$telegramId]);

        echo json_encode(['success' => true, 'message' => 'User banned successfully']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

} catch (Exception $e) {
    error_log("Ban user DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
?>
