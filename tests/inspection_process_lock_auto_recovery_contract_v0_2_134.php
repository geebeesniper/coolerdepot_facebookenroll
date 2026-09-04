<?php
/**
 * EN: Static contract for v0.2.134 self-healing verification locks plus manual-unlock fallback.
 * 中文：v0.2.134 验证锁自动恢复 + 手动解锁兜底的静态契约测试。
 */
$root = dirname(__DIR__);
$lock = file_get_contents($root . '/app/Services/InspectionProcessLock.php');
$config = file_get_contents($root . '/config/config.php');
$view = file_get_contents($root . '/app/Views/admin/settings.php');
$js = file_get_contents($root . '/public/assets/app.js');
$version = trim((string)file_get_contents($root . '/VERSION'));

$checks = [
    'release version advanced' => $version === '0.2.134',
    'dedicated mysql liveness lease acquired' => str_contains($lock, 'SELECT GET_LOCK(?, 0)')
        && str_contains($lock, 'PDO::ATTR_PERSISTENT => false'),
    'dead lease is detected automatically' => str_contains($lock, 'SELECT IS_USED_LOCK(?)')
        && str_contains($lock, 'recoverUnhealthyLock'),
    'orphan is deleted by token safely' => str_contains($lock, 'DELETE FROM cdsp_inspection_locks WHERE sales_user_id=? AND lock_token=?'),
    'acquire retries after automatic recovery' => str_contains($lock, 'self::recoverUnhealthyLock($salesUserId)')
        && str_contains($lock, 'for ($attempt = 0; $attempt < 2; $attempt++)'),
    'status reads self-heal' => str_contains($lock, 'public static function isLocked')
        && str_contains($lock, 'if (self::recoverUnhealthyLock($salesUserId))'),
    'settings reads self-heal' => str_contains($lock, 'public static function activeLocks')
        && str_contains($lock, 'foreach ($ids as $salesUserId)'),
    'shutdown release fallback exists' => str_contains($lock, 'register_shutdown_function')
        && str_contains($lock, 'releaseAllOwned'),
    'hard timeout is configurable and bounded' => str_contains($config, "INSPECTION_LOCK_RECOVERY_SECONDS")
        && str_contains($config, "max(300,min(3600"),
    'manual fallback remains available' => str_contains($lock, 'public static function forceRelease')
        && str_contains($view, 'In Case: Manual Unlock')
        && str_contains($view, '>Manual Unlock<'),
    'old primary lock wording is replaced' => !str_contains($view, '>Post Verification Locks<')
        && !str_contains($view, '>Unlock<'),
    'four languages describe automatic recovery' => substr_count($js, 'verificationLocksHelp:') === 4
        && str_contains($js, "postVerificationLocks:'In Case: Manual Unlock'")
        && str_contains($js, "unlock:'Manual Unlock'"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "v0.2.134 inspection lock auto-recovery contract failed:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}

echo "v0.2.134 inspection lock auto-recovery contract OK\n";
