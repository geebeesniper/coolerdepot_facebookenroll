<?php
/**
 * File / 文件：app/Services/FacebookListingMetadata.php
 * EN: Normalizes Facebook Marketplace provider metadata into strict internal date fields and detects explicit listing-unavailable states.
 * 中文：把 Facebook Marketplace Provider 元数据标准化为严格的内部日期字段，并识别明确的帖子不可用状态。
 */
namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class FacebookListingMetadata
{
    /** @var string[] */
    private const DATE_KEYS = [
        'published_at','publishedAt','published_date','publishedDate',
        'listing_date','listingDate','date_posted','datePosted','posted_at','postedAt',
        'creation_time','creationTime','created_at','createdAt','date_created','dateCreated',
        'creation_timestamp','creationTimestamp','time_posted','timePosted',
        'listed_at','listedAt','listed_date','listedDate',
    ];

    /** @var string[] */
    private const STATUS_KEYS = [
        'listing_status','listingStatus','availability','availability_status',
        'availabilityStatus','item_status','itemStatus','marketplace_status','marketplaceStatus',
    ];

    /** @var string[] */
    private const MESSAGE_KEYS = [
        'message','error','detail','reason','status_text','statusText','availability_text','availabilityText',
    ];

    /**
     * EN: Normalize one provider item. `published_raw` remains only for diagnostics;
     * downstream verification should use `published_at` / `published_date`.
     * 中文：标准化 Provider Item。`published_raw` 仅保留用于排错；下游验证应使用 `published_at` / `published_date`。
     */
    public static function normalizeItem(array $item): array
    {
        $rawRecord = is_array($item['raw'] ?? null) ? $item['raw'] : [];
        $timezone = date_default_timezone_get() ?: 'UTC';
        $anchor = self::parseAnchor($item['fetched_at'] ?? null);

        $publishedRaw = self::scalar($item['published_raw'] ?? null);
        if ($publishedRaw === '') {
            $publishedRaw = self::extractPublishedRaw($rawRecord);
        }

        $normalized = self::normalizePublished($publishedRaw, $anchor, $timezone);

        // Preserve already-normalized strict provider fields when present and valid.
        $existingDate = self::validDate(self::scalar($item['published_date'] ?? null));
        $existingAt = self::normalizeAbsolute(self::scalar($item['published_at'] ?? null), $timezone);
        if ($existingAt !== null) {
            $normalized['published_at'] = $existingAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $normalized['published_date'] = $existingAt->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            $normalized['published_source'] = self::scalar($item['published_source'] ?? null) ?: 'provider_standard';
        } elseif ($existingDate !== null) {
            $normalized['published_date'] = $existingDate;
            $normalized['published_source'] = self::scalar($item['published_source'] ?? null) ?: 'provider_date';
        }

        $item['published_raw'] = $normalized['published_raw'] !== '' ? $normalized['published_raw'] : null;
        $item['published_at'] = $normalized['published_at'];
        $item['published_date'] = $normalized['published_date'];
        $item['published_source'] = $normalized['published_source'];
        $item['fetched_at'] = $normalized['fetched_at'];

        return $item;
    }

    /**
     * EN: A provider response is usable when it identifies the requested listing and returns any real listing evidence.
     * Date/title completeness belongs to PostInspector, not to provider transport success.
     * 中文：Provider 结果只要能识别目标帖子并返回真实帖子证据即可视为 Provider 成功；日期/标题完整性由 PostInspector 判断。
     */
    public static function providerUsable(array $item): bool
    {
        if (trim((string)($item['external_post_id'] ?? '')) === '') {
            return false;
        }

        foreach (['title','description','published_raw','published_at','published_date'] as $key) {
            if (trim((string)($item[$key] ?? '')) !== '') {
                return true;
            }
        }

        if (self::containsImage($item['raw'] ?? null) || self::containsImage($item['photos'] ?? null) || self::containsImage($item['images'] ?? null)) {
            return true;
        }

        return false;
    }

    /**
     * EN: Convert provider date output into strict internal fields.
     * `published_at` is ISO-8601 UTC (`YYYY-MM-DDTHH:MM:SSZ`) when exact time is known.
     * `published_date` is always `YYYY-MM-DD` when the calendar date can be determined.
     * 中文：把 Provider 日期转换为严格内部格式；能确定时间时 `published_at` 为 UTC ISO-8601，能确定日期时 `published_date` 为 YYYY-MM-DD。
     *
     * @return array{published_raw:string,published_at:?string,published_date:?string,published_source:?string,fetched_at:string}
     */
    public static function normalizePublished(string $raw, ?DateTimeImmutable $fetchedAt = null, ?string $timezone = null): array
    {
        $timezone = $timezone ?: (date_default_timezone_get() ?: 'UTC');
        $localTz = new DateTimeZone($timezone);
        $utc = new DateTimeZone('UTC');
        $fetchedAt = ($fetchedAt ?: new DateTimeImmutable('now', $utc))->setTimezone($utc);
        $raw = trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        $result = [
            'published_raw' => $raw,
            'published_at' => null,
            'published_date' => null,
            'published_source' => null,
            'fetched_at' => $fetchedAt->format('Y-m-d\TH:i:s\Z'),
        ];

        if ($raw === '') {
            return $result;
        }

        // Unix seconds / milliseconds.
        if (preg_match('/^\d{10,13}$/', $raw)) {
            $number = (int)$raw;
            $seconds = strlen($raw) >= 13 ? intdiv($number, 1000) : $number;
            if ($seconds >= 946684800 && $seconds <= 4133980799) { // 2000-01-01 through 2100-12-31.
                $dt = (new DateTimeImmutable('@' . $seconds))->setTimezone($utc);
                $result['published_at'] = $dt->format('Y-m-d\TH:i:s\Z');
                $result['published_date'] = $dt->setTimezone($localTz)->format('Y-m-d');
                $result['published_source'] = strlen($raw) >= 13 ? 'unix_milliseconds' : 'unix_seconds';
                return $result;
            }
        }

        // Facebook relative text: "Listed 59 minutes ago in Fresno, TX" etc.
        if (preg_match('/\b(?:Listed|Posted)\s+(?:about\s+)?(\d+|a|an)\s+(minute|hour|day|week)s?\s+ago\b/i', $raw, $m)) {
            $qty = in_array(strtolower($m[1]), ['a','an'], true) ? 1 : (int)$m[1];
            $unit = strtolower($m[2]);
            $seconds = match ($unit) {
                'minute' => 60,
                'hour' => 3600,
                'day' => 86400,
                'week' => 604800,
                default => 0,
            };
            if ($qty > 0 && $seconds > 0) {
                $dt = $fetchedAt->modify('-' . ($qty * $seconds) . ' seconds');
                $result['published_at'] = $dt->format('Y-m-d\TH:i:s\Z');
                $result['published_date'] = $dt->setTimezone($localTz)->format('Y-m-d');
                $result['published_source'] = 'facebook_relative';
                return $result;
            }
        }

        if (preg_match('/\b(?:Listed|Posted)\s+(just now|today)\b/i', $raw)) {
            $result['published_date'] = $fetchedAt->setTimezone($localTz)->format('Y-m-d');
            $result['published_source'] = 'facebook_today';
            return $result;
        }

        if (preg_match('/\b(?:Listed|Posted)\s+yesterday\b/i', $raw)) {
            $result['published_date'] = $fetchedAt->setTimezone($localTz)->modify('-1 day')->format('Y-m-d');
            $result['published_source'] = 'facebook_yesterday';
            return $result;
        }

        if (preg_match('/\b(?:Listed|Posted)\s+on\s+([A-Za-z]{3,9})\s+(\d{1,2})(?:,?\s+(\d{4}))?\b/i', $raw, $m)) {
            $year = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : (int)$fetchedAt->setTimezone($localTz)->format('Y');
            $candidate = self::parseMonthDay($m[1], (int)$m[2], $year, $localTz);
            if ($candidate !== null && !isset($m[3])) {
                // A yearless date that would be in the future relative to fetch time belongs to the previous year.
                if ($candidate->format('Y-m-d') > $fetchedAt->setTimezone($localTz)->format('Y-m-d')) {
                    $candidate = self::parseMonthDay($m[1], (int)$m[2], $year - 1, $localTz);
                }
            }
            if ($candidate !== null) {
                $result['published_date'] = $candidate->format('Y-m-d');
                $result['published_source'] = 'facebook_listed_on';
                return $result;
            }
        }

        // Strict date-only provider values.
        $dateOnly = self::validDate($raw);
        if ($dateOnly !== null) {
            $result['published_date'] = $dateOnly;
            $result['published_source'] = 'date_only';
            return $result;
        }

        // Absolute date/time values. Require visible date or ISO-like timestamp evidence;
        // do not feed arbitrary listing text to DateTime's permissive parser.
        if (preg_match('/(?:\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}[-\/]\d{1,2}[-\/]\d{4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4})/', $raw)) {
            $dt = self::normalizeAbsolute($raw, $timezone);
            if ($dt !== null) {
                $result['published_at'] = $dt->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
                $result['published_date'] = $dt->setTimezone($localTz)->format('Y-m-d');
                $result['published_source'] = 'absolute_datetime';
                return $result;
            }
        }

        return $result;
    }

    /**
     * EN: Search common provider fields and Facebook display text for the raw posting-time expression.
     * 中文：从常见 Provider 字段及 Facebook 显示文本中查找原始发布时间表达。
     */
    public static function extractPublishedRaw(array $record): string
    {
        $found = self::findByKeys($record, self::DATE_KEYS, 0);
        if ($found !== '') {
            return $found;
        }

        $strings = [];
        self::collectStrings($record, $strings, 0, 300);
        foreach ($strings as $text) {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if (preg_match('/\b(?:Listed|Posted)\s+(?:(?:about\s+)?(?:\d+|a|an)\s+(?:minute|hour|day|week)s?\s+ago|just now|today|yesterday|on\s+[A-Za-z]{3,9}\s+\d{1,2}(?:,?\s+\d{4})?)(?:\s+in\s+[^|•]+)?/i', $text, $m)) {
                return trim($m[0]);
            }
        }

        return '';
    }

    /**
     * EN: Return a human-readable reason only when provider data explicitly says the listing is unavailable/removed/not found.
     * Generic timeouts, login walls and provider failures are intentionally not classified as unavailable.
     * 中文：只有 Provider 数据明确表示帖子不可用/删除/不存在时才返回原因；Timeout、登录墙、Provider 故障不归类为 Unavailable。
     */
    public static function unavailableReason($payload, ?int $httpStatus = null, ?string $fallbackMessage = null): ?string
    {
        if (is_array($payload)) {
            $bool = self::explicitUnavailableBoolean($payload, 0);
            if ($bool !== null) {
                return $bool;
            }

            // Generic top-level status/state can describe the listing response itself.
            // Nested generic status values may belong to seller/profile objects, so only
            // listing-specific status keys are searched recursively.
            $topStatus = self::scalar($payload['status'] ?? $payload['state'] ?? null);
            if ($topStatus !== '' && self::statusMeansUnavailable($topStatus)) {
                return self::cleanReason($topStatus);
            }
            $status = self::findByKeys($payload, self::STATUS_KEYS, 0);
            if ($status !== '' && self::statusMeansUnavailable($status)) {
                return self::cleanReason($status);
            }

            $message = self::findByKeys($payload, self::MESSAGE_KEYS, 0);
            if ($message !== '' && self::textMeansUnavailable($message)) {
                return self::cleanReason($message);
            }
        }

        $fallbackMessage = trim((string)$fallbackMessage);
        if ($fallbackMessage !== '' && self::textMeansUnavailable($fallbackMessage)) {
            return self::cleanReason($fallbackMessage);
        }

        // 404/410 alone can also mean a provider endpoint error. Only classify it when the body/message mentions the listing/item.
        if (in_array((int)$httpStatus, [404, 410], true)) {
            $text = is_scalar($payload) ? trim((string)$payload) : $fallbackMessage;
            if ($text !== '' && preg_match('/\b(listing|item|marketplace|post)\b/i', $text) && self::textMeansUnavailable($text)) {
                return self::cleanReason($text);
            }
        }

        return null;
    }

    private static function parseAnchor($value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $raw = self::scalar($value);
        if ($raw !== '') {
            try {
                return (new DateTimeImmutable($raw, $utc))->setTimezone($utc);
            } catch (\Throwable $e) {
                // Fall through to current time.
            }
        }
        return new DateTimeImmutable('now', $utc);
    }

    private static function normalizeAbsolute(string $raw, string $timezone): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone($timezone));
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) {
                return null;
            }
            return $dt;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function validDate(string $raw): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dt || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }
        return $dt->format('Y-m-d') === $raw ? $raw : null;
    }

    private static function parseMonthDay(string $month, int $day, int $year, DateTimeZone $tz): ?DateTimeImmutable
    {
        foreach (['!F j Y','!M j Y'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $month . ' ' . $day . ' ' . $year, $tz);
            $errors = DateTimeImmutable::getLastErrors();
            if ($dt && !(is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
                return $dt;
            }
        }
        return null;
    }

    private static function scalar($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
    }

    private static function findByKeys(array $data, array $keys, int $depth): string
    {
        if ($depth > 6) {
            return '';
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = self::scalar($data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::findByKeys($value, $keys, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private static function collectStrings($value, array &$out, int $depth, int $limit): void
    {
        if (count($out) >= $limit || $depth > 7) {
            return;
        }
        if (is_string($value)) {
            if (trim($value) !== '') {
                $out[] = $value;
            }
            return;
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $child) {
            self::collectStrings($child, $out, $depth + 1, $limit);
            if (count($out) >= $limit) {
                return;
            }
        }
    }

    private static function explicitUnavailableBoolean(array $data, int $depth): ?string
    {
        if ($depth > 6) {
            return null;
        }
        if ($depth === 0) {
            foreach (['is_available','isAvailable','available','listing_available','listingAvailable'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] === false) {
                    return $key . '=false';
                }
            }
        }
        foreach (['is_removed','isRemoved','removed','is_deleted','isDeleted','deleted','not_found','notFound'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === true) {
                return $key . '=true';
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $reason = self::explicitUnavailableBoolean($value, $depth + 1);
                if ($reason !== null) {
                    return $reason;
                }
            }
        }
        return null;
    }

    private static function statusMeansUnavailable(string $status): bool
    {
        $value = strtolower(trim(preg_replace('/[_-]+/', ' ', $status) ?? $status));
        return in_array($value, [
            'unavailable','removed','deleted','not found','expired','inactive','archived','no longer available',
        ], true);
    }

    private static function textMeansUnavailable(string $text): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? ''));
        foreach ([
            'this listing is no longer available',
            'listing is no longer available',
            'listing unavailable',
            'marketplace listing unavailable',
            "this item isn't available",
            'this item is not available',
            'item is no longer available',
            'listing has been removed',
            'item has been removed',
            'listing was removed',
            'item was removed',
            'listing has been deleted',
            'item has been deleted',
            'listing not found',
            'item not found',
            'marketplace item not found',
            "this content isn't available right now",
            'this content is not available right now',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function cleanReason(string $reason): string
    {
        $reason = trim(preg_replace('/\s+/u', ' ', strip_tags($reason)) ?? '');
        return $reason !== '' ? substr($reason, 0, 300) : 'Facebook Marketplace listing is unavailable.';
    }

    private static function containsImage($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $key => $child) {
            $key = strtolower((string)$key);
            if (in_array($key, [
                'image','images','photo','photos','listingphotos','listing_photos',
                'image_url','imageurl','thumbnail','thumbnail_url','thumbnailurl','photo_url','photourl'
            ], true) && self::containsHttpsValue($child)) {
                return true;
            }
            if (is_array($child) && self::containsImage($child)) {
                return true;
            }
        }
        return false;
    }

    private static function containsHttpsValue($value): bool
    {
        if (is_string($value)) {
            return preg_match('/^https:\/\//i', trim($value)) === 1;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $child) {
            if (self::containsHttpsValue($child)) {
                return true;
            }
        }
        return false;
    }
}
