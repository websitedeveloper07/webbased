<?php
header('Access-Control-Allow-Origin: https://cxchk.site');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // Handle CORS preflight
}

$apiKey = 'a3lhIHJlIGxhd2RlIHlhaGkgb2thYXQgaGFpIGt5YSB0ZXJpIGtpIGR1c3JvIGthIGFwaSB1c2Uga3JuYSAxIGJhYXAga2EgaGFpIHRvIGtodWRkYSBibmEgaWRociBtdCB1c2Uga3Lwn5iC';
$url = 'https://cxchk.site/gate/stripe1$.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

header('Content-Type: application/json');
http_response_code($httpCode);
echo $response;
curl_close($ch);
?>
