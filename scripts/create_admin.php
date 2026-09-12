<?php
require __DIR__.'/../config/bootstrap.php';
if (PHP_SAPI!=='cli') {http_response_code(403);exit("CLI only\n");}
[$script,$username,$name,$password]=array_pad($argv,4,null);
if(!$username||!$name||!$password||strlen($password)<10){fwrite(STDERR,"Usage: php scripts/create_admin.php <username> <full-name> <password-10+-chars>\n");exit(1);}
$pdo->beginTransaction();try{$stmt=$pdo->prepare('INSERT INTO users(username,full_name,password_hash,active) VALUES(:username,:name,:hash,1)');$stmt->execute(['username'=>$username,'name'=>$name,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :uid,id FROM roles WHERE code='ADMIN'")->execute(['uid'=>$uid]);$pdo->commit();echo "Admin created: {$username}\n";}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
