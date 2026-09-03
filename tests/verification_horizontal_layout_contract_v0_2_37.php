<?php
/** v0.2.37 contract: verified listing content is media-left/copy-right and responsive. */
$root = dirname(__DIR__);
$view = file_get_contents($root . '/app/Views/sales/_submit_form.php');
$css = file_get_contents($root . '/public/assets/app.css');

$checks = [
    'view has horizontal main wrapper' => strpos($view, 'class="sales-verification-main"') !== false,
    'image region is inside main wrapper' => strpos($view, 'class="sales-verification-images sales-verification-media hidden"') !== false,
    'copy region exists' => strpos($view, 'class="sales-verification-copy"') !== false,
    'title remains addressable by JS' => strpos($view, 'id="resultTitle"') !== false,
    'description remains addressable by JS' => strpos($view, 'id="resultDescription"') !== false,
    'images remain addressable by JS' => strpos($view, 'id="resultImages"') !== false,
    'facts remain below main content' => strpos($view, 'class="sales-verification-facts"') !== false,
    'desktop is two-column' => strpos($css, '.sales-verification-main{') !== false && strpos($css, 'grid-template-columns:minmax(118px,31%) minmax(0,1fr)') !== false,
    'tablet submit layout collapses safely' => strpos($css, '@media(max-width:900px)') !== false && strpos($css, '.sales-submit-modal-scroll .sales-submit-layout') !== false,
    'phone verification stacks' => strpos($css, '@media(max-width:560px)') !== false && preg_match('/@media\(max-width:560px\)[\s\S]*?\.sales-verification-main\s*\{[\s\S]*?grid-template-columns:1fr;/', $css) === 1,
    'small phone modal fits viewport' => strpos($css, '@media(max-width:420px)') !== false && strpos($css, 'max-height:100vh') !== false,
    'global 4px radius convention retained' => strpos($css, 'border-radius:4px') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
    }
}
if ($failed) {
    fwrite(STDERR, "FAILED:\n - " . implode("\n - ", $failed) . "\n");
    return 1;
}
printf("v0.2.37 verification layout contract: %d checks passed.\n", count($checks));
return 0;
