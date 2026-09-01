<?php
/**
 * File / 文件：tests/api_contract_v0_2_06.php
 * EN: Automated regression/contract test for api contract v0 2 06.
 * 中文：用于 api contract v0 2 06 的自动回归/契约测试。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\GraphqlEngine;

/**
 * EN: Perform the contract assert helper used by this automated regression test.
 * 中文：执行 当前自动回归测试使用的“contract assert”辅助操作。
 *
 * @param bool $condition Condition value used by this operation. / 本操作使用的“condition”参数值。
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 */
function contractAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$index = (string)file_get_contents($root . '/index.php');
$schema = (string)file_get_contents($root . '/database/schema.sql');
$sdl = (string)file_get_contents($root . '/docs/schema.graphql');

foreach ([
    '/api/v1/health',
    '/api/v1/auth/exchange',
    '/api/v1/auth/me',
    '/api/v1/auth/logout',
    '/api/v1/admin/users',
    '/api/v1/sales/profile',
    '/graphql',
] as $route) {
    contractAssert(str_contains($index, $route), 'Missing route ' . $route);
}

contractAssert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS cdsp_api_tokens'), 'Missing cdsp_api_tokens schema.');
contractAssert(str_contains($sdl, 'authExchange(input: AuthHandoffInput!)'), 'Missing GraphQL authExchange SDL.');
contractAssert(str_contains($sdl, 'adminUsers: [User!]!'), 'Missing GraphQL Admin RBAC field.');
contractAssert(str_contains($sdl, 'salesProfile: User!'), 'Missing GraphQL Sales RBAC field.');

$engine = new GraphqlEngine();
$result = $engine->execute('query Contract { api: apiVersion app: appVersion }');
contractAssert(($result['data']['api'] ?? null) === 'v1', 'GraphQL apiVersion query failed.');
contractAssert(($result['data']['app'] ?? null) === (string)$config['app']['version'], 'GraphQL appVersion query failed.');



// Security regression: overly deep GraphQL documents must be rejected before resolver execution.
$deep = '{ apiVersion';
for ($i = 0; $i < 12; $i++) {
    $deep .= ' { x';
}
for ($i = 0; $i < 12; $i++) {
    $deep .= ' }';
}
$deep .= ' }';
$depthRejected = false;
try {
    $engine->execute($deep);
} catch (\App\Core\ApiException $e) {
    $depthRejected = $e->apiCode() === 'graphql_complexity_limit';
}
contractAssert($depthRejected, 'GraphQL depth limit was not enforced.');

// Security regression: excessive aliases/fields must be rejected deterministically.
$many = '{ ';
for ($i = 0; $i < 60; $i++) {
    $many .= 'f' . $i . ': apiVersion ';
}
$many .= '}';
$fieldRejected = false;
try {
    $engine->execute($many);
} catch (\App\Core\ApiException $e) {
    $fieldRejected = $e->apiCode() === 'graphql_complexity_limit';
}
contractAssert($fieldRejected, 'GraphQL field-count limit was not enforced.');

// Signed handoff canonical fields must reject embedded control/newline characters before DB work.
$config['auth']['handoff_secret'] = str_repeat('x', 32);
$badPayload = [
    'uid' => "bad\nuid",
    'sales_id' => '',
    'name' => 'API Contract',
    'role' => 'admin',
    'ts' => time(),
    'nonce' => 'contract-nonce-1234',
    'sig' => str_repeat('a', 64),
];
$badIdRejected = false;
try {
    (new \App\Services\ExternalAuthService())->accept($badPayload);
} catch (\RuntimeException $e) {
    $badIdRejected = str_contains($e->getMessage(), 'external user id');
}
contractAssert($badIdRejected, 'Signed handoff control-character validation was not enforced.');

// JSON-body endpoints must reject browser-simple content types.
$_SERVER['CONTENT_TYPE'] = 'text/plain';
$contentTypeRejected = false;
try {
    \App\Core\ApiRequest::requireJsonContentType();
} catch (\App\Core\ApiException $e) {
    $contentTypeRejected = $e->status() === 415 && $e->apiCode() === 'unsupported_media_type';
}
contractAssert($contentTypeRejected, 'JSON Content-Type enforcement was not active.');
$_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
\App\Core\ApiRequest::requireJsonContentType();

// Bearer parser must reject malformed authorization schemes without leaking values.
$_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc';
$bearerRejected = false;
try {
    \App\Core\ApiRequest::bearerToken();
} catch (\App\Core\ApiException $e) {
    $bearerRejected = $e->status() === 401 && $e->apiCode() === 'invalid_authorization_header';
}
contractAssert($bearerRejected, 'Malformed Authorization header was not rejected.');
unset($_SERVER['HTTP_AUTHORIZATION']);

echo "API contract regression for app v" . $config['app']['version'] . ": PASS\n";
