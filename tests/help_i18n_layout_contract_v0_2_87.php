<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) { $failures[] = $message; }
};

$version = trim($read('VERSION'));
$view = $read('app/Views/help.php');
$admin = $read('app/Views/help/admin.php');
$sales = $read('app/Views/help/sales.php');
$css = $read('public/assets/app.css');

$expect(version_compare($version, '0.2.87', '>='), 'VERSION must be >= 0.2.87.');
$expect(str_contains($view, "localStorage.getItem('cdsp-admin-language')"), 'Help must read the same app language key.');
$expect(str_contains($view, "['en','zh-CN','zh-TW','es']"), 'Help must support EN / 简 / 繁 / ES.');
$expect(str_contains($view, "document.getElementById('appLanguageSwitch')"), 'Help must bind to the application language switch.');
$expect(str_contains($view, "cdsp:language-changed.helpPage"), 'Help must follow the app language-change event.');
$expect(str_contains($view, "window.addEventListener('storage'"), 'Help must resync cross-tab language changes.');
$expect(str_contains($view, "window.addEventListener('focus'"), 'Help must resync language on focus.');
$expect(str_contains($view, "root.classList.add('help-lang-'+lang)"), 'Help must switch its visible language class.');
$expect(str_contains($view, "'zh-CN':{"), 'Simplified Chinese static-label translations are missing.');
$expect(str_contains($view, "'zh-TW':{"), 'Traditional Chinese static-label translations are missing.');
$expect(str_contains($view, 'help-language-pending'), 'Help must avoid flashing the wrong language before initialization.');
$expect(!str_contains($view, '(function($)'), 'Help must not depend on an unbound jQuery IIFE parameter.');
$expect(str_contains($view, '$helpVersion'), 'Help must derive the displayed version dynamically.');

foreach (['admin' => $admin, 'sales' => $sales] as $role => $guide) {
    $expect(!str_contains($guide, 'V0.2.85'), ucfirst($role) . ' Help still hardcodes V0.2.85.');
    $expect(str_contains($guide, 'htmlspecialchars((string)$helpVersion'), ucfirst($role) . ' Help must render the current VERSION dynamically.');
    $expect(!str_contains($guide, 'new browser tab'), ucfirst($role) . ' Help still claims Help opens in a separate tab.');
    $expect(!str_contains($guide, '新的浏览器标签页'), ucfirst($role) . ' Help still contains the obsolete new-tab Chinese text.');
    $expect(str_contains($guide, 'class="en"'), ucfirst($role) . ' English content blocks missing.');
    $expect(str_contains($guide, 'class="zh"'), ucfirst($role) . ' Simplified Chinese content blocks missing.');
    $expect(str_contains($guide, 'class="zh-tw"'), ucfirst($role) . ' Traditional Chinese content blocks missing.');
    $expect(str_contains($guide, 'class="es"'), ucfirst($role) . ' Spanish content blocks missing.');
}

$expect(str_contains($css, 'v0.2.87 — Help uses the application language source'), 'v0.2.87 Help CSS marker missing.');
$expect(str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr));'), 'Help cards/workflow must use readable two-column layout.');
$expect(str_contains($css, 'grid-template-areas:'), 'Quick Start steps must separate number/title/copy instead of concatenating text.');
$expect(str_contains($css, '"number title"') && str_contains($css, '"copy copy"'), 'Quick Start grid areas are incomplete.');
$expect(str_contains($css, '.app-help-page .card p{'), 'Help card paragraph spacing rule missing.');
$expect(str_contains($css, '@media(max-width:900px)'), 'Help responsive single-column fallback missing.');

if ($failures) {
    fwrite(STDERR, "V0.2.87 Help i18n/layout contract: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "V0.2.87 Help i18n/layout contract passed.\n");
