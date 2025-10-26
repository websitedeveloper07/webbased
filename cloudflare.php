<?php
// cloudflare.php

// Cloudflare Turnstile Secret Key
define('CLOUDFLARE_TURNSTILE_SECRET', '0x4AAAAAAB8uqeP5W9TVr6Xx_4QvDs3LVvc');

// Function to verify Turnstile token
function verifyTurnstileToken($token) {
    $secret = CLOUDFLARE_TURNSTILE_SECRET;
    if (empty($secret)) {
        error_log('Turnstile secret key is not set');
        return false;
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $token
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log('Failed to contact Turnstile verification service');
        return false;
    }

    $result = json_decode($response, true);
    if (!isset($result['success'])) {
        error_log('Invalid response from Turnstile verification service');
        return false;
    }

    return $result['success'];
}
?>
