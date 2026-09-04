<?php
/**
 * EN: Static contract for v0.2.136 shorter verification hard-recovery timeout.
 * 中文：v0.2.136 验证锁硬性自动恢复时间缩短的静态契约测试。
 */
$root = dirname(__DIR__);
$config = file_get_contents($root . '/config/config.php');
$lock = file_get_contents($root . '/app/Services/InspectionProcessLock.php');
$envExample = file_get_contents($root . '/.env.example');
$version = trim((string)file_get_contents($root . '/VERSION'));

$checks = [
    'release version advanced' => $version === '0.2.136',
    'default hard recovery is five minutes' => str_contains(
        $config,
        "getenv('INSPECTION_LOCK_RECOVERY_SECONDS') ?: 300"
    ),
    'service fallback is five minutes' => str_contains(
        $lock,
        "inspection_lock_recovery_seconds'] ?? 300"
    ),
    'minimum remains five minutes' => str_contains($config, 'max(300,min(3600')
        && str_contains($lock, 'return max(300, min(3600, $seconds));'),
    'environment override remains supported' => str_contains($config, 'INSPECTION_LOCK_RECOVERY_SECONDS'),
    'example environment matches new default' => str_contains($envExample, 'INSPECTION_LOCK_RECOVERY_SECONDS=300'),
    'liveness recovery remains intact' => str_contains($lock, 'SELECT IS_USED_LOCK(?)')
        && str_contains($lock, 'inspection_process_lock_auto_recovered_orphan'),
    'manual fallback remains intact' => str_contains($lock, 'public static function forceRelease'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "v0.2.136 verification lock timeout contract failed:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}

echo "v0.2.136 verification lock timeout contract OK\n";
