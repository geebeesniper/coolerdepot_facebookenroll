<?php
/**
 * CoolerDepot Sales Post Tracker v0.2.73 UI regression contract.
 * Verifies Sales-card action containment and isolated Daily Sales Review behavior.
 */

$root = dirname(__DIR__);
$js = (string) file_get_contents($root . '/public/assets/app.js');
$css = (string) file_get_contents($root . '/public/assets/app.css');
$version = trim((string) file_get_contents($root . '/VERSION'));

$failures = [];

$check = static function (bool $ok, string $label) use (&$failures): void {
    if (!$ok) {
        $failures[] = $label;
    }
};

$check($version === '0.2.73', 'VERSION must be 0.2.73');
$check(str_contains($js, 'function openDailyReviewOnly($card)'), 'review-only loader missing');
$check(str_contains($js, "event.stopImmediatePropagation();\n        openDailyReviewOnly"), 'Daily Sales Review click must stop delegated card clicks');

$handlerStart = strpos($js, "\$grid.on('click','[data-daily-review]'", 0);
$handlerEnd = $handlerStart === false ? false : strpos($js, "    });", $handlerStart);
$handler = ($handlerStart !== false && $handlerEnd !== false)
    ? substr($js, $handlerStart, $handlerEnd - $handlerStart + 7)
    : '';
$check($handler !== '', 'Daily Sales Review handler missing');
$check(!str_contains($handler, 'openExpandedPosts('), 'Daily Sales Review handler must not open Post Grid');
$check(str_contains($js, 'if(dailyReviewOnlyMode){'), 'review-only cleanup missing');

$check(str_contains($css, '/* v0.2.73 Sales-card action containment + isolated Daily Sales Review */'), 'v0.2.73 CSS override missing');
$check(str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr));'), 'desktop action grid containment missing');
$check(str_contains($css, 'white-space:normal;'), 'long localized action labels must wrap');
$check(str_contains($css, 'overflow-wrap:anywhere;'), 'long localized action labels must stay inside cards');
$check(str_contains($css, 'grid-template-columns:minmax(0,1fr) 30px;'), 'phone review/settings layout missing');

if ($failures) {
    fwrite(STDERR, "v0.2.73 UI regression contract: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "v0.2.73 UI regression contract: PASS\n");
