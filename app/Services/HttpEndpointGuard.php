<?php
/**
 * File / 文件：app/Services/HttpEndpointGuard.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class HttpEndpointGuard
{
    /**
     * EN: Checks or validates the condition represented by `assertPublicHttps` (assert Public Https).
     * 中文：检查或校验 `assertPublicHttps`（assert Public Https）所表示的条件。
     */
    public static function assertPublicHttps(string $url): string
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('API endpoint must be a valid URL.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new \RuntimeException('Custom API endpoints must use HTTPS.');
        }

        if ($host === ''
            || $host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new \RuntimeException('Private/internal API hosts are not allowed.');
        }

        $ips = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (!$resolved) {
                throw new \RuntimeException('API host could not be resolved.');
            }
            $ips = $resolved;
        }

        foreach ($ips as $ip) {
            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                throw new \RuntimeException(
                    'API endpoint resolves to a private/reserved network.'
                );
            }
        }

        return $url;
    }

    /**
     * EN: Implements the application operation `optionalWebsite` (optional Website).
     * 中文：实现应用操作 `optionalWebsite`（optional Website）。
     */
    public static function optionalWebsite(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new \RuntimeException('Website link must be a valid HTTPS URL.');
        }

        return $url;
    }
}
