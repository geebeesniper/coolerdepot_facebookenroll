<?php
/**
 * EN: Regression contract for per-Sales Marketplace inspection locking introduced in v0.2.28.
 * 中文：v0.2.28 引入的按 Sales 用户隔离 Marketplace Inspection 锁回归契约。
 *
 * EN: v0.2.29 replaced connection-owned advisory locks with durable token-owned rows so Admin can
 * force-unlock a stuck Sales gate. This test accepts either implementation while preserving the
 * original one-active-inspection-per-Sales behavior.
 * 中文：v0.2.29 将连接级 advisory lock 替换为可由 Admin 手动清除的 token 行锁；本测试兼容
 * 两种实现，同时继续验证“每个 Sales 同时只有一个 Inspection”的原始契约。
 */
$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ApiController.php');
$lock = file_get_contents($root . '/app/Services/InspectionProcessLock.php');
$routes = file_get_contents($root . '/index.php');
$js = file_get_contents($root . '/public/assets/app.js');

$advisory = strpos($lock, 'GET_LOCK(?, 0)') !== false;
$durable = strpos($lock, 'cdsp_inspection_locks') !== false
    && strpos($lock, 'INSERT IGNORE INTO cdsp_inspection_locks') !== false;

$checks = [
    'Per-Sales lock implementation exists' => $advisory || $durable,
    'Inspect status route exists' => strpos($routes, "'/api/inspect/status'") !== false,
    'Inspect returns 409 when lock is busy' => strpos($controller, "'INSPECTION_IN_PROGRESS'") !== false
        && strpos($controller, '], 409);') !== false,
    'Inspect releases process lock in finally' => strpos($controller, 'finally {') !== false
        && strpos($controller, 'InspectionProcessLock::release($salesUserId)') !== false,
    'Detected-platform refresh respects busy lock' => strpos($js, "prop('disabled',salesInspectionBusy||!platform)") !== false
        || strpos($js, "prop('disabled', salesInspectionBusy || !platform)") !== false,
    'Reopened modal synchronizes server lock' => strpos($js, 'syncSalesInspectionProcessState(true);') !== false,
    'Closing modal does not abort inspection' => strpos($js, 'Closing the modal is only a visual action') !== false
        && strpos($js, 'salesInspectionRequest.abort()') === false,
    'AJAX completion rechecks authoritative lock' => strpos($js, 'syncSalesInspectionProcessState(false);') !== false,
    'Busy state is restored across modal reopen in same tab' => strpos($js, 'SALES_INSPECTION_BUSY_KEY') !== false
        && strpos($js, 'sessionStorage.setItem') !== false,
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) {
        $failed[] = $label;
    }
}

exit($failed ? 1 : 0);
