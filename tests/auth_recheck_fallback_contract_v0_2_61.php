<?php
$root = dirname(__DIR__);
$checks = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
    return (string)file_get_contents($full);
};

$index = $read('index.php');
$auth = $read('app/Controllers/AuthController.php');
$settingsController = $read('app/Controllers/AdminSettingsController.php');
$settingsView = $read('app/Views/admin/settings.php');
$accessView = $read('app/Views/auth/access_required.php');
$version = trim($read('VERSION'));

$checks['version'] = $version === '0.2.61';
$checks['route'] = str_contains($index, "'/auth/recheck'") && str_contains($index, "AuthController::class, 'recheck'");
$checks['server_recheck'] = str_contains($auth, 'public function recheck(): void')
    && str_contains($auth, "'authenticated' => true")
    && str_contains($auth, "'redirect_url' => \$this->configuredAuthFailureRedirectUrl()");
$checks['setting_read'] = str_contains($auth, "Setting::get('auth_failure_redirect_url', '')");
$checks['refresh_on_success'] = str_contains($accessView, 'window.location.reload()');
$checks['redirect_on_failure'] = str_contains($accessView, 'window.location.replace(target)');
$checks['settings_field'] = str_contains($settingsView, 'name="auth_failure_redirect_url"')
    && str_contains($settingsView, 'Portal fallback URL');
$checks['settings_persist'] = str_contains($settingsController, "Setting::set('auth_failure_redirect_url'")
    && str_contains($settingsController, "Setting::delete('auth_failure_redirect_url')");
$checks['url_validation'] = str_contains($settingsController, 'FILTER_VALIDATE_URL')
    && str_contains($settingsController, "['http','https']");

$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'V0.2.61 contract failed: ' . implode(', ', $failed) . "\n");
    exit(1);
}

echo 'V0.2.61 contract checks passed: ' . count($checks) . "\n";
