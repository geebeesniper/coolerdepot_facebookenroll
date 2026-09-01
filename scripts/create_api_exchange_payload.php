<?php
/**
 * File / 文件：scripts/create_api_exchange_payload.php
 * EN: CLI maintenance/deployment script for create api exchange payload.
 * 中文：用于 create api exchange payload 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExternalAuthService;

/**
 * EN: Perform the api exchange payload helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“api exchange payload”辅助操作。
 *
 * @param string $role Required or assigned application role. / 要求或分配的应用角色。
 * @param string $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
 * @param string $name Display or logical name associated with the operation. / 与本操作关联的显示名称或逻辑名称。
 * @param string $salesId Numeric Sales ID associated with the sales user or record. / 与 Sales 用户或记录关联的数字 Sales ID。
 * @param string $secret Whether the value must be handled as encrypted secret data. / 是否将该值作为加密敏感数据处理。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function apiExchangePayload(string $role, string $uid, string $name, string $salesId, string $secret): array
{
    $payload = [
        'uid' => $uid,
        'sales_id' => $role === 'sales' ? $salesId : '',
        'name' => $name,
        'role' => $role,
        'ts' => time(),
        'nonce' => bin2hex(random_bytes(16)),
    ];
    $payload['sig'] = hash_hmac('sha256', ExternalAuthService::canonicalPayload($payload), $secret);
    return $payload;
}

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/create_api_exchange_payload.php <admin|sales> <external_uid> <display_name> [sales_id]\n");
    exit(1);
}
$role = (string)$argv[1];
$uid = (string)$argv[2];
$name = (string)$argv[3];
$salesId = $role === 'sales' ? (string)($argv[4] ?? '') : '';
if (!in_array($role, ['admin', 'sales'], true) || ($role === 'sales' && !ctype_digit($salesId))) {
    fwrite(STDERR, "Invalid role or sales_id.\n");
    exit(1);
}
$secret = (string)($config['auth']['handoff_secret'] ?? '');
if (strlen($secret) < 32) {
    fwrite(STDERR, "AUTH_HANDOFF_SECRET is not configured.\n");
    exit(1);
}
echo json_encode(apiExchangePayload($role, $uid, $name, $salesId, $secret), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
