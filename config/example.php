<?php
// Copy to local.php. Never commit local.php or real credentials.
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=skprofund;charset=utf8mb4',
    'user' => 'root',
    'password' => '',
    // Outside Apache's document root; use an absolute Windows path in XAMPP.
    'storage' => dirname(__DIR__, 2) . '/skprofund-private',
];
