<?php
$configFile=__DIR__.'/../config/app.php';
if(!is_file($configFile)){fwrite(STDERR,"Missing config/app.php\n");exit(1);}
$config=require $configFile;
$db=$config['database'];
$dir=__DIR__.'/../storage/backups';
if(!is_dir($dir)&&!mkdir($dir,0770,true)){fwrite(STDERR,"Cannot create backup directory\n");exit(1);}
$file=$dir.'/pharmacy-'.date('Ymd-His').'.sql';
putenv('MYSQL_PWD='.(string)$db['password']);
$cmd=sprintf(
    'mysqldump --host=%s --port=%d --user=%s --single-transaction --routines --triggers %s > %s',
    escapeshellarg($db['host']),
    (int)$db['port'],
    escapeshellarg($db['username']),
    escapeshellarg($db['database']),
    escapeshellarg($file)
);
passthru($cmd,$code);
putenv('MYSQL_PWD');
if($code!==0){@unlink($file);fwrite(STDERR,"Backup failed\n");exit($code);}
echo "Backup created: {$file}\n";
