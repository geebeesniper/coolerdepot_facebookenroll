<?php
/** V0.2.95 background verification queue worker. */
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Services\VerificationQueueWorker;
$max=20;
foreach($argv??[] as $arg){if(preg_match('/^--max=(\d+)$/',$arg,$m))$max=(int)$m[1];}
$stats=VerificationQueueWorker::run($max);
printf("%s processed=%d passed=%d failed=%d duplicate=%d recovered=%d\n",date('c'),$stats['processed'],$stats['passed'],$stats['failed'],$stats['duplicate'],$stats['recovered']);
