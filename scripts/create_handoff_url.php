<?php
$config=require dirname(__DIR__).'/config/bootstrap.php';use App\Services\ExternalAuthService;
if($argc<4){echo "Usage: php scripts/create_handoff_url.php <admin|sales> <external_uid> <display_name> [sales_id] [base_url]\n";exit(1);}
$role=$argv[1];$uid=$argv[2];$name=$argv[3];$sales=$role==='sales'?($argv[4]??''):'';$baseIndex=$role==='sales'?5:4;
$default=$config['app']['host']?'https://'.$config['app']['host']:'http://127.0.0.1'.$config['app']['base_path'];$base=$argv[$baseIndex]??$default;$secret=(string)$config['auth']['handoff_secret'];if(strlen($secret)<32)exit("AUTH_HANDOFF_SECRET is not configured.\n");
$p=['uid'=>$uid,'sales_id'=>$sales,'name'=>$name,'role'=>$role,'ts'=>time(),'nonce'=>bin2hex(random_bytes(16))];$p['sig']=hash_hmac('sha256',ExternalAuthService::canonicalPayload($p),$secret);echo rtrim($base,'/').'/auth/handoff?'.http_build_query($p).PHP_EOL;
