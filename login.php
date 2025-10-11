<?php
session_start();

// === CONFIGURATION ===
define('BOT_TOKEN', getenv('8421537809:AAEfYzNtCmDviAMZXzxYt6juHbzaZGzZb6A') ?: 'YOUR_BOT_TOKEN_HERE');
define('BOT_USERNAME', 'CardXchk_LOGBOT'); // e.g. mybotname_bot

// === TELEGRAM AUTH VALIDATION ===
function checkTelegramAuthorization($auth_data) {
    if (!isset($auth_data['hash'])) {
        throw new Exception('Missing Telegram hash');
    }

    $check_hash = $auth_data['hash'];
    unset($auth_data['hash']);

    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }

    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);
    $secret_key = hash('sha256', BOT_TOKEN, true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);

    if (!hash_equals($hash, $check_hash)) {
        throw new Exception('Invalid Telegram authentication data (hash mismatch)');
    }

    if ((time() - $auth_data['auth_date']) > 86400) {
        throw new Exception('Telegram login data is too old');
    }

    return $auth_data;
}

// === MAIN LOGIC ===
try {
    if (!empty($_GET['id']) && isset($_GET['hash'])) {
        // Verify Telegram redirect data
        $auth_data = checkTelegramAuthorization($_GET);

        // Save in session
        $_SESSION['auth_provider'] = 'telegram';
        $_SESSION['telegram_user'] = [
            'id' => $auth_data['id'],
            'first_name' => $auth_data['first_name'] ?? '',
            'last_name' => $auth_data['last_name'] ?? '',
            'username' => $auth_data['username'] ?? '',
            'photo_url' => $auth_data['photo_url'] ?? ''
        ];

        // Redirect to homepage
        header('Location: index.php');
        exit;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// === FRONTEND (LOGIN WIDGET) ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login via Telegram</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Inter', sans-serif;
    display: flex; align-items: center; justify-content: center;
    height: 100vh; background: #f4f6fb; margin: 0;
}
.container {
    background: #fff; border-radius: 16px; padding: 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;
}
h1 { margin-bottom: 20px; color: #222; }
.error { color: #d33; margin-bottom: 15px; font-weight: 500; }
</style>
</head>
<body>
<div class="container">
    <h1>Login with Telegram</h1>
    <?php if (!empty($error)): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <script async src="https://telegram.org/js/telegram-widget.js?22"
        data-telegram-login="<?= CardXchk_LOGBOT ?>"
        data-size="large"
        data-radius="8"
        data-auth-url="login.php"
        data-request-access="write">
    </script>
</div>
</body>
</html>
