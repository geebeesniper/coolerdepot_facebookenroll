<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) { $failures[] = $message; }
};
$read = static function (string $relative) use ($root): string {
    $text = @file_get_contents($root . '/' . $relative);
    if ($text === false) { throw new RuntimeException('Could not read ' . $relative); }
    return $text;
};

$version = trim($read('VERSION'));
$check(version_compare($version, '0.2.83', '>='), 'VERSION must be 0.2.83 or newer.');

$controller = $read('app/Controllers/HelpController.php');
$check(str_contains($controller, 'Auth::requireLogin()'), 'Help must remain authenticated.');
$check(str_contains($controller, "['sales', 'admin']"), 'Help role isolation must remain explicit.');
$check(str_contains($controller, "'/Views/help/' . \$role . '.php'") || str_contains($controller, "'/docs/user-guides/' . \$role . '.html'"), 'Help must remain role-specific.');
$check(str_contains($controller, "Cache-Control: private, no-store, max-age=0"), 'Help must disable stale browser caching after upgrades.');

$appJs = $read('public/assets/app.js');
foreach (["help:'Help'", "help:'帮助'", "help:'幫助'", "help:'Ayuda'"] as $marker) {
    $check(str_contains($appJs, $marker), 'Global Help menu language is missing: ' . $marker);
}

foreach (['sales', 'admin'] as $role) {
    $guide = $read('docs/user-guides/' . $role . '.html');
    $prefix = ucfirst($role) . ' guide: ';
    $check(str_contains($guide, 'V0.2.83'), $prefix . 'guide version was not updated.');
    $check(str_contains($guide, "const STORAGE_KEY='cdsp-admin-language'"), $prefix . 'must use the same language key as the application.');
    $check(str_contains($guide, "SUPPORTED=['en','zh-CN','zh-TW','es']"), $prefix . 'must support EN / 简 / 繁 / ES.');
    $check(str_contains($guide, "window.addEventListener('storage'"), $prefix . 'must react when the application language changes in another tab.');
    $check(str_contains($guide, "window.addEventListener('focus',applyLanguage)"), $prefix . 'must resync language when Help regains focus.');
    $check(!str_contains($guide, 'cdspGuideLang'), $prefix . 'must not keep an independent Help-language preference.');
    $check(!str_contains($guide, 'data-langmode='), $prefix . 'must not expose the old independent EN/中文/Both selector.');
    $check(str_contains($guide, 'data-help-language-label'), $prefix . 'must show the language inherited from the app.');
    $check(str_contains($guide, 'help-lang-zh-TW'), $prefix . 'Traditional Chinese mode missing.');
    $check(str_contains($guide, 'help-lang-es'), $prefix . 'Spanish mode missing.');
    $check(str_contains($guide, 'class="es"'), $prefix . 'Spanish content blocks missing.');
    $check(str_contains($guide, 'class="zh-tw"'), $prefix . 'Traditional Chinese content blocks missing.');
    $check(str_contains($guide, 'Guía'), $prefix . 'Spanish guide content missing.');
    $check(str_contains($guide, '使用說明'), $prefix . 'Traditional Chinese guide title missing.');

    preg_match_all('/class="en"/', $guide, $en);
    preg_match_all('/class="es"/', $guide, $es);
    preg_match_all('/class="zh"/', $guide, $zh);
    preg_match_all('/class="zh-tw"/', $guide, $zhTw);
    $check(count($en[0]) >= 100, $prefix . 'expected detailed English guide content.');
    $check(count($en[0]) === count($es[0]), $prefix . 'every English Help block must have a Spanish peer.');
    $check(count($zh[0]) === count($zhTw[0]), $prefix . 'every Simplified Chinese Help block must have a Traditional Chinese peer.');
}

if ($failures) {
    fwrite(STDERR, "v0.2.83 Help language-follow contract: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "v0.2.83 Help language-follow contract passed.\n");
