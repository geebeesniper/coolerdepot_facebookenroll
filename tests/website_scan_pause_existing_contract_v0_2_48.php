<?php
/** Static contract checks for V0.2.48 website scan behavior. */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $data = @file_get_contents($root . '/' . $rel);
    if ($data === false) {
        fwrite(STDERR, "Missing file: {$rel}\n");
        exit(1);
    }
    return $data;
};

$checks = [];
$expect = static function(bool $ok, string $name) use (&$checks): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    $checks[] = $name;
};

$job = $read('app/Services/WebsiteScanJob.php');
$history = $read('app/Services/WebsiteActivityHistory.php');
$settings = $read('app/Views/admin/settings.php');
$js = $read('public/assets/app.js');
$css = $read('public/assets/app.css');
$migration = $read('scripts/migrate_v0_2_48.php');

$expect(strpos($job, "ENUM('running','completed','paused','stopped','failed')") !== false, 'scan job enum supports paused');
$expect(strpos($job, "SET status='paused',last_error=?") !== false, 'manual pause persists paused status and message');
$expect(strpos($job, "GET_LOCK(?,35)") !== false, 'pause waits for current scan step lock');
$expect(strpos($job, "status IN ('paused','stopped','failed')") !== false, 'resume accepts paused jobs and legacy stopped jobs');
$expect(strpos($history, "'paused'") !== false, 'activity history accepts paused');
$expect(strpos($settings, 'website-scan-toggle') !== false, 'single continue/pause scan control exists');
$expect(strpos($settings, 'website-scan-stop') === false, 'separate stop scan button removed from settings card');
$expect(strpos($settings, "['completed','running','paused','stopped','failed']") !== false, 'history renderer styles paused status');
$expect(strpos($js, 'function revealExistingSource(host,inputSelector)') !== false, 'existing website card reveal helper exists');
$expect(strpos($js, 'if(inputSelector&&revealExistingSource(requestedHost,inputSelector)){return;}') !== false, 'new website field checks existing card before scan');
$expect(strpos($js, "active?'Pause Scanning':'Continue Scanning'") !== false, 'continue button becomes pause while running');
$expect(strpos($js, "if(!loops[host]){return;}") !== false, 'paused client loop cannot schedule another scan step');
$expect(strpos($js, "requestedAction!=='pause'") !== false, 'interrupted Continue reconnects instead of pausing');
$expect(strpos($css, '.website-history-status.is-paused') !== false, 'paused history badge styled');
$expect(strpos($migration, "'paused'") !== false, 'migration adds paused enum value');

fwrite(STDOUT, 'V0.2.48 contract checks passed: ' . count($checks) . "\n");
