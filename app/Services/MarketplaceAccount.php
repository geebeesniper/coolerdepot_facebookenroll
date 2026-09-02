<?php
/**
 * File / 文件：app/Services/MarketplaceAccount.php
 * EN: Extracts a marketplace seller/account identity only from structured provider/API data.
 * 中文：仅从结构化 Provider/API 数据中提取 Marketplace 卖家/账号身份。
 */
namespace App\Services;

/**
 * EN: Normalizes public marketplace account metadata returned by external providers.
 * 中文：标准化外部 Provider 返回的公开 Marketplace 账号元数据。
 */
class MarketplaceAccount
{
    /**
     * Best-effort account extraction for live inspection paths.
     * Account metadata is optional enrichment: malformed/unexpected Provider data
     * must never make the underlying listing verification fail.
     *
     * @param mixed $result Provider/API result.
     * @param array<string,mixed> $context Diagnostic context without secrets.
     */
    public static function safeFromProviderResult(
        string $platform,
        $result,
        array $context = []
    ): ?array {
        if (!is_array($result)) {
            return null;
        }

        try {
            return self::fromProviderResult($platform, $result);
        } catch (\Throwable $error) {
            \App\Core\Logger::exception(
                $error,
                'marketplace-account',
                array_merge([
                    'event' => 'Optional marketplace account extraction failed; listing verification continues',
                    'platform' => strtolower(trim($platform)),
                ], $context),
                'warning'
            );
            return null;
        }
    }

    /**
     * Extract a stable account identity from a normalized provider result.
     * Direct HTML fetches are intentionally not passed here: the account rule is
     * enabled only when a provider/API actually returns account information.
     */
    public static function fromProviderResult(string $platform, array $result): ?array
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['facebook', 'offerup', 'craigslist'], true)) {
            return null;
        }

        $candidates = [];
        $visited = 0;
        self::collectCandidates($result, $candidates, 0, $visited);
        if (is_array($result['raw'] ?? null) && count($candidates) < 40 && $visited < 500) {
            self::collectCandidates($result['raw'], $candidates, 0, $visited);
        }

        // Top-level seller fields are common in Apify/Bright Data actors.
        $top = self::topLevelCandidate($result);
        if ($top !== null) {
            array_unshift($candidates, $top);
        }
        if (is_array($result['raw'] ?? null)) {
            $rawTop = self::topLevelCandidate($result['raw']);
            if ($rawTop !== null) {
                array_unshift($candidates, $rawTop);
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeCandidate($candidate);
            if ($normalized === null) {
                continue;
            }

            $identity = '';
            $identityStrength = 'name';
            if ($normalized['id'] !== null) {
                $identity = 'id:' . self::lower($normalized['id']);
                $identityStrength = 'id';
            } elseif ($normalized['url'] !== null) {
                $identity = 'url:' . self::normalizeProfileUrl($normalized['url']);
                $identityStrength = 'url';
            }

            // A provider may expose only a seller display name. Show that name on
            // the Post, but do NOT use it to prove two posts came from the same
            // account: display names are not guaranteed unique or stable.
            if ($identity === '' && $normalized['name'] === null) {
                continue;
            }

            return [
                'id' => $normalized['id'],
                'name' => $normalized['name'],
                'url' => $normalized['url'],
                'key_hash' => $identity !== ''
                    ? hash('sha256', $platform . "\n" . $identity)
                    : null,
                'identity_strength' => $identityStrength,
                'source' => 'provider_api',
            ];
        }

        return null;
    }

    /** @param array<int,mixed> $out */
    private static function collectCandidates(
        $value,
        array &$out,
        int $depth,
        int &$visited
    ): void {
        if (!is_array($value) || $depth > 4 || $visited >= 500 || count($out) >= 40) {
            return;
        }
        $visited++;

        $sellerKeys = [
            'seller', 'seller_info', 'sellerInfo', 'seller_data', 'sellerData',
            'marketplace_listing_seller', 'marketplaceListingSeller',
            'owner', 'owner_info', 'ownerInfo',
        ];

        foreach ($value as $key => $child) {
            $key=(string)$key;
            $isSellerContainer=in_array($key,$sellerKeys,true)
                || ($depth<=1 && in_array($key,['user','account'],true));
            if ($isSellerContainer) {
                if (is_scalar($child) && trim((string)$child) !== '') {
                    $out[] = ['name' => trim((string)$child)];
                } elseif (is_array($child)) {
                    $out[] = $child;
                }
            }

            if (is_array($child) && $visited < 500 && count($out) < 40) {
                self::collectCandidates($child, $out, $depth + 1, $visited);
            }
        }
    }

    private static function topLevelCandidate(array $data): ?array
    {
        $candidate = [
            'id' => self::firstScalar($data, [
                'seller_id', 'sellerId', 'sellerID',
                'seller_user_id', 'sellerUserId',
                'owner_id', 'ownerId', 'user_id', 'userId',
                'profile_id', 'profileId',
            ]),
            'name' => self::firstScalar($data, [
                'seller_name', 'sellerName', 'seller_username', 'sellerUsername',
                'owner_name', 'ownerName', 'display_name', 'displayName',
                'username', 'userName',
            ]),
            'url' => self::firstScalar($data, [
                'seller_profile_url', 'sellerProfileUrl',
                'seller_url', 'sellerUrl',
                'profile_url', 'profileUrl', 'profile_link', 'profileLink',
            ]),
        ];

        return ($candidate['id'] || $candidate['name'] || $candidate['url'])
            ? $candidate
            : null;
    }

    private static function normalizeCandidate(array $candidate): ?array
    {
        $id = self::firstScalar($candidate, [
            'seller_id', 'sellerId', 'id', 'user_id', 'userId',
            'profile_id', 'profileId', 'facebook_id', 'facebookId', 'uuid',
        ]);
        $name = self::firstScalar($candidate, [
            'seller_name', 'sellerName', 'name', 'display_name', 'displayName',
            'username', 'user_name', 'userName', 'full_name', 'fullName',
            'nickname', 'handle',
        ]);
        $url = self::firstScalar($candidate, [
            'seller_profile_url', 'sellerProfileUrl', 'profile_url', 'profileUrl',
            'seller_url', 'sellerUrl', 'url', 'link', 'profile_link', 'profileLink',
            'facebookUrl',
        ]);

        // Some providers wrap the account one more level inside actor/profile.
        foreach (['actor', 'profile', 'public_profile', 'publicProfile'] as $nestedKey) {
            if (!is_array($candidate[$nestedKey] ?? null)) {
                continue;
            }
            $nested = self::normalizeCandidate($candidate[$nestedKey]);
            if ($nested !== null) {
                $id = $id ?: $nested['id'];
                $name = $name ?: $nested['name'];
                $url = $url ?: $nested['url'];
            }
        }

        $id = self::clean($id, 191);
        $name = self::clean($name, 255);
        $url = self::cleanUrl($url);

        if ($id === null && $name === null && $url === null) {
            return null;
        }

        return ['id' => $id, 'name' => $name, 'url' => $url];
    }

    private static function firstScalar(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data) || !is_scalar($data[$key])) {
                continue;
            }
            $value = trim((string)$data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    private static function clean(?string $value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return self::substr($value, $maxLength);
    }

    private static function cleanUrl(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        return self::substr($value, 2000);
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private static function substr(string $value, int $maxLength): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : substr($value, 0, $maxLength);
    }

    private static function normalizeProfileUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return self::lower(rtrim($url, '/'));
        }
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = rtrim((string)($parts['path'] ?? ''), '/');
        return $scheme . '://' . $host . $path;
    }
}
