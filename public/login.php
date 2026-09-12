<?php
require __DIR__.'/../config/bootstrap.php';

use Pharmacy\Auth\AuthService;
use Pharmacy\Support\Csrf;

$auth=new AuthService($pdo);
if ($auth->user()) {header('Location: /dashboard.php');exit;}
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    Csrf::validate((string)($_POST['csrf']??''));
    if ($auth->attempt((string)($_POST['username']??''),(string)($_POST['password']??''),$_SERVER['REMOTE_ADDR']??null)) {
      header('Location: /dashboard.php');exit;
    }
    $error='Invalid username or password.';
  } catch (Throwable $e) {$error='Unable to sign in. Please try again.';}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pharmacy Login</title><link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/app.css"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-5 col-lg-4"><div class="card shadow-sm border-0"><div class="card-body p-4"><h3 class="mb-1">Estate Pharmacy</h3><p class="text-muted mb-4">WFd DeepTech Labs Private Limited</p><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(Csrf::token())?>"><div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" autocomplete="username" required></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-dark w-100">Sign in</button></form></div></div></div></div></div></body></html>
