<?php
// gate_init.php - Place this in your /gate/ folder
if (!defined('ALLOWED_ACCESS')) {
    // Block direct access
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access denied');
}
?>
