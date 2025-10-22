<?php
$secret_key = 'a3lhIHJlIGxhd2RlIHlhaGkgb2thYXQgaGFpIGt5YSB0ZXJpIGtpIGR1c3JvIGthIGFwaSB1c2Uga3JuYSAxIGJhYXAga2EgaGFpIHRvIGtodWRkYSBibmEgaWRociBtdCB1c2Uga3Lwn5iC';
$header_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($header_key !== $secret_key) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    error_log("Unauthorized API access attempt: " . $header_key . " from " . $_SERVER['REMOTE_ADDR']);
    exit(json_encode(['error' => 'Unauthorized']));
}
?>
