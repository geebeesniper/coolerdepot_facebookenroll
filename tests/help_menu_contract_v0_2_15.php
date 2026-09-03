<?php
/**
 * V0.2.15 in-app Help/manual contract test.
 * V0.2.15 站内 Help/使用说明契约测试。
 */
$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $relative) use ($root): string {
    $text = @file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException('Could not read ' . $relative);
    }
    return $text;
};

$version = trim($read('VERSION'));
$expect(version_compare($version, '0.2.15', '>='), 'VERSION must be 0.2.15 or newer.');

$index = $read('index.php');
$expect(str_contains($index, "use App\\Controllers\\HelpController;"), 'HelpController import missing.');
$expect(str_contains($index, "\$router->get('/help', [HelpController::class, 'show']);"), 'Authenticated /help route missing.');

$controller = $read('app/Controllers/HelpController.php');
$expect(str_contains($controller, 'Auth::requireLogin()'), 'Help route must require authentication.');
$expect(str_contains($controller, "['sales', 'admin']"), 'Help route must explicitly restrict supported roles.');
$expect(str_contains($controller, "'/Views/help/' . \$role . '.php'") || str_contains($controller, "'/docs/user-guides/' . \$role . '.html'"), 'Help route must select the guide by authenticated role.');

$header = $read('app/Views/layout/header.php');
$expect(str_contains($header, 'href="<?= Util::e($base) ?>/help"'), 'Help menu link missing.');
$expect(str_contains($header, 'data-nav-i18n="help"'), 'Help menu translation key missing.');
$helpLinkStandalone = (bool)preg_match('/href="<\?= Util::e\(\$base\) \?>\/help"[\s\S]{0,240}target="_blank"/', $header);
$expect(version_compare($version, '0.2.85', '<') || !$helpLinkStandalone, 'Help should be a normal in-app page on V0.2.85+.');

$js = $read('public/assets/app.js');
foreach (['help:\'Help\'', 'help:\'帮助\'', 'help:\'幫助\'', 'help:\'Ayuda\''] as $marker) {
    $expect(str_contains($js, $marker), 'Missing Help menu translation: ' . $marker);
}

foreach (['sales', 'admin'] as $role) {
    $guide = $read('docs/user-guides/' . $role . '.html');
    $expect((bool)preg_match('/V0\.2\.(?:1[5-9]|[2-9][0-9])/', $guide), ucfirst($role) . ' guide version is older than V0.2.15.');
    $expect(str_contains($guide, 'Help'), ucfirst($role) . ' guide does not document the Help menu.');
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "V0.2.15 in-app Help/manual contract: PASS\n";
