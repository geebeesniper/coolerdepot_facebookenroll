<?php
/**
 * File / 文件：tests/browser_api_session_contract_v0_2_12.php
 * EN: Regression contract for separating stateful browser API routes from stateless external REST/GraphQL routes.
 * 中文：回归检查浏览器有 Session API 与外部无 Session REST/GraphQL API 的边界。
 */

$bootstrap = (string)file_get_contents(dirname(__DIR__) . '/config/bootstrap.php');
$appJs = (string)file_get_contents(dirname(__DIR__) . '/public/assets/app.js');
$footer = (string)file_get_contents(dirname(__DIR__) . '/app/Views/layout/footer.php');
$version = trim((string)file_get_contents(dirname(__DIR__) . '/VERSION'));

$failures = [];

$checks = [
    "strncmp(\$requestPath, '/api/v1/', 8) === 0" => 'REST v1 must remain stateless.',
    "\$requestPath === '/graphql'" => 'GraphQL must remain stateless.',
    "ErrorPage::isApiRequest() intentionally remains broader" => 'Browser/API error-format boundary must be documented.',
    'function escapeHtml(value)' => 'Shared HTML escape helper must exist in app.js.',
    "'<strong>'+escapeHtml(data.date)+'</strong>'" => 'Sales chart tooltip must use the shared escape helper.',
];

foreach ($checks as $needle => $message) {
    $haystack = str_contains($needle, 'escapeHtml') ? $appJs : $bootstrap;
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
}

if (!str_contains($footer, "app.js?v=<?= rawurlencode(\$config['app']['version']) ?>")) {
    $failures[] = 'app.js must use release-version cache busting.';
}

if (version_compare($version, '0.2.12', '<')) {
    $failures[] = 'VERSION must be 0.2.12 or newer.';
}

if ($failures) {
    fwrite(STDERR, "Browser API/session contract: FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Browser API/session contract for app v{$version}: PASS\n";
