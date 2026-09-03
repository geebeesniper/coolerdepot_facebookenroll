<?php
/**
 * File / 文件：tests/craigslist_manual_contract_v0_2_13.php
 * EN: Regression contract for the V0.2.13 Craigslist HTTP 403 manual-verification workflow.
 * 中文：V0.2.13 Craigslist HTTP 403 手动验证流程的回归契约测试。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) {
        require dirname(__DIR__) . '/app/'
            . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

use App\Services\PlatformUrl;

$failures = [];
$checks = 0;

/**
 * EN: Record one contract assertion.
 * 中文：记录一项契约断言。
 *
 * @param bool $condition Assertion condition. / 断言条件。
 * @param string $label Human-readable assertion label. / 可读的断言说明。
 *
 * @return void No value is returned. / 无返回值。
 */
function contractCheck(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $label;
        echo "FAIL {$label}\n";
        return;
    }
    echo "PASS {$label}\n";
}

$share = 'https://www.craigslist.org/view/d/pasadena-los-angeles-chocolate-salon-on/hL65mwkMvyiiX2khDkpfyE';
contractCheck(PlatformUrl::platformFor($share) === 'craigslist', 'modern /view/d Craigslist URL is detected');
contractCheck(PlatformUrl::normalize($share, 'craigslist') === $share, 'modern /view/d Craigslist URL is preserved');
contractCheck(PlatformUrl::externalId('craigslist', $share) === 'hL65mwkMvyiiX2khDkpfyE', 'modern Craigslist share token is extracted as external ID');

$root = dirname(__DIR__);
$postInspector = file_get_contents($root . '/app/Services/PostInspector.php');
$apiController = file_get_contents($root . '/app/Controllers/ApiController.php');
$inspection = file_get_contents($root . '/app/Models/Inspection.php');
$post = file_get_contents($root . '/app/Models/Post.php');
$js = file_get_contents($root . '/public/assets/app.js');
$schema = file_get_contents($root . '/database/schema.sql');

contractCheck(str_contains($postInspector, "CRAIGSLIST_REMOTE_BLOCKED"), 'Craigslist HTTP 403 has a dedicated manual-required code');
contractCheck(str_contains($postInspector, "finalizeCraigslistManual"), 'manual Craigslist finalizer exists');
contractCheck(str_contains($apiController, "manual_craigslist"), 'browser inspect endpoint accepts manual Craigslist completion');
contractCheck(str_contains($inspection, "verification_status IN ('verified','manual_pending')"), 'save token accepts validated manual_pending inspection');
contractCheck(str_contains($post, "['verified','manual_pending']"), 'saved post preserves manual_pending verification state');
contractCheck(str_contains($js, "craigslistManualVerification"), 'Sales UI exposes manual Craigslist fields only when needed');
contractCheck(str_contains($js, "saveForAdminReview"), 'Sales UI distinguishes manual Admin-review save from full verification');
contractCheck(substr_count($schema, "ENUM('verified','manual_pending','failed')") >= 2, 'schema supports manual_pending in inspections and posts');

$version = trim((string)file_get_contents($root . '/VERSION'));
contractCheck(version_compare($version, '0.2.13', '>='), 'VERSION is 0.2.13 or newer');

if ($failures) {
    fwrite(STDERR, count($failures) . " contract failure(s).\n");
    exit(1);
}

echo "{$checks} Craigslist manual-verification contract checks passed.\n";
