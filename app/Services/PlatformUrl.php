<?php
/**
 * File / 文件：app/Services/PlatformUrl.php
 * EN: Defines the PlatformUrl service used by application business, security, or provider integration flows.
 * 中文：定义 PlatformUrl 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Application service that encapsulates platform url business, security, or integration behavior.
 * 中文：封装 platform url 业务、安全或外部集成行为的应用服务。
 */
class PlatformUrl
{
    /**
     * EN: Perform the platform for operation implemented by platform url.
     * 中文：执行 platform url 实现的“platform for”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    public static function platformFor(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Direct marketplace/listing patterns are checked before parse_url so that
        // accidentally duplicated pasted URLs can still be recovered safely.
        if (preg_match('~https?://(?:[a-z0-9-]+\.)?facebook\.com/marketplace/item/\d+~i', $url)) {
            return 'facebook';
        }

        if (preg_match('~https?://(?:[a-z0-9-]+\.)?offerup\.com/item/detail/[a-z0-9-]+~i', $url)) {
            return 'offerup';
        }

        if (preg_match('~https?://(?:[a-z0-9-]+\.)?craigslist\.org/[^\s]+/\d{8,}\.html~i', $url)) {
            return 'craigslist';
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'facebook.com' || substr($host, -13) === '.facebook.com') {
            return 'facebook';
        }

        if ($host === 'offerup.com' || substr($host, -12) === '.offerup.com' || $host === 'offerup.co') {
            return 'offerup';
        }

        if ($host === 'craigslist.org' || substr($host, -15) === '.craigslist.org') {
            return 'craigslist';
        }

        return null;
    }

    /**
     * EN: Normalize or format the normalize operation implemented by platform url.
     * 中文：规范化或格式化 platform url 实现的“normalize”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $expected Expected value used by this operation. / 本操作使用的“expected”参数值。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    public static function normalize(string $url, ?string $expected = null): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '') {
            return null;
        }

        $platform = $expected ?: self::platformFor($url);

        if ($platform === 'facebook') {
            if (preg_match(
                '~https?://(?:[a-z0-9-]+\.)?facebook\.com/marketplace/item/(\d+)~i',
                $url,
                $m
            )) {
                return 'https://www.facebook.com/marketplace/item/' . $m[1];
            }
        }

        if ($platform === 'offerup') {
            if (preg_match(
                '~https?://(?:www\.)?offerup\.com/item/detail/([a-z0-9-]+)~i',
                $url,
                $m
            )) {
                return 'https://offerup.com/item/detail/' . $m[1];
            }
        }

        if ($platform === 'craigslist') {
            if (preg_match(
                '~(https?://(?:[a-z0-9-]+\.)?craigslist\.org/[^\s]*?/\d{8,}\.html)~i',
                $url,
                $m
            )) {
                return $m[1];
            }
        }

        // Share URLs and other valid provider URLs that are not item URLs can pass
        // through unchanged, but malformed concatenated URLs cannot.
        if (self::allowedStrict($url, $platform)) {
            return $url;
        }

        return null;
    }

    /**
     * EN: Retrieve the allowed operation implemented by platform url.
     * 中文：读取 platform url 实现的“allowed”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $expected Expected value used by this operation. / 本操作使用的“expected”参数值。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function allowed(string $url, ?string $expected = null): bool
    {
        return self::normalize($url, $expected) !== null;
    }

    /**
     * EN: Retrieve the allowed strict operation implemented by platform url.
     * 中文：读取 platform url 实现的“allowed strict”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $expected Expected value used by this operation. / 本操作使用的“expected”参数值。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private static function allowedStrict(string $url, ?string $expected = null): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (!in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) {
            return false;
        }

        // Reject a second embedded URL inside the path/query. This catches:
        // https://facebook/.../123https://facebook/.../123
        $withoutScheme = preg_replace('~^https?://~i', '', $url, 1);

        if (preg_match('~https?://~i', (string)$withoutScheme)) {
            return false;
        }

        $platform = self::platformForStrict($url);

        return $platform && (!$expected || $platform === $expected);
    }

    /**
     * EN: Perform the platform for strict operation implemented by platform url.
     * 中文：执行 platform url 实现的“platform for strict”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    private static function platformForStrict(string $url): ?string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'facebook.com' || substr($host, -13) === '.facebook.com') {
            return 'facebook';
        }

        if ($host === 'offerup.com' || substr($host, -12) === '.offerup.com' || $host === 'offerup.co') {
            return 'offerup';
        }

        if ($host === 'craigslist.org' || substr($host, -15) === '.craigslist.org') {
            return 'craigslist';
        }

        return null;
    }

    /**
     * EN: Perform the external id operation implemented by platform url.
     * 中文：执行 platform url 实现的“external id”操作。
     *
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    public static function externalId(string $platform, string $url, string $html = ''): ?string
    {
        $mappings = [
            'facebook' => [
                '~facebook\.com/marketplace/item/(\d+)~i',
                '~"listing_id":"?(\d+)"?~i'
            ],
            'offerup' => [
                '~/item/detail/([a-z0-9-]+)~i',
                '~"itemId":"([^"]+)"~i'
            ],
            'craigslist' => [
                '~/(\d{8,})\.html~i',
                '~posting id:\s*(\d+)~i'
            ]
        ];

        foreach ($mappings[$platform] ?? [] as $rx) {
            if (preg_match($rx, $url . "\n" . $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
