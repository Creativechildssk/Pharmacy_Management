<?php

declare(strict_types=1);

use Pharmacy\Database\Connection;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$configFile = $root . '/config/app.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing. Copy config/app.example.php to config/app.php.');
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

$timeout = (int)($config['app']['session_timeout_minutes'] ?? 30) * 60;
if (!empty($_SESSION['user']['last_activity']) && time() - (int)$_SESSION['user']['last_activity'] > $timeout) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
if (!empty($_SESSION['user'])) {
    $_SESSION['user']['last_activity'] = time();
}

$pdo = Connection::fromConfig($config['database']);
