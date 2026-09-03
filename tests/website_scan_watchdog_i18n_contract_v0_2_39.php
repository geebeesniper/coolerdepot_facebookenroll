<?php
$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Services/WebsiteScanJob.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$view=(string)file_get_contents($root.'/app/Views/admin/settings.php');
$checks=[
    'version marker'=>trim((string)file_get_contents($root.'/VERSION'))==='0.2.39',
    'single page scan step'=>strpos($service,'private const STEP_SIZE = 1;')!==false,
    'status exposes stale seconds'=>strpos($service,'TIMESTAMPDIFF(SECOND,updated_at,NOW())) AS stale_seconds')!==false,
    'public state stale seconds'=>strpos($service,"'stale_seconds'=>\$staleSeconds")!==false,
    'client tracks scan xhr'=>strpos($js,'const stepRequests={};')!==false,
    'watchdog grace'=>strpos($js,'const watchdogGraceUntil={};')!==false,
    'watchdog interval'=>strpos($js,'},12000);')!==false,
    'watchdog stale threshold'=>strpos($js,'stale>=55')!==false,
    'watchdog exposes continue'=>strpos($js,'Website scan paused because no progress was recorded for ')!==false,
    'watchdog abort token'=>strpos($js,"abort('scan-watchdog')")!==false,
    'watchdog abort is quiet'=>strpos($js,"if(textStatus==='scan-watchdog'){return;}")!==false,
    'generic app translation hook'=>strpos($js,"$('[data-app-i18n]')")!==false,
    'generic count translation hook'=>strpos($js,"$('[data-app-i18n-count]')")!==false,
    'verification panel translated heading'=>strpos($view,'data-app-i18n="postVerificationLocks"')!==false,
    'verification panel translated help'=>strpos($view,'data-app-i18n="verificationLocksHelp"')!==false,
    'verification panel translated empty'=>strpos($view,'data-app-i18n="verificationLocksEmpty"')!==false,
    'verification panel translated unlock'=>strpos($view,'data-app-i18n="unlock"')!==false,
    'old bilingual empty removed'=>strpos($view,'No Sales verification is currently locked. / 当前没有 Sales Verify 被锁定。')===false,
    'old bilingual warning removed'=>strpos($view,'/ Unlock 会解除再次验证的门锁')===false,
    'four language lock dictionaries'=>substr_count($js,'verificationLocksEmpty:')===4,
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'V0.2.39 scan/i18n contract failed: '.implode(', ',$failed)."\n");return 1;}
printf("V0.2.39 scan watchdog + i18n contract: %d checks passed.\n",count($checks));
return 0;
