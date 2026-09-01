<?php
/**
 * File / 文件：app/Services/SecretCrypto.php
 * EN: Defines the SecretCrypto service used by application business, security, or provider integration flows.
 * 中文：定义 SecretCrypto 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Application service that encapsulates secret crypto business, security, or integration behavior.
 * 中文：封装 secret crypto 业务、安全或外部集成行为的应用服务。
 */
class SecretCrypto
{
    /**
     * EN: Perform the key operation.
     * 中文：执行“key”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private static function key(): string
    {
        global $config;

        $secret = (string)($config['auth']['handoff_secret'] ?? '');

        if (strlen($secret) < 32) {
            throw new \RuntimeException(
                'Server encryption secret is not configured. AUTH_HANDOFF_SECRET must be at least 32 characters.'
            );
        }

        return hash('sha256', 'cdsp-settings-v1|' . $secret, true);
    }

    /**
     * EN: Encrypt a sensitive value for authenticated storage using the application secret.
     * 中文：使用应用密钥对敏感值进行认证加密后保存。
     *
     * @param string $plaintext Plaintext value used by this operation. / 本操作使用的“plaintext”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'cdsp-settings-v1'
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Could not encrypt secret setting.');
        }

        return base64_encode("CDSP1" . $iv . $tag . $ciphertext);
    }

    /**
     * EN: Decrypt and authenticate a previously encrypted application secret value.
     * 中文：解密并验证此前加密保存的应用敏感值。
     *
     * @param string $encoded Encoded value used by this operation. / 本操作使用的“encoded”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < 5 + 12 + 16 || substr($raw, 0, 5) !== 'CDSP1') {
            throw new \RuntimeException('Encrypted setting format is invalid.');
        }

        $iv = substr($raw, 5, 12);
        $tag = substr($raw, 17, 16);
        $ciphertext = substr($raw, 33);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'cdsp-settings-v1'
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Could not decrypt secret setting.');
        }

        return $plaintext;
    }
}
