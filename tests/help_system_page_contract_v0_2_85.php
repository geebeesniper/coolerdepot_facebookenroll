<?php
$root = dirname(__DIR__);
$failures = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};

$version = trim($read('VERSION'));
$controller = $read('app/Controllers/HelpController.php');
$header = $read('app/Views/layout/header.php');
$view = $read('app/Views/help.php');
$css = $read('public/assets/app.css');
$admin = $read('app/Views/help/admin.php');
$sales = $read('app/Views/help/sales.php');

$expect(version_compare($version, '0.2.85', '>='), 'VERSION must be >= 0.2.85.');
$expect(str_contains($controller, "\$this->render('help'"), 'HelpController must render through the normal application layout.');
$expect(!str_contains($controller, 'readfile('), 'HelpController must not stream a standalone HTML document.');
$expect(str_contains($header, 'href="<?= Util::e($base) ?>/help"'), 'Help menu link is missing.');
$expect(!preg_match('/href="<\?= Util::e\(\$base\) \?>\/help"[\s\S]{0,240}target="_blank"/', $header), 'Help must behave like a normal in-app page, not a standalone new-tab document.');
$expect(str_contains($view, 'id="appHelpPage"'), 'Normal Help application view is missing.');
$expect(str_contains($view, "cdsp:language-changed.helpPage"), 'Help must follow the application language change event.');
$expect(str_contains($view, "localStorage.getItem('cdsp-admin-language')"), 'Help must read the shared application language setting.');
$expect(str_contains($css, 'v0.2.85 — Help is a normal application page'), 'v0.2.85 Help layout CSS marker missing.');
$expect(str_contains($css, '.app-help-guide-layout'), 'Help content layout CSS missing.');
$expect(str_contains($admin, 'app-help-toc') && str_contains($sales, 'app-help-toc'), 'Role-specific Help content must include the in-guide TOC.');
$expect(!str_contains($admin, '<header class="top">') && !str_contains($sales, '<header class="top">'), 'Standalone Help topbar must not appear inside the application page.');

if ($failures) {
    fwrite(STDERR, "V0.2.85 Help system-page contract: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "V0.2.85 Help system-page contract passed.\n";
