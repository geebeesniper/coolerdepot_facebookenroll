<?php
/**
 * File / 文件：app/Services/PlatformUrl.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class PlatformUrl
{
    /**
     * EN: Implements the application operation `platformFor` (platform For).
     * 中文：实现应用操作 `platformFor`（platform For）。
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
     * EN: Builds, formats, or transforms data for `normalize` (normalize).
     * 中文：为 `normalize`（normalize）构建、格式化或转换数据。
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
     * EN: Checks or validates the condition represented by `allowed` (allowed).
     * 中文：检查或校验 `allowed`（allowed）所表示的条件。
     */
    public static function allowed(string $url, ?string $expected = null): bool
    {
        return self::normalize($url, $expected) !== null;
    }

    /**
     * EN: Checks or validates the condition represented by `allowedStrict` (allowed Strict).
     * 中文：检查或校验 `allowedStrict`（allowed Strict）所表示的条件。
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
     * EN: Implements the application operation `platformForStrict` (platform For Strict).
     * 中文：实现应用操作 `platformForStrict`（platform For Strict）。
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
     * EN: Implements the application operation `externalId` (external Id).
     * 中文：实现应用操作 `externalId`（external Id）。
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
