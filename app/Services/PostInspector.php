<?php
/**
 * File / 文件：app/Services/PostInspector.php
 * EN: Defines the PostInspector service used by application business, security, or provider integration flows.
 * 中文：定义 PostInspector 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use DOMDocument;
use DOMXPath;
use DateTime;
use DateTimeZone;
use App\Models\Post;

/**
 * EN: Application service that encapsulates post inspector business, security, or integration behavior.
 * 中文：封装 post inspector 业务、安全或外部集成行为的应用服务。
 */
class PostInspector
{
    /**
     * Fetch the newest content for an already-saved post without running
     * duplicate/save validation against that same post. This is used only by
     * the explicit Admin Refresh Content action.
     */
    /**
     * EN: Perform the refresh existing content operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“refresh existing content”操作。
     *
     * @param int $actorUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function refreshExistingContent(
        int $actorUserId,
        string $platform,
        string $url
    ): array {
        $platform = strtolower(trim($platform));
        $url = trim($url);

        if (!in_array($platform, ['facebook','offerup','craigslist'], true)) {
            throw new \DomainException('Refresh Content is not available for this platform.');
        }

        if (!PlatformUrl::allowed($url, $platform)) {
            throw new \DomainException('The saved source URL is not valid for this platform.');
        }

        if ($platform === 'facebook') {
            // Force a live provider request and bypass the normal provider cache.
            return (new FacebookMarketplaceProviderChain())->fetch(
                $url,
                $actorUserId,
                true,
                true
            );
        }

        $f = (new SafeFetcher())->fetch($url, $platform);
        $html = (string)($f['html'] ?? '');
        $meta = $this->meta($html);

        if ($platform === 'craigslist') {
            $meta = array_merge(
                $meta,
                array_filter(
                    $this->craigslist($html),
                    static fn($value) => $value !== null && $value !== ''
                )
            );
        }

        if (empty($meta['published_at'])) {
            $meta['published_at'] = $this->embeddedDate($platform, $html)
                ?: $this->relativeDate($html);
        }

        $canonical = trim((string)($meta['canonical_url'] ?? ''));
        if ($canonical === '' || !PlatformUrl::allowed($canonical, $platform)) {
            $canonical = trim((string)($f['resolved_url'] ?? $url));
        }

        $images = array_values(array_filter(
            array_map('trim', (array)($meta['images'] ?? [])),
            static fn($value) => $value !== ''
        ));
        $raw = $meta;
        $raw['photos'] = array_map(
            static fn(string $imageUrl): array => ['url' => $imageUrl],
            $images
        );

        return [
            'provider' => 'direct_fetch',
            'provider_name' => ucfirst($platform) . ' live page',
            'title' => trim((string)($meta['title'] ?? '')),
            'description' => trim((string)($meta['description'] ?? '')),
            'published_raw' => trim((string)($meta['published_at'] ?? '')),
            'canonical_url' => $canonical,
            'external_post_id' => PlatformUrl::externalId(
                $platform,
                $canonical,
                $html
            ),
            'raw' => $raw,
        ];
    }

    /**
     * EN: Execute the inspect operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“inspect”操作。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $submitted Submitted value used by this operation. / 本操作使用的“submitted”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Execute the inspect facebook operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“inspect facebook”操作。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $submitted Submitted value used by this operation. / 本操作使用的“submitted”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Check or validate the validate and finish operation implemented by post inspector.
     * 中文：检查或验证 post inspector 实现的“validate and finish”操作。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $submitted Submitted value used by this operation. / 本操作使用的“submitted”参数值。
     * @param string $resolved Resolved value used by this operation. / 本操作使用的“resolved”参数值。
     * @param string $canonical Canonical value used by this operation. / 本操作使用的“canonical”参数值。
     * @param ?string $eid Identifier of the e record or entity. / e 记录或实体的标识 ID。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param string $desc Desc value used by this operation. / 本操作使用的“desc”参数值。
     * @param string $publishedRaw Published raw value used by this operation. / 本操作使用的“published raw”参数值。
     * @param array $meta Meta value used by this operation. / 本操作使用的“meta”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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
            \App\Core\Logger::exception($e, 'post-inspector', ['event' => 'Duplicate comparison failed'], 'error');
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

    /**
     * EN: Perform the meta operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“meta”操作。
     *
     * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the craigslist operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“craigslist”操作。
     *
     * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the embedded date operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“embedded date”操作。
     *
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
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

    /**
     * EN: Perform the relative date operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“relative date”操作。
     *
     * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
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

    /**
     * EN: Perform the date operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“date”操作。
     *
     * @param string $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
     * @param string $tz Tz value used by this operation. / 本操作使用的“tz”参数值。
     *
     * @return ?DateTime ?DateTime result produced by this operation. / 本操作生成的 ?DateTime 类型结果。
     */
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

    /**
     * EN: Perform the fail operation implemented by post inspector.
     * 中文：执行 post inspector 实现的“fail”操作。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     * @param string $s S value used by this operation. / 本操作使用的“s”参数值。
     * @param string $code Code value used by this operation. / 本操作使用的“code”参数值。
     * @param string $msg Msg value used by this operation. / 本操作使用的“msg”参数值。
     * @param ?string $resolved Resolved value used by this operation. / 本操作使用的“resolved”参数值。
     * @param ?string $canonical Canonical value used by this operation. / 本操作使用的“canonical”参数值。
     * @param ?string $eid Identifier of the e record or entity. / e 记录或实体的标识 ID。
     * @param array $meta Meta value used by this operation. / 本操作使用的“meta”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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
        \App\Core\Logger::warning(
            'Post inspection failed.',
            [
                'event' => 'post_inspection_failed',
                'sales_user_id' => $uid,
                'platform' => $p,
                'failure_code' => $code,
                'failure_message' => $msg,
                'submitted_url' => $s,
                'resolved_url' => $resolved,
                'external_post_id' => $eid,
            ],
            'post-inspector'
        );

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
