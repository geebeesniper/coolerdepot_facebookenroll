<?php
/**
 * File / 文件：app/Services/SecretCrypto.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class SecretCrypto
{
    /**
     * EN: Implements the application operation `key` (key).
     * 中文：实现应用操作 `key`（key）。
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
     * EN: Implements the application operation `encrypt` (encrypt).
     * 中文：实现应用操作 `encrypt`（encrypt）。
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
     * EN: Implements the application operation `decrypt` (decrypt).
     * 中文：实现应用操作 `decrypt`（decrypt）。
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
