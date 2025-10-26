<?php
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

    // === CREATE BANNED USERS TABLE ===
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS banned_users (
            telegram_id BIGINT PRIMARY KEY,
            reason TEXT,
            banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    echo "✅ Table 'banned_users' created successfully!";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
