<?php
// /gate/security.php

$secret_key = 'a3lhIHJlIGxhd2RlIHlhaGkgb2thYXQgaGFpIGt5YSB0ZXJpIGtpIGR1c3JvIGthIGFwaSB1c2Uga3JuYSAxIGJhYXAga2EgaGFpIHRvIGtodWRrYSBibmEgaWRociBtdCB1c2Uga3Lwn5iC'; // your secret key
$header_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($header_key !== $secret_key) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}
?>
