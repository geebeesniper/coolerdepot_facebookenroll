<?php
/**
 * V0.2.90 contract: Website Scan runs in its own IIFE, so every HTML helper used
 * by that runtime must exist inside the same scope. This guards the exact bug
 * where clicking Scan Website threw "ReferenceError: escapeHtml is not defined"
 * before any History placeholder or scan-start request could be created.
 */
$root=dirname(__DIR__);
$js=(string)file_get_contents($root.'/public/assets/app.js');
$fail=[];
$check=static function(bool $ok,string $message) use (&$fail): void { if(!$ok){$fail[]=$message;} };
$marker="/* v0.2.34 — persistent website scan jobs + inline product grid / 持久化网站扫描与内联产品网格 */";
$start=strpos($js,$marker);
$end=$start===false?false:strpos($js,'/* v0.2.36 — Website Library 1/2/3 top-level accordion. */',$start);
$scanScope=($start!==false&&$end!==false)?substr($js,$start,$end-$start):'';
$check($scanScope!=='','scanner IIFE can be isolated');
$check(str_contains($scanScope,"function escapeHtml(value){"),'scanner IIFE owns a local escapeHtml helper');
$check(str_contains($scanScope,'function startHistoryPlaceholder(host,website,$button){'),'scanner placeholder path exists');
$check(str_contains($scanScope,"const row='<tr class=\"website-history-main-row is-expanded is-starting\""),'placeholder row is still rendered synchronously');
$check(str_contains($scanScope,"url:endpoints.start,method:'POST'"),'scan-start request remains wired');
$helperPos=strpos($scanScope,"function escapeHtml(value){");
$placeholderPos=strpos($scanScope,'function startHistoryPlaceholder(host,website,$button){');
$check($helperPos!==false&&$placeholderPos!==false&&$helperPos<$placeholderPos,'local escapeHtml is defined before Scan Website can call it');
if($fail){fwrite(STDERR,"V0.2.90 website scan runtime scope contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.90 website scan runtime scope contract passed.\n";
