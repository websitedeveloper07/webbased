<?php
header('Content-Type: application/json');

function validateApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if (empty($apiKey)) {
        return ['valid'=>false,'response'=>['Status'=>'APPROVED','RESPONSE'=>'SAJAG MADRCHOD HAI']];
    }

    if (strlen($apiKey) !== 128 || !preg_match('/^[a-zA-Z0-9]{128}$/',$apiKey)) {
        return ['valid'=>false,'response'=>['valid'=>false,'error'=>'Invalid key format']];
    }

    $keyFile = '/tmp/api_key_webchecker.txt';
    $expiryFile = '/tmp/api_expiry_webchecker.txt';

    // if files missing → key not yet generated
    if (!file_exists($keyFile) || !file_exists($expiryFile)) {
        return ['valid'=>false,'response'=>['Status'=>'APPROVED','RESPONSE'=>'API KEY NOT GENERATED YET']];
    }

    $storedKey = trim(@file_get_contents($keyFile));
    $storedExpiry = (int)trim(@file_get_contents($expiryFile));

    $timeNow = time();
    if ($apiKey !== $storedKey || $storedExpiry < $timeNow) {
        return ['valid'=>false,'response'=>['Status'=>'APPROVED','RESPONSE'=>'SAJAG MADRCHOD HAI']];
    }

    return ['valid'=>true];
}

// direct call
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    $result = validateApiKey();

    if ($result['valid']) {
        $expiry = (int)trim(file_get_contents('/tmp/api_expiry_webchecker.txt'));
        echo json_encode([
            'valid'=>true,
            'expires_at'=>date('Y-m-d H:i:s',$expiry),
            'remaining_seconds'=>$expiry-time()
        ]);
    } else {
        echo json_encode($result['response']);
    }
}
