<?php
// update_activity.php
// Proper API key validation + custom response (no 401 page)

require_once __DIR__ . '/gate/validkey.php';

// === VALIDATE API KEY ===
$validation = validateApiKey();

if (!$validation['valid']) {
    header('Content-Type: application/json');
    echo json_encode($validation['response']);
    exit;
}

// === KEY IS VALID → CONTINUE ===
session_start();

// Check Telegram session
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // === TABLE SETUP ===
    $tableExists = $pdo->query("SELECT to_regclass('public.online_users')")->fetchColumn();

    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE online_users (
                id SERIAL PRIMARY KEY,
                session_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                photo_url VARCHAR(255),
                telegram_id BIGINT,
                username VARCHAR(255),
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(session_id)
            );
        ");
    } else {
        $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'online_users'")->fetchAll(PDO::FETCH_COLUMN);
        $cols = array_flip($columns);

        if (!isset($cols['telegram_id'])) {
            $pdo->exec("ALTER TABLE online_users ADD COLUMN telegram_id BIGINT");
        }
        if (!isset($cols['username'])) {
            $pdo->exec("ALTER TABLE online_users ADD COLUMN username VARCHAR(255)");
        }
    }

    // === USER DATA ===
    $sessionId = session_id();
    $name = $_SESSION['user']['name'] ?? 'Unknown User';
    $photoUrl = $_SESSION['user']['photo_url'] ?? null;
    $telegramId = $_SESSION['user']['id'] ?? null;
    $username = $_SESSION['user']['username'] ?? null;

    if (empty($name)) {
        throw new Exception("User name cannot be empty");
    }

    // === UPDATE ACTIVITY ===
    $pdo->prepare("
        INSERT INTO online_users (session_id, name, photo_url, telegram_id, username, last_activity)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (session_id) DO UPDATE SET
            name = EXCLUDED.name,
            photo_url = EXCLUDED.photo_url,
            telegram_id = EXCLUDED.telegram_id,
            username = EXCLUDED.username,
            last_activity = CURRENT_TIMESTAMP
    ")->execute([$sessionId, $name, $photoUrl, $telegramId, $username]);

    // === CLEANUP OLD USERS ===
    $pdo->prepare("DELETE FROM online_users WHERE last_activity < NOW() - INTERVAL '10 seconds'")->execute();

    // === GET ONLINE USERS ===
    $stmt = $pdo->query("
        SELECT session_id, name, photo_url, telegram_id, username
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 seconds'
        ORDER BY last_activity DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === FORMAT RESPONSE ===
    $formatted = [];
    foreach ($users as $u) {
        $avatar = $u['photo_url'] ?: (
            function($n) {
                $i = '';
                foreach (explode(' ', trim($n)) as $w) {
                    if ($w) $i .= strtoupper(substr($w, 0, 1));
                    if (strlen($i) >= 2) break;
                }
                return 'https://ui-avatars.com/api/?name=' . urlencode($i ?: 'U') . '&background=3b82f6&color=fff&size=64';
            }
        )($u['name']);

        $formatted[] = [
            'name' => $u['name'],
            'username' => $u['username'] ? '@' . $u['username'] : null,
            'photo_url' => $avatar,
            'is_currently_online' => ($u['session_id'] === $sessionId)
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($formatted),
        'users' => $formatted
    ]);

} catch (Exception $e) {
    error_log("DB Error in update_activity.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
