<?php
/**
 * v0.2.33 same-platform + website duplicate-source contract.
 * v0.2.33 同平台 + 官网重复来源契约检查。
 */
$root=dirname(__DIR__);
$checks=[];
$need=function(string $file,string $needle,string $label)use(&$checks,$root):void{
    $text=file_get_contents($root.'/'.$file);
    $checks[]=[str_contains((string)$text,$needle),$label];
};
$checks[]=[trim((string)file_get_contents($root.'/VERSION'))==='0.2.33','VERSION is 0.2.33'];
$need('app/Models/Post.php','WHERE platform=? AND canonical_url_hash=?','URL duplicate lookup is platform scoped');
$need('app/Models/Post.php','WHERE platform=? AND BINARY title=BINARY ?','title duplicate lookup is platform scoped');
$need('app/Services/DuplicateIndex.php','WHERE LOWER(p.platform)=? AND p.deleted_at IS NULL AND f.sha256=?','image duplicate lookup is platform scoped');
$need('app/Services/DuplicateIndex.php',"'platform'=>'website'",'website duplicates remain a separate global source');
$need('app/Controllers/ApiController.php','duplicateMatchFromResult','API exposes DuplicateIndex match URLs');
$need('public/assets/app.js',".text('Duplicate: '+parsed.href)",'UI prints the exact duplicate URL');
$need('public/assets/app.js','markSalesDuplicateMessageCompact','duplicate notice uses compact UI');
$need('app/Views/sales/_submit_form.php','id="salesDuplicateSource"','duplicate URL is visible outside hidden result body');
$need('public/assets/app.css','.sales-submit-message.duplicate-compact','compact duplicate message CSS exists');
$failed=array_filter($checks,static fn($c)=>!$c[0]);
foreach($checks as [$ok,$label]){echo ($ok?'PASS':'FAIL')." - {$label}\n";}
if($failed){exit(1);} echo "ALL PASS\n";
