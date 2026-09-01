<?php
/**
 * File / 文件：app/Services/HttpEndpointGuard.php
 * EN: Defines the HttpEndpointGuard service used by application business, security, or provider integration flows.
 * 中文：定义 HttpEndpointGuard 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Application service that encapsulates http endpoint guard business, security, or integration behavior.
 * 中文：封装 http endpoint guard 业务、安全或外部集成行为的应用服务。
 */
class HttpEndpointGuard
{
    /**
     * EN: Check or validate the assert public https operation.
     * 中文：检查或验证“assert public https”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the optional website operation.
     * 中文：执行“optional website”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
