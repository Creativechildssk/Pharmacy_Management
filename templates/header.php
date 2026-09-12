<?php
use Pharmacy\Auth\Authorization;
$title = $title ?? 'Estate Pharmacy Management System';
$user = $_SESSION['user'] ?? null;
$estateName = 'Estate Pharmacy';
try {
    if (isset($pdo)) {
        $setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='estate_name' LIMIT 1")->fetchColumn();
        if (is_string($setting) && trim($setting) !== '') {
            $estateName = $setting;
        }
    }
} catch (Throwable) {
    // Keep safe default during initial setup.
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/vendor/datatables/datatables.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark navbar-expand-lg sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="/dashboard.php"><?= htmlspecialchars($estateName) ?></a>
    <?php if ($user): ?><span class="navbar-text text-light small"><?= htmlspecialchars($user['full_name']) ?></span><?php endif; ?>
  </div>
</nav>
<div class="d-flex">
<?php if ($user) require __DIR__.'/sidebar.php'; ?>
<main class="flex-grow-1 p-3 p-lg-4">
