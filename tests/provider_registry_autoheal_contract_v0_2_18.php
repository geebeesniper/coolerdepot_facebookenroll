<?php
/**
 * EN: Static contract test for V0.2.18 Provider Registry auto-repair.
 * 中文：V0.2.18 Provider Registry 自动修复的静态契约测试。
 */
$root = dirname(__DIR__);
$file = $root . '/app/Models/ProviderProfile.php';
$src = file_get_contents($file);
$needles = [
    "if (!self::tableExists())",
    "if (self::count() <= 0)",
    "self::repairRegistryEnabledFlag();",
    'Setting::set(\'provider_registry_enabled\', \'1\', $userId);',
    "Provider registry flag auto-repaired",
];
foreach ($needles as $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "FAIL: missing contract marker: {$needle}\\n");
        exit(1);
    }
}
echo "PASS: V0.2.18 Provider Registry auto-repair contract.\\n";
