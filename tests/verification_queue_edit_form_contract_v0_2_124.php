<?php
/** V0.2.124 Verification Queue editable re-verify form contract. */
$root=dirname(__DIR__);
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$failed=[];
$check=function($ok,$msg)use(&$failed){if(!$ok)$failed[]=$msg;};
$check(strpos($js,"queueEditUrlHelp:'Edit the original listing URL below.")!==false,'English edit help is missing');
$check(strpos($js,"queueEditUrlHelp:'请在下面修改原始帖子链接。")!==false,'Simplified Chinese edit help is missing');
$check(strpos($js,"item.submitted_url||item.canonical_url")!==false,'Editor does not load submitted URL directly from queue item');
$check(strpos($js,"addClass('sales-vq-edit-field')")!==false,'Full-width editor field wrapper is missing');
$check(strpos($js,"data-vq-edit-platform")!==false&&strpos($js,"data-vq-edit-post-id")!==false,'Derived platform/Post ID preview is missing');
$check(strpos($js,"vqRefreshEditPreview")!==false,'Edit preview refresh is missing');
$check(strpos($js,'$row.addClass(\'is-editing\')')!==false,'Row editing state is missing');
$check(strpos($js,'.fail(function(){$button.prop(\'disabled\',false);})')!==false,'Save button is not restored after a failed reverify request');
$check(strpos($css,'.sales-vq-row.is-editing>.sales-vq-actions{display:none;}')!==false,'Old action strip is not hidden during edit mode');
$check(strpos($css,'.sales-vq-edit input[data-vq-edit-url]')!==false&&strpos($css,'width:100%;')!==false,'Visible full-width URL input CSS is missing');
$check(strpos($css,'.sales-vq-edit-actions')!==false,'Dedicated edit action row is missing');
if($failed){fwrite(STDERR,"V0.2.124 verification queue edit form contract failed: ".implode('; ',$failed)."\n");exit(1);}
printf("V0.2.124 verification queue edit form contract passed.\n");
