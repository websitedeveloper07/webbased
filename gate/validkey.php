<?php
// validkey.php
// Static API key validation for all scripts

// STATIC API KEY — must match update_activity.php
define('STATIC_API_KEY', 'aB7dF3GhJkL9MnPqRsT2UvWxYz0AbCdEfGhIjKlMnOpQrStUvWxYz1234567890aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789AbCdEfGhIjK'); // Replace with your 128-char key

/**
 * Validate API key
 * Returns array:
 * [
 *   'valid' => true|false,
 *   'response' => array // optional, used when invalid
 * ]
 */
function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // === NO KEY OR INVALID ===
    if (empty($apiKey) || $apiKey !== STATIC_API_KEY) {
        return [
            'valid' => false,
            'response' => [
                'success' => false,
                'message' => 'Invalid API key'
            ]
        ];
    }

    // === KEY IS VALID ===
    return ['valid' => true];
}

// === DIRECT CALL (optional) ===
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();
    header('Content-Type: application/json');
    if ($result['valid']) {
        echo json_encode(['success' => true, 'message' => 'API key is valid']);
    } else {
        echo json_encode($result['response']);
    }
    exit;
}
