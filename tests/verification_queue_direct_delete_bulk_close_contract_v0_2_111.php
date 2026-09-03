<?php
/** V0.2.111 contract: queue Delete has no confirm; successful Bulk Submit closes its modal. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fails=[];
$check=static function(bool $ok,string $label)use(&$fails):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$fails[]=$label;
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$js=(string)@file_get_contents($root.'/public/assets/app.js');

$check(version_compare($version,'0.2.111','>='),'VERSION is >= 0.2.111');
$deleteStart=strpos($js,"if(action==='delete'){");
$deleteEnd=$deleteStart===false?false:strpos($js,"if(action==='edit')",$deleteStart);
$deleteBlock=($deleteStart===false||$deleteEnd===false)?'':substr($js,$deleteStart,$deleteEnd-$deleteStart);
$check($deleteBlock!==''&&!str_contains($deleteBlock,'window.confirm')&&!str_contains($deleteBlock,'confirm('),'Verification Queue Delete does not show a confirmation dialog');
$check($deleteBlock!==''&&str_contains($deleteBlock,"vqPost('/api/verification-queue/delete'"),'Verification Queue Delete still calls the existing AJAX delete endpoint');

$bulkStart=strpos($js,"$('#bulkQueueButton').on('click'");
$bulkEnd=$bulkStart===false?false:strpos($js,"$(document).on('click','[data-verification-queue-panel] [data-vq-title-toggle]'",$bulkStart);
$bulkBlock=($bulkStart===false||$bulkEnd===false)?'':substr($js,$bulkStart,$bulkEnd-$bulkStart);
$check($bulkBlock!==''&&str_contains($bulkBlock,"/api/verification-queue/bulk"),'Bulk Submit still uses the existing AJAX queue endpoint');
$check($bulkBlock!==''&&str_contains($bulkBlock,'}).done(function(resp){')&&str_contains($bulkBlock,'closeSalesBulkSubmitModal();'),'Successful Bulk Submit closes the Bulk Submit popup');
$check($bulkBlock!==''&&str_contains($bulkBlock,'.fail(function(xhr)')&&!preg_match('/\.fail\(function\(xhr\).*?closeSalesBulkSubmitModal\(\)/s',$bulkBlock),'Failed Bulk Submit keeps the popup open for correction');
$check($bulkBlock!==''&&str_contains($bulkBlock,'vqLoadAll(false);'),'Bulk Submit refreshes Verification Queue through AJAX after success');

if($fails){fwrite(STDERR,"V0.2.111 direct-delete/bulk-close contract failed: ".implode('; ',$fails)."\n");exit(1);}
echo "V0.2.111 direct-delete/bulk-close contract passed.\n";
