<?php
require __DIR__.'/../config/bootstrap.php';
$auth=new Pharmacy\Auth\AuthService($pdo);
$auth->logout();
header('Location: /login.php');
