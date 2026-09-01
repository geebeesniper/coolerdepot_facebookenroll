<?php
/**
 * File / 文件：integration/parent_api_example.php
 * EN: Parent-system integration example for parent api example.
 * 中文：用于 parent api example 的父系统集成示例。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */

/**
 * EN: Perform the sales post tracker signed identity integration helper used by the parent-system example.
 * 中文：执行 父系统集成示例使用的“sales post tracker signed identity”辅助操作。
 *
 * @param array $user User value used by this operation. / 本操作使用的“user”参数值。
 * @param string $secret Whether the value must be handled as encrypted secret data. / 是否将该值作为加密敏感数据处理。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function salesPostTrackerSignedIdentity(array $user, string $secret): array
{
    $role = (string)$user['role'];
    $payload = [
        'uid' => (string)$user['id'],
        'sales_id' => $role === 'sales' ? (string)$user['sales_id'] : '',
        'name' => (string)$user['display_name'],
        'role' => $role,
        'ts' => time(),
        'nonce' => bin2hex(random_bytes(16)),
    ];
    $canonical = implode("\n", [
        $payload['uid'],
        $payload['sales_id'],
        $payload['name'],
        $payload['role'],
        (string)$payload['ts'],
        $payload['nonce'],
    ]);
    $payload['sig'] = hash_hmac('sha256', $canonical, $secret);
    return $payload;
}

// REST: POST the returned array as JSON to /api/v1/auth/exchange.
// GraphQL: map sales_id -> salesId inside AuthHandoffInput and POST to /graphql.
