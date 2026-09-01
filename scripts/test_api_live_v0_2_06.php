<?php
/**
 * File / 文件：scripts/test_api_live_v0_2_06.php
 * EN: CLI maintenance/deployment script for test api live v0 2 06.
 * 中文：用于 test api live v0 2 06 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

/**
 * EN: Perform the fail helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“fail”辅助操作。
 *
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 */
function fail(string $message): void
{
    global $failures;
    $failures++;
    fwrite(STDERR, "FAIL  $message\n");
}

/**
 * EN: Perform the pass check helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“pass check”辅助操作。
 *
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 */
function passCheck(string $message): void
{
    echo "PASS  $message\n";
}

/**
 * EN: Perform the warn check helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“warn check”辅助操作。
 *
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 */
function warnCheck(string $message): void
{
    global $warnings;
    $warnings++;
    echo "WARN  $message\n";
}

/**
 * EN: Check or validate the expect helper used by this validation script.
 * 中文：检查或验证 当前验证脚本使用的“expect”辅助操作。
 *
 * @param bool $condition Condition value used by this operation. / 本操作使用的“condition”参数值。
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 */
function expect(bool $condition, string $message): void
{
    $condition ? passCheck($message) : fail($message);
}

/**
 * EN: Check or validate the canonical helper used by this validation script.
 * 中文：检查或验证 当前验证脚本使用的“canonical”辅助操作。
 *
 * @param array $payload Input payload supplied to this operation. / 传入本操作的输入载荷。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function canonical(array $payload): string
{
    return implode("\n", [
        (string)$payload['uid'],
        (string)$payload['sales_id'],
        (string)$payload['name'],
        (string)$payload['role'],
        (string)$payload['ts'],
        (string)$payload['nonce'],
    ]);
}

/**
 * EN: Perform the signed payload helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“signed payload”辅助操作。
 *
 * @param string $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
 * @param string $salesId Numeric Sales ID associated with the sales user or record. / 与 Sales 用户或记录关联的数字 Sales ID。
 * @param string $name Display or logical name associated with the operation. / 与本操作关联的显示名称或逻辑名称。
 * @param string $role Required or assigned application role. / 要求或分配的应用角色。
 * @param int $ts Unix timestamp used to validate the request time window. / 用于验证请求时间窗口的 Unix 时间戳。
 * @param string $nonce Single-use nonce used to prevent request replay. / 用于防止请求重放的一次性 Nonce。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function signedPayload(string $uid, string $salesId, string $name, string $role, int $ts, string $nonce): array
{
    global $config;
    $payload = compact('uid', 'salesId', 'name', 'role', 'ts', 'nonce');
    $payload = [
        'uid' => $uid,
        'sales_id' => $salesId,
        'name' => $name,
        'role' => $role,
        'ts' => $ts,
        'nonce' => $nonce,
    ];
    $payload['sig'] = hash_hmac('sha256', canonical($payload), (string)$config['auth']['handoff_secret']);
    return $payload;
}

/**
 * EN: Send or process the request http helper used by this validation script.
 * 中文：发送或处理 当前验证脚本使用的“request http”辅助操作。
 *
 * @param string $method HTTP or operation method being processed. / 正在处理的 HTTP 或操作方法。
 * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
 * @param ?array $json Json value used by this operation. / 本操作使用的“json”参数值。
 * @param array $headers Headers value used by this operation. / 本操作使用的“headers”参数值。
 * @param bool $insecure Insecure value used by this operation. / 本操作使用的“insecure”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
function requestHttp(string $method, string $url, ?array $json = null, array $headers = [], bool $insecure = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for the live API test.');
    }

    $responseHeaders = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
            $len = strlen($line);
            $line = trim($line);
            if ($line !== '' && str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($k))][] = trim($v);
            }
            return $len;
        },
    ]);

    if ($json !== null) {
        $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string)$raw, true);
    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'json' => is_array($decoded) ? $decoded : null,
        'raw' => (string)$raw,
    ];
}

/**
 * EN: Perform the header value helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“header value”辅助操作。
 *
 * @param array $response Response value used by this operation. / 本操作使用的“response”参数值。
 * @param string $name Display or logical name associated with the operation. / 与本操作关联的显示名称或逻辑名称。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function headerValue(array $response, string $name): string
{
    $values = $response['headers'][strtolower($name)] ?? [];
    return $values ? (string)end($values) : '';
}

/**
 * EN: Delete or clean the cleanup test identity helper used by this validation script.
 * 中文：删除或清理 当前验证脚本使用的“cleanup test identity”辅助操作。
 *
 * @param string $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
 *
 * @return void No value is returned. / 无返回值。
 */
function cleanupTestIdentity(string $uid): void
{
    try {
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT id FROM cdsp_users WHERE external_user_id=?');
        $q->execute([$uid]);
        $id = $q->fetchColumn();
        if (!$id) {
            return;
        }
        $id = (int)$id;
        $pdo->prepare('DELETE FROM cdsp_api_tokens WHERE user_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cdsp_auth_sessions WHERE user_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cdsp_auth_handoffs WHERE user_id=? OR external_user_id=?')->execute([$id, $uid]);
        $pdo->prepare('DELETE FROM cdsp_users WHERE id=?')->execute([$id]);
    } catch (Throwable $e) {
        warnCheck('Could not fully clean test identity ' . $uid . '; remove it manually if necessary.');
    }
}

$options = getopt('', ['base-url:', 'insecure']);
$base = rtrim((string)($options['base-url'] ?? ''), '/');
$insecure = array_key_exists('insecure', $options);
if ($base === '') {
    fwrite(STDERR, "Usage: php scripts/test_api_live_v0_2_06.php --base-url=https://host/sales-posts [--insecure]\n");
    exit(2);
}

$secret = (string)($config['auth']['handoff_secret'] ?? '');
if (strlen($secret) < 32) {
    fwrite(STDERR, "AUTH_HANDOFF_SECRET is missing/too short in the PHP runtime. Test stopped.\n");
    exit(2);
}

$failures = 0;
$warnings = 0;
$pdo = Database::connection();
$stamp = bin2hex(random_bytes(5));
$adminUid = 'api-test-admin-' . $stamp;
$salesUid = 'api-test-sales-' . $stamp;
$graphqlUid = 'api-test-gql-' . $stamp;
$maxSales = (int)$pdo->query("SELECT COALESCE(MAX(sales_id),100000) FROM cdsp_users WHERE sales_id IS NOT NULL")->fetchColumn();
$salesId = (string)max(100001, min(4000000000, $maxSales + 1000 + random_int(1, 999)));

$adminToken = '';
$salesToken = '';
$graphqlToken = '';

try {
    echo "CoolerDepot Sales Post Tracker v" . $config['app']['version'] . " live API/security test\n";
    echo "Target: $base\n\n";

    $health = requestHttp('GET', $base . '/api/v1/health', null, [], $insecure);
    expect($health['status'] === 200 && ($health['json']['ok'] ?? false) === true, 'REST health endpoint returns 200/ready.');
    expect(($health['json']['app_version'] ?? '') === (string)$config['app']['version'], 'REST health reports the current application version.');
    expect(strtolower(headerValue($health, 'X-Content-Type-Options')) === 'nosniff', 'API sends X-Content-Type-Options: nosniff.');
    expect(headerValue($health, 'Set-Cookie') === '', 'Stateless API request does not create a browser session cookie.');

    // Sensitive project paths must never be served as static files.
    foreach (['/.env', '/database/schema.sql', '/docs/API.md', '/scripts/migrate_v0_2_05_api.php'] as $path) {
        $r = requestHttp('GET', $base . $path, null, [], $insecure);
        expect(in_array($r['status'], [403, 404], true), 'Sensitive path blocked: ' . $path);
    }

    // Wrong media type must fail before JSON parsing/business work.
    $r = requestHttp('POST', $base . '/api/v1/auth/exchange', null, ['Content-Type: text/plain'], $insecure);
    expect($r['status'] === 415 && (($r['json']['error'] ?? '') === 'unsupported_media_type'), 'REST exchange rejects non-JSON Content-Type with 415.');

    $bad = signedPayload($adminUid, '', 'API Test Admin', 'admin', time(), 'bad-sig-' . $stamp);
    $bad['sig'] = str_repeat('0', 64);
    $r = requestHttp('POST', $base . '/api/v1/auth/exchange', $bad, [], $insecure);
    expect($r['status'] === 401, 'Invalid HMAC signature is rejected.');

    $expired = signedPayload($adminUid, '', 'API Test Admin', 'admin', time() - max(300, ((int)$config['auth']['handoff_max_age_seconds'] + 60)), 'expired-' . $stamp);
    $r = requestHttp('POST', $base . '/api/v1/auth/exchange', $expired, [], $insecure);
    expect($r['status'] === 401, 'Expired signed exchange is rejected.');

    $adminPayload = signedPayload($adminUid, '', 'API Test Admin', 'admin', time(), 'admin-ok-' . $stamp);
    $r = requestHttp('POST', $base . '/api/v1/auth/exchange', $adminPayload, [], $insecure);
    $adminToken = (string)($r['json']['access_token'] ?? '');
    expect($r['status'] === 200 && str_starts_with($adminToken, 'cdsp_at_'), 'REST signed exchange issues an Admin Bearer token.');
    expect(($r['json']['user']['role'] ?? '') === 'admin', 'REST exchange preserves Admin role.');
    expect(!str_contains($r['raw'], $secret), 'REST response does not expose AUTH_HANDOFF_SECRET.');

    $replay = requestHttp('POST', $base . '/api/v1/auth/exchange', $adminPayload, [], $insecure);
    expect($replay['status'] === 401, 'Signed nonce replay is rejected.');

    if ($adminToken !== '') {
        $hash = hash('sha256', $adminToken);
        $q = $pdo->prepare('SELECT token_hash FROM cdsp_api_tokens WHERE token_hash=? LIMIT 1');
        $q->execute([$hash]);
        expect((string)$q->fetchColumn() === $hash, 'Database stores SHA-256 Bearer-token hash.');
        $cols = $pdo->query('SHOW COLUMNS FROM cdsp_api_tokens')->fetchAll();
        $fieldNames = array_map(static fn($row) => strtolower((string)$row['Field']), $cols);
        expect(!in_array('access_token', $fieldNames, true) && !in_array('token', $fieldNames, true), 'API token table has no raw-token column.');

        $auth = ['Authorization: Bearer ' . $adminToken];
        $me = requestHttp('GET', $base . '/api/v1/auth/me', null, $auth, $insecure);
        expect($me['status'] === 200 && ($me['json']['user']['role'] ?? '') === 'admin', 'Admin Bearer token works on /auth/me.');
        $users = requestHttp('GET', $base . '/api/v1/admin/users', null, $auth, $insecure);
        expect($users['status'] === 200 && is_array($users['json']['users'] ?? null), 'Admin Bearer token can access Admin REST endpoint.');
        $wrongRole = requestHttp('GET', $base . '/api/v1/sales/profile', null, $auth, $insecure);
        expect($wrongRole['status'] === 403, 'Admin token cannot call Sales-only REST endpoint.');
    }

    $salesPayload = signedPayload($salesUid, $salesId, 'API Test Sales', 'sales', time(), 'sales-ok-' . $stamp);
    $r = requestHttp('POST', $base . '/api/v1/auth/exchange', $salesPayload, [], $insecure);
    $salesToken = (string)($r['json']['access_token'] ?? '');
    expect($r['status'] === 200 && str_starts_with($salesToken, 'cdsp_at_'), 'REST signed exchange issues a Sales Bearer token.');
    expect(($r['json']['user']['salesId'] ?? null) === (int)$salesId, 'REST exchange preserves Sales ID.');

    if ($salesToken !== '') {
        $salesAuth = ['Authorization: Bearer ' . $salesToken];
        $profile = requestHttp('GET', $base . '/api/v1/sales/profile', null, $salesAuth, $insecure);
        expect($profile['status'] === 200 && ($profile['json']['user']['role'] ?? '') === 'sales', 'Sales Bearer token can access Sales REST endpoint.');
        $forbidden = requestHttp('GET', $base . '/api/v1/admin/users', null, $salesAuth, $insecure);
        expect($forbidden['status'] === 403, 'Sales Bearer token is denied Admin REST endpoint.');
    }

    $invalidBearer = requestHttp('GET', $base . '/api/v1/auth/me', null, ['Authorization: Bearer cdsp_at_invalid'], $insecure);
    expect($invalidBearer['status'] === 401, 'Invalid Bearer token is rejected.');

    // GraphQL public contract and RBAC.
    $gqlPublic = requestHttp('POST', $base . '/graphql', ['query' => 'query { apiVersion appVersion }'], [], $insecure);
    expect($gqlPublic['status'] === 200 && ($gqlPublic['json']['data']['apiVersion'] ?? '') === 'v1', 'GraphQL public version query works.');
    expect(($gqlPublic['json']['data']['appVersion'] ?? '') === (string)$config['app']['version'], 'GraphQL appVersion reports the current application version.');

    $gqlNoAuth = requestHttp('POST', $base . '/graphql', ['query' => 'query { me { role } }'], [], $insecure);
    $noAuthCode = $gqlNoAuth['json']['errors'][0]['extensions']['code'] ?? '';
    expect($gqlNoAuth['status'] === 200 && $noAuthCode === 'bearer_token_required', 'GraphQL protected field requires Bearer token.');

    if ($salesToken !== '') {
        $salesAuth = ['Authorization: Bearer ' . $salesToken];
        $g = requestHttp('POST', $base . '/graphql', ['query' => 'query { salesProfile { role salesId displayName } }'], $salesAuth, $insecure);
        expect(($g['json']['data']['salesProfile']['role'] ?? '') === 'sales', 'Sales Bearer token works on GraphQL Sales field.');
        $g = requestHttp('POST', $base . '/graphql', ['query' => 'query { adminUsers { id role } }'], $salesAuth, $insecure);
        $code = $g['json']['errors'][0]['extensions']['code'] ?? '';
        expect($code === 'forbidden_role', 'Sales Bearer token is denied GraphQL Admin field.');
    }

    if ($adminToken !== '') {
        $adminAuth = ['Authorization: Bearer ' . $adminToken];
        $g = requestHttp('POST', $base . '/graphql', ['query' => 'query { adminUsers { id role displayName } }'], $adminAuth, $insecure);
        expect(is_array($g['json']['data']['adminUsers'] ?? null), 'Admin Bearer token can access GraphQL Admin field.');
    }

    // Complexity limits should reject abusive documents before resolver work.
    $deep = 'query { me { ';
    for ($i = 0; $i < 12; $i++) {
        $deep .= 'x { ';
    }
    $deep .= 'role';
    for ($i = 0; $i < 12; $i++) {
        $deep .= ' }';
    }
    $deep .= ' } }';
    $g = requestHttp('POST', $base . '/graphql', ['query' => $deep], [], $insecure);
    expect($g['status'] === 400 && (($g['json']['errors'][0]['extensions']['code'] ?? '') === 'graphql_complexity_limit'), 'GraphQL depth limit rejects abusive nesting.');

    $manyFields = 'query { ';
    for ($i = 0; $i < 60; $i++) {
        $manyFields .= 'f' . $i . ': apiVersion ';
    }
    $manyFields .= '}';
    $g = requestHttp('POST', $base . '/graphql', ['query' => $manyFields], [], $insecure);
    expect($g['status'] === 400 && (($g['json']['errors'][0]['extensions']['code'] ?? '') === 'graphql_complexity_limit'), 'GraphQL field-count limit rejects oversized selections.');

    // GraphQL signed exchange should issue a token accepted by REST, proving one shared API auth layer.
    $gqlPayload = signedPayload($graphqlUid, '', 'API Test GraphQL Admin', 'admin', time(), 'gql-ok-' . $stamp);
    $input = [
        'uid' => $gqlPayload['uid'],
        'salesId' => '',
        'name' => $gqlPayload['name'],
        'role' => $gqlPayload['role'],
        'ts' => $gqlPayload['ts'],
        'nonce' => $gqlPayload['nonce'],
        'sig' => $gqlPayload['sig'],
    ];
    $g = requestHttp('POST', $base . '/graphql', [
        'query' => 'mutation Exchange($input: AuthHandoffInput!) { authExchange(input: $input) { accessToken tokenType user { role } } }',
        'variables' => ['input' => $input],
    ], [], $insecure);
    $graphqlToken = (string)($g['json']['data']['authExchange']['accessToken'] ?? '');
    expect(str_starts_with($graphqlToken, 'cdsp_at_'), 'GraphQL authExchange issues a Bearer token.');
    if ($graphqlToken !== '') {
        $gqlAuth = ['Authorization: Bearer ' . $graphqlToken];
        $me = requestHttp('GET', $base . '/api/v1/auth/me', null, $gqlAuth, $insecure);
        expect($me['status'] === 200 && ($me['json']['user']['role'] ?? '') === 'admin', 'GraphQL-issued Bearer token works on REST /auth/me.');
        $logout = requestHttp('POST', $base . '/graphql', ['query' => 'mutation { logout }'], $gqlAuth, $insecure);
        expect(($logout['json']['data']['logout'] ?? false) === true, 'GraphQL logout revokes current Bearer token.');
        $after = requestHttp('GET', $base . '/api/v1/auth/me', null, $gqlAuth, $insecure);
        expect($after['status'] === 401, 'GraphQL-revoked token is rejected by REST.');
    }

    if ($salesToken !== '') {
        $salesAuth = ['Authorization: Bearer ' . $salesToken];
        $logout = requestHttp('POST', $base . '/api/v1/auth/logout', [], $salesAuth, $insecure);
        expect($logout['status'] === 200 && ($logout['json']['ok'] ?? false) === true, 'REST logout succeeds.');
        $after = requestHttp('GET', $base . '/api/v1/auth/me', null, $salesAuth, $insecure);
        expect($after['status'] === 401, 'REST-revoked token cannot be reused.');
    }

    // Method protection.
    $wrongMethod = requestHttp('GET', $base . '/graphql', null, [], $insecure);
    expect($wrongMethod['status'] === 405, 'GraphQL endpoint rejects GET with 405.');

    // CORS policy: an unlisted hostile origin must not receive a successful preflight unless wildcard was explicitly configured.
    $cors = requestHttp('OPTIONS', $base . '/graphql', null, [
        'Origin: https://evil.invalid',
        'Access-Control-Request-Method: POST',
        'Access-Control-Request-Headers: Authorization, Content-Type',
    ], $insecure);
    $allowedOrigins = $config['api']['allowed_origins'] ?? [];
    if (in_array('*', $allowedOrigins, true)) {
        expect($cors['status'] === 204 && headerValue($cors, 'Access-Control-Allow-Origin') === '*', 'Wildcard CORS behaves as explicitly configured.');
        warnCheck('API_ALLOWED_ORIGINS contains *; exact production origins are safer for authentication APIs.');
    } else {
        expect($cors['status'] === 403, 'Unlisted hostile Origin is denied at CORS preflight.');
    }

} catch (Throwable $e) {
    fail('Test runner exception: ' . $e->getMessage());
} finally {
    foreach ([$adminUid, $salesUid, $graphqlUid] as $uid) {
        cleanupTestIdentity($uid);
    }
}

echo "\nResult: " . ($failures === 0 ? 'PASS' : 'FAIL') . " | failures=$failures warnings=$warnings\n";
if ($failures === 0) {
    echo "All live REST/GraphQL smoke/security checks passed. This is not a substitute for an independent penetration test.\n";
}
exit($failures === 0 ? 0 : 1);
