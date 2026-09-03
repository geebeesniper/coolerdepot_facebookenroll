<?php
/** V0.2.84 contract: scan processing is attached to exactly one History run. */
$root=dirname(__DIR__);
$errors=[];
$read=static function(string $rel) use ($root,&$errors): string {
    $path=$root.'/'.$rel;
    if(!is_file($path)){$errors[]="Missing {$rel}";return '';}
    $data=file_get_contents($path);
    if($data===false){$errors[]="Unreadable {$rel}";return '';}
    return $data;
};
$must=static function(bool $ok,string $message) use (&$errors): void {if(!$ok){$errors[]=$message;}};

$version=trim($read('VERSION'));
$settings=$read('app/Views/admin/settings.php');
$js=$read('public/assets/app.js');
$css=$read('public/assets/app.css');
$controller=$read('app/Controllers/AdminSettingsController.php');
$history=$read('app/Services/WebsiteActivityHistory.php');

$must(version_compare($version,'0.2.84','>='),'VERSION must be >= 0.2.84.');
$must(!str_contains($settings,'website-product-scan-progress-wrap hidden'),'Saved Websites must not render the standalone scan progress strip.');
$must(str_contains($settings,"\$runningOpen=\$status==='running';"),'Server-rendered running History row must auto-expand.');
$must(str_contains($settings,"website-history-detail-row'.(\$runningOpen?'':' hidden')"),'Processing detail row must be attached directly under its History run.');
$must(str_contains($settings,'Processing log'),'History run must contain Processing log.');
$must(str_contains($settings,'Preparing first URL…'),'Running scan must show an in-run preparation state before the first URL finishes.');

$must(str_contains($js,'v0.2.84: scan processing belongs to its History run only.'),'V0.2.84 scan placement marker is missing.');
$must(str_contains($js,'There is intentionally no standalone "Scanning…" progress strip above History.'),'Standalone Scanning strip must be explicitly disabled.');
$must(str_contains($js,"\$detail.removeClass('hidden');"),'Running History detail must auto-open in live JS.');
$must(str_contains($js,"if(!pageUrl||kind==='run'){return;}"),'Processing log must contain URL processing records, not run lifecycle pseudo-rows.');
$must(str_contains($js,'after_item_id:Number(historyItemLast[historyId]||0)'),'Live scan loop must request only new per-run processing records.');
$must(str_contains($js,'$log.append(historyItemHtml(item));'),'Each completed URL must append to the current run log.');
$must(str_contains($js,'data-history-id="\'+historyId+\'"') || str_contains($js,'data-history-id="'+"'+historyId+'"+'"'),'History controls/logs must remain bound to one history_id.');

$must(str_contains($controller,"\$state['history_items']=WebsiteActivityHistory::scanItems"),'Scan API must return persisted History items.');
$must(str_contains($history,'INSERT INTO cdsp_website_scan_history_items'),'Per-URL processing records must be persisted in the database.');
$must(str_contains($history,'WHERE history_id=? AND id>? ORDER BY id ASC'),'Processing log retrieval must be incremental and scoped by history_id.');

$must(str_contains($css,'v0.2.84 — Scan Processing belongs only to its own Product Scan History run.'),'V0.2.84 History-only CSS marker is missing.');
$must(str_contains($css,'.website-source-manager .website-product-scan-progress-wrap'),'Old standalone progress strip must be force-hidden.');

if($errors){
    fwrite(STDERR,"V0.2.84 Website Scan run-processing contract: FAIL\n- ".implode("\n- ",$errors)."\n");
    exit(1);
}
fwrite(STDOUT,"V0.2.84 Website Scan run-processing contract passed.\n");
