<?php
namespace App\Services;

class SecretCrypto
{
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
