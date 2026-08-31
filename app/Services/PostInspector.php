<?php
namespace App\Services;

use DOMDocument;
use DOMXPath;
use DateTime;
use DateTimeZone;
use App\Models\Post;

class PostInspector
{
    public function inspect(int $uid, string $platform, string $submitted): array
    {
        global $config;

        if (!in_array($platform, ['facebook','offerup','craigslist'], true)) {
            return $this->fail($uid, $platform, $submitted, 'PLATFORM_INVALID', 'Unsupported platform.');
        }

        if (!PlatformUrl::allowed($submitted, $platform)) {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'URL_INVALID',
                'URL does not belong to the detected platform.'
            );
        }

        if ($platform === 'facebook') {
            return $this->inspectFacebook($uid, $submitted);
        }

        try {
            $f = (new SafeFetcher())->fetch($submitted, $platform);
        } catch (\Throwable $e) {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'FETCH_FAILED',
                $e->getMessage()
            );
        }

        $html = $f['html'];
        $meta = $this->meta($html);

        if ($platform === 'craigslist') {
            $meta = array_merge(
                $meta,
                array_filter($this->craigslist($html), fn($v) => $v !== null && $v !== '')
            );
        }

        if (!$meta['published_at']) {
            $meta['published_at'] = $this->embeddedDate($platform, $html)
                ?: $this->relativeDate($html);
        }

        $canonical = $meta['canonical_url'] ?: $f['resolved_url'];

        if (!PlatformUrl::allowed($canonical, $platform)) {
            $canonical = $f['resolved_url'];
        }

        $eid = PlatformUrl::externalId($platform, $canonical, $html);
        $title = trim((string)($meta['title'] ?? ''));
        $desc = trim((string)($meta['description'] ?? ''));

        return $this->validateAndFinish(
            $uid,
            $platform,
            $submitted,
            $f['resolved_url'],
            $canonical,
            $eid,
            $title,
            $desc,
            (string)($meta['published_at'] ?? ''),
            $meta
        );
    }

    private function inspectFacebook(int $uid, string $submitted): array
    {
        $eid = PlatformUrl::externalId('facebook', $submitted);

        // Do the cheap duplicate check before consuming a Bright Data query.
        if ($eid) {
            $dup = Post::duplicate($uid, 'facebook', $submitted, $eid, null, null);

            if ($dup) {
                return $this->fail(
                    $uid,
                    'facebook',
                    $submitted,
                    'DUPLICATE',
                    $dup['reason'],
                    $submitted,
                    $submitted,
                    $eid,
                    ['duplicate_match'=>$dup]
                );
            }
        }

        try {
            $item = (new FacebookMarketplaceProviderChain())->fetch(
                $submitted,
                $uid
            );
        } catch (\Throwable $e) {
            return $this->fail(
                $uid,
                'facebook',
                $submitted,
                'FACEBOOK_PROVIDER_FAILED',
                'Facebook verification is temporarily unavailable. Please try again.',
                $submitted,
                $submitted,
                $eid,
                [
                    'provider' => 'facebook_provider_chain',
                    'provider_error' => $e->getMessage(),
                ]
            );
        }

        $canonical = (string)($item['canonical_url'] ?? $submitted);

        if (!PlatformUrl::allowed($canonical, 'facebook')) {
            $canonical = $submitted;
        }

        $eid = trim((string)($item['external_post_id'] ?? $eid ?? ''));

        // Facebook share links are transient aliases and do not contain the Marketplace
        // listing ID. Once the provider resolves the real numeric ID, use the stable
        // Marketplace item URL as the canonical URL. Keep submitted_url unchanged so
        // the exact link entered by Sales is still available for audit/history.
        if ($eid !== '' && ctype_digit($eid)) {
            $canonical = 'https://www.facebook.com/marketplace/item/' . $eid;
        }

        $title = trim((string)($item['title'] ?? ''));
        $desc = trim((string)($item['description'] ?? ''));
        $publishedRaw = trim((string)($item['published_raw'] ?? ''));

        $raw = [
            'provider' => (string)($item['provider'] ?? 'unknown'),
            'provider_job_id' => $item['provider_job_id'] ?? null,
            'provider_cache' => !empty($item['_provider_cache']),
            'provider_chain' => $item['_provider_chain'] ?? [],
            'fallback_used' => !empty($item['_fallback_used']),
            'fallback_level' => $item['_fallback_level'] ?? 0,
            'fallback_reason' => $item['_fallback_reason'] ?? null,
            'provider_profile_id' => $item['_provider_profile_id']
                ?? $item['provider_profile_id']
                ?? null,
            'provider_profile_name' => $item['_provider_profile_name']
                ?? $item['provider_name']
                ?? null,
            'provider_record' => $item['raw'] ?? [],
            'images' => ImageFingerprint::urls($item),
        ];

        if ($publishedRaw === '') {
            return $this->fail(
                $uid,
                'facebook',
                $submitted,
                'DATE_NOT_VERIFIABLE',
                'Facebook returned the listing, but its posting date could not be verified.',
                $canonical,
                $canonical,
                $eid ?: null,
                $raw + [
                    'title' => $title,
                    'description' => $desc,
                ]
            );
        }

        return $this->validateAndFinish(
            $uid,
            'facebook',
            $submitted,
            $canonical,
            $canonical,
            $eid ?: null,
            $title,
            $desc,
            $publishedRaw,
            $raw
        );
    }

    private function validateAndFinish(
        int $uid,
        string $platform,
        string $submitted,
        string $resolved,
        string $canonical,
        ?string $eid,
        string $title,
        string $desc,
        string $publishedRaw,
        array $meta
    ): array {
        global $config;

        if ($title === '') {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'TITLE_NOT_VERIFIABLE',
                'The post title could not be verified.',
                $resolved,
                $canonical,
                $eid,
                $meta
            );
        }

        $dt = $this->date($publishedRaw, $config['app']['timezone']);

        if (!$dt) {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'DATE_NOT_VERIFIABLE',
                'The post date could not be verified.',
                $resolved,
                $canonical,
                $eid,
                $meta + [
                    'title' => $title,
                    'description' => $desc,
                ]
            );
        }

        $tz = new DateTimeZone($config['app']['timezone']);
        $dt->setTimezone($tz);

        $today = (new DateTime('now', $tz))->format('Y-m-d');
        $pd = $dt->format('Y-m-d');

        if ($pd > $today) {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'FUTURE_DATE',
                "Post date is {$pd}; future-dated posts cannot be saved.",
                $resolved,
                $canonical,
                $eid,
                $meta + [
                    'title' => $title,
                    'description' => $desc,
                    'published_at' => $dt->format('Y-m-d H:i:s'),
                    'published_date' => $pd,
                ]
            );
        }

        if ($dup = Post::duplicate($uid, $platform, $canonical, $eid, $title, $desc)) {
            return $this->fail(
                $uid,
                $platform,
                $submitted,
                'DUPLICATE',
                $dup['reason'],
                $resolved,
                $canonical,
                $eid,
                $meta + [
                    'title' => $title,
                    'description' => $desc,
                    'published_at' => $dt->format('Y-m-d H:i:s'),
                    'published_date' => $pd,
                    'duplicate_match' => $dup,
                ]
            );
        }

        try {
            $report = DuplicateIndex::inspect($platform, $title, $meta);
        } catch (\Throwable $e) {
            error_log('[CDSP duplicate comparison] '.$e->getMessage());
            return $this->fail($uid, $platform, $submitted, 'COMPARISON_UNAVAILABLE',
                'Duplicate comparison is unavailable. Ask an administrator to run the v0.1.70 migration, then try again.',
                $resolved, $canonical, $eid, $meta);
        }
        $report['version'] = 1;
        $meta['duplicate_report'] = $report;
        if ($report['blocked']) {
            return $this->fail($uid, $platform, $submitted, 'DUPLICATE_IMAGE', $report['blocked'],
                $resolved, $canonical, $eid, array_merge($meta, [
                    'title'=>$title, 'description'=>$desc,
                    'published_at'=>$dt->format('Y-m-d H:i:s'), 'published_date'=>$pd,
                ]));
        }

        return [
            'sales_user_id' => $uid,
            'platform' => $platform,
            'submitted_url' => $submitted,
            'resolved_url' => $resolved,
            'canonical_url' => $canonical,
            'external_post_id' => $eid,
            'title' => $title,
            'description' => $desc,
            'published_at' => $dt->format('Y-m-d H:i:s'),
            'published_date' => $pd,
            'fetched_at' => date('Y-m-d H:i:s'),
            'verification_status' => 'verified',
            'failure_code' => null,
            'failure_message' => null,
            'raw_meta' => $meta,
        ];
    }

    private function meta(string $html): array
    {
        $r = [
            'title' => null,
            'description' => null,
            'published_at' => null,
            'canonical_url' => null,
            'images' => [],
        ];

        libxml_use_internal_errors(true);
        $d = new DOMDocument();

        if (@$d->loadHTML($html)) {
            $x = new DOMXPath($d);
            $q = [
                'title' => [
                    "//meta[@property='og:title']/@content",
                    "//meta[@name='twitter:title']/@content",
                    "//title/text()"
                ],
                'description' => [
                    "//meta[@property='og:description']/@content",
                    "//meta[@name='description']/@content"
                ],
                'published_at' => [
                    "//meta[@property='article:published_time']/@content",
                    "//meta[@itemprop='datePosted']/@content",
                    "//meta[@itemprop='datePublished']/@content",
                    "//time[@datetime]/@datetime"
                ],
                'canonical_url' => [
                    "//link[@rel='canonical']/@href",
                    "//meta[@property='og:url']/@content"
                ]
            ];

            foreach ($q as $k => $qs) {
                foreach ($qs as $qq) {
                    $n = $x->query($qq);

                    if ($n && $n->length) {
                        $v = trim($n->item(0)->nodeValue);

                        if ($v !== '') {
                            $r[$k] = html_entity_decode(
                                $v,
                                ENT_QUOTES | ENT_HTML5,
                                'UTF-8'
                            );
                            break;
                        }
                    }
                }
            }

            foreach ($x->query("//meta[@property='og:image' or @property='og:image:secure_url' or @name='twitter:image']/@content | //link[@rel='image_src']/@href") ?: [] as $image) {
                $r['images'][] = html_entity_decode(trim($image->nodeValue), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            foreach ($x->query("//script[@type='application/ld+json']") ?: [] as $node) {
                $j = json_decode(trim($node->nodeValue), true);

                if (!is_array($j)) {
                    continue;
                }
                $r['images'] = array_merge($r['images'], ImageFingerprint::urls($j));

                $items = isset($j[0]) ? $j : [$j];

                foreach ($items as $it) {
                    if (!$r['title'] && !empty($it['name']) && is_string($it['name'])) {
                        $r['title'] = $it['name'];
                    }

                    if (!$r['description']
                        && !empty($it['description'])
                        && is_string($it['description'])) {
                        $r['description'] = $it['description'];
                    }

                    if (!$r['published_at']) {
                        foreach (['datePosted','datePublished','dateCreated'] as $k) {
                            if (!empty($it[$k]) && is_string($it[$k])) {
                                $r['published_at'] = $it[$k];
                                break;
                            }
                        }
                    }

                    if (!$r['canonical_url'] && !empty($it['url']) && is_string($it['url'])) {
                        $r['canonical_url'] = $it['url'];
                    }
                }
            }
        }

        libxml_clear_errors();

        return $r;
    }

    private function craigslist(string $html): array
    {
        $r = [];
        libxml_use_internal_errors(true);
        $d = new DOMDocument();

        if (!@$d->loadHTML($html)) {
            return $r;
        }

        $x = new DOMXPath($d);
        $map = [
            'title' => ["//*[@id='titletextonly']/text()"],
            'description' => ["//*[@id='postingbody']"],
            'published_at' => [
                "//time[contains(@class,'date')]/@datetime",
                "//time/@datetime"
            ],
        ];

        foreach ($map as $k => $qs) {
            foreach ($qs as $q) {
                $n = $x->query($q);

                if ($n && $n->length) {
                    $v = trim(preg_replace(
                        '/\s+/u',
                        ' ',
                        $n->item(0)->textContent ?: $n->item(0)->nodeValue
                    ));

                    if ($v !== '') {
                        $r[$k] = $v;
                        break;
                    }
                }
            }
        }

        libxml_clear_errors();

        return $r;
    }

    private function embeddedDate(string $p, string $html): ?string
    {
        $keys = $p === 'offerup'
            ? ['datePosted','datePublished','createdAt','created_at']
            : [];

        foreach ($keys as $k) {
            if (preg_match(
                '~["\']' . preg_quote($k, '~') . '["\']\s*:\s*(?:"([^"]+)"|(\d{9,13}))~i',
                $html,
                $m
            )) {
                $v = $m[1] !== '' ? $m[1] : $m[2];

                if (ctype_digit((string)$v)) {
                    $n = (int)$v;

                    if ($n > 9999999999) {
                        $n = (int)floor($n / 1000);
                    }

                    return '@' . $n;
                }

                return html_entity_decode(
                    $v,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
            }
        }

        return null;
    }

    private function relativeDate(string $html): ?string
    {
        $t = strip_tags(html_entity_decode(
            $html,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (preg_match(
            '/Posted\s+(\d+)\s+(minute|hour|day)s?\s+ago/i',
            $t,
            $m
        )) {
            return '-' . (int)$m[1] . ' ' . strtolower($m[2])
                . ((int)$m[1] === 1 ? '' : 's');
        }

        if (preg_match('/Posted\s+(just now|today)/i', $t)) {
            return 'now';
        }

        return null;
    }

    private function date(string $raw, string $tz): ?DateTime
    {
        if ($raw === '') {
            return null;
        }

        try {
            $z = new DateTimeZone($tz);
            $d = new DateTime($raw, $z);
            $errors = DateTime::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) {return null;}

            if ($raw[0] === '@') {
                $d->setTimezone($z);
            }

            return $d;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fail(
        int $uid,
        string $p,
        string $s,
        string $code,
        string $msg,
        ?string $resolved = null,
        ?string $canonical = null,
        ?string $eid = null,
        array $meta = []
    ): array {
        return [
            'sales_user_id' => $uid,
            'platform' => $p,
            'submitted_url' => $s,
            'resolved_url' => $resolved,
            'canonical_url' => $canonical,
            'external_post_id' => $eid,
            'title' => $meta['title'] ?? null,
            'description' => $meta['description'] ?? null,
            'published_at' => $meta['published_at'] ?? null,
            'published_date' => $meta['published_date'] ?? null,
            'fetched_at' => date('Y-m-d H:i:s'),
            'verification_status' => 'failed',
            'failure_code' => $code,
            'failure_message' => $msg,
            'raw_meta' => $meta,
        ];
    }
}
