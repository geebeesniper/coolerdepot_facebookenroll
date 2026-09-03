<?php
/**
 * EN: Static contract for v0.2.29 Admin force-unlock and hard preflight gating.
 * 中文：v0.2.29 Admin 手动解锁与硬前置检查静态契约。
 */
$root = dirname(__DIR__);
$lock = file_get_contents($root . '/app/Services/InspectionProcessLock.php');
$settingsController = file_get_contents($root . '/app/Controllers/AdminSettingsController.php');
$settingsView = file_get_contents($root . '/app/Views/admin/settings.php');
$routes = file_get_contents($root . '/index.php');
$js = file_get_contents($root . '/public/assets/app.js');

$preflightPos = strpos($js, "'/api/inspect/preflight'");
$inspectPos = strpos($js, "'/api/inspect',", $preflightPos !== false ? $preflightPos : 0);

$checks = [
    'Durable lock table is used' => strpos($lock, 'cdsp_inspection_locks') !== false,
    'Lock ownership token protects late release' => strpos($lock, 'lock_token=?') !== false
        && strpos($lock, 'self::$ownedTokens') !== false,
    'Admin force release exists' => strpos($lock, 'function forceRelease') !== false,
    'Admin can list active locks' => strpos($lock, 'function activeLocks') !== false,
    'Admin unlock route exists' => strpos($routes, "'/admin/inspection-lock/unlock'") !== false,
    'Admin unlock is CSRF protected' => strpos($settingsController, 'function unlockInspection') !== false
        && strpos($settingsController, "Csrf::verify(\$_POST['_csrf'] ?? null)") !== false,
    'Settings shows active verification locks' => strpos($settingsView, 'Post Verification Locks') !== false
        && strpos($settingsView, '>Unlock<') !== false,
    'Duplicate preflight runs before full inspection' => $preflightPos !== false && $inspectPos !== false && $preflightPos < $inspectPos,
    'Preflight failure skips expensive steps' => strpos($js, "['fetch','date','final'].forEach") !== false
        && strpos($js, "'Duplicate check failed. Verification was not started.'") !== false,
    'Save form is hidden until result is saveable' => strpos($js, "$('#salesVerifiedSaveForm').addClass('hidden')") !== false
        && strpos($js, "toggleClass('hidden',manualRequired||!d.ok)") !== false,
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) {
        $failed[] = $label;
    }
}
exit($failed ? 1 : 0);
