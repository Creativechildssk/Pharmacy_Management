<?php
require __DIR__.'/../config/bootstrap.php';
header('Location: '.(empty($_SESSION['user'])?'/login.php':'/dashboard.php'));
