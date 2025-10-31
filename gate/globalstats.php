<?php
// Set headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

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
    } else {
        // Check if username column exists in users table
        $columnExists = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'username'
        ")->fetchColumn();
        
        // Add username column if it doesn't exist
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255)");
        }
        
        // Check if photo_url column exists in users table
        $photoColumnExists = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'photo_url'
        ")->fetchColumn();
        
        // Add photo_url column if it doesn't exist
        if (!$photoColumnExists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(255)");
        }
    }
    
    // Function to record card check result
    function recordCardCheck($pdo, $cardNumber, $status, $response = '') {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get or create user
        $userId = null;
        if (isset($_SESSION['user']['telegram_id'])) {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$_SESSION['user']['telegram_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['id'];
            } else {
                // Create new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (telegram_id, name, username, photo_url) 
                    VALUES (?, ?, ?, ?)
                    RETURNING id
                ");
                $stmt->execute([
                    $_SESSION['user']['telegram_id'],
                    $_SESSION['user']['name'] ?? 'Unknown User',
                    $_SESSION['user']['username'] ?? '',
                    $_SESSION['user']['photo_url'] ?? ''
                ]);
                $userId = $pdo->lastInsertId();
            }
        }
        
        // Insert card check result
        $stmt = $pdo->prepare("
            INSERT INTO card_checks (user_id, card_number, status, response) 
            VALUES (?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userId,
            $cardNumber,
            $status,
            $response
        ]);
        
        // Log the result for debugging
        if ($result) {
            error_log("Card check recorded successfully: $cardNumber - $status");
        } else {
            error_log("Failed to record card check: " . print_r($stmt->errorInfo(), true));
        }
        
        return $result;
    }
    
} catch (PDOException $e) {
    error_log("Database PDO Error in stats.php: " . $e->getMessage());
    // Don't output errors in API responses
} catch (Exception $e) {
    error_log("General Error in stats.php: " . $e->getMessage());
    // Don't output errors in API responses
}
?>
