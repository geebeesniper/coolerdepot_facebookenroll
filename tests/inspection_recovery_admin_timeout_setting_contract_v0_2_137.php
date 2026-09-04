<?php
/**
 * EN: Static contract for v0.2.137 Admin-configurable verification hard-recovery timeout.
 * 中文：v0.2.137 Admin 可配置验证锁硬性自动恢复时间的静态契约测试。
 */
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$version = trim($read('VERSION'));
$lock = $read('app/Services/InspectionProcessLock.php');
$controller = $read('app/Controllers/AdminSettingsController.php');
$view = $read('app/Views/admin/settings.php');
$routes = $read('index.php');
$js = $read('public/assets/app.js');
$css = $read('public/assets/app.css');
$help = $read('app/Views/help/admin.php');
$env = $read('.env.example');

$checks = [
    'release version advanced' => $version === '0.2.137',
    'persistent Admin setting key exists' => str_contains($lock, "inspection_lock_recovery_minutes"),
    'minimum is five minutes' => str_contains($lock, 'MIN_RECOVERY_MINUTES = 5'),
    'maximum is sixty minutes' => str_contains($lock, 'MAX_RECOVERY_MINUTES = 60'),
    'default remains five minutes' => str_contains($lock, 'DEFAULT_RECOVERY_MINUTES = 5'),
    'DB setting overrides fallback' => str_contains($lock, "Setting::get(self::RECOVERY_SETTING_KEY, null)"),
    'hard timeout uses Admin minutes' => str_contains($lock, 'return self::recoveryMinutes() * 60;'),
    'environment fallback remains supported' => str_contains($lock, "inspection_lock_recovery_seconds'] ?? (self::DEFAULT_RECOVERY_MINUTES * 60)")
        && str_contains($env, 'INSPECTION_LOCK_RECOVERY_SECONDS=300'),
    'orphaned process still recovers immediately' => str_contains($lock, 'SELECT IS_USED_LOCK(?)')
        && str_contains($lock, 'inspection_process_lock_auto_recovered_orphan'),
    'manual unlock fallback remains intact' => str_contains($lock, 'public static function forceRelease'),
    'admin save route exists' => str_contains($routes, "'/admin/settings/verification-recovery'"),
    'admin save action validates whole minutes' => str_contains($controller, 'saveInspectionRecovery')
        && str_contains($controller, "preg_match('/^\\d+$/', \$raw)"),
    'admin setting is persisted through lock service' => str_contains($controller, 'InspectionProcessLock::setRecoveryMinutes'),
    'settings page exposes bounded minute input' => str_contains($view, 'name="recovery_minutes"')
        && str_contains($view, 'inspectionRecoveryMinMinutes')
        && str_contains($view, 'inspectionRecoveryMaxMinutes'),
    'settings UI is localized' => substr_count($js, "verificationRecoveryTimeoutLabel:") === 4
        && substr_count($js, "saveRecoveryTimeout:") === 4,
    'new styles are component scoped' => str_contains($css, '.verification-locks-panel .verification-recovery-setting')
        && !str_contains($css, '.verification-recovery-setting input{'),
    'admin help documents 5-60 minute range' => str_contains($help, '5–60 分钟')
        && str_contains($help, '5 to 60 minutes'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "v0.2.137 verification recovery Admin timeout contract failed:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}

echo "v0.2.137 verification recovery Admin timeout contract OK\n";
