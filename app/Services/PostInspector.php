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
            $item = (new FacebookMarketplaceProviderChain())->fetch(
                $url,
                $actorUserId,
                true,
                true
            );
            $account = MarketplaceAccount::safeFromProviderResult('facebook', $item, ['operation' => 'refresh_existing_content']);
            if ($account !== null) {
                $item['platform_account'] = $account;
            }
            return $item;
        }

        try {
            $fetch = (new SafeFetcher())->fetch($url, $platform);
            return $this->normalizeDirectMarketplacePage($platform, $url, $fetch);
        } catch (\Throwable $error) {
            if (
                in_array($platform, ['craigslist', 'offerup'], true)
                && $this->remoteHttpStatus($error) === 403
            ) {
                try {
                    $provider = (new BlockedMarketplaceProviderChain())->fetch(
                        $platform,
                        $url,
                        $actorUserId,
                        true
                    );
                    return $this->normalizeBlockedProviderResult($platform, $url, $provider);
                } catch (\Throwable $providerError) {
                    \App\Core\Logger::exception(
                        $providerError,
                        'post-inspector',
                        [
                            'event' => 'Admin refresh blocked-marketplace provider fallback failed',
                            'platform' => $platform,
                            'source_url' => $url,
                        ],
                        'warning'
                    );
                    throw new \DomainException(
                        ucfirst($platform)
                        . ' blocked the server request (HTTP 403), and automatic provider fallback failed: '
                        . $providerError->getMessage()
                    );
                }
            }
            throw $error;
        }
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
            $fetch = (new SafeFetcher())->fetch($submitted, $platform);
            $listing = $this->normalizeDirectMarketplacePage($platform, $submitted, $fetch);
        } catch (\Throwable $error) {
            // EN: Both Craigslist and OfferUp can return HTTP 403 to datacenter IPs.
            // Try the Provider Registry automatically before asking Sales for manual data.
            // 中文：Craigslist 与 OfferUp 都可能对数据中心 IP 返回 HTTP 403。
            // 在要求 Sales 手动填写之前，先自动尝试 Provider Registry 回退链。
            if (
                in_array($platform, ['craigslist', 'offerup'], true)
                && $this->remoteHttpStatus($error) === 403
            ) {
                try {
                    $provider = (new BlockedMarketplaceProviderChain())->fetch(
                        $platform,
                        $submitted,
                        $uid
                    );
                    $listing = $this->normalizeBlockedProviderResult(
                        $platform,
                        $submitted,
                        $provider
                    );
                } catch (\Throwable $providerError) {
                    \App\Core\Logger::exception(
                        $providerError,
                        'post-inspector',
                        [
                            'event' => 'Blocked marketplace provider fallback failed',
                            'platform' => $platform,
                            'submitted_url' => $submitted,
                        ],
                        'warning'
                    );
                    return $this->blockedMarketplaceManualRequired(
                        $uid,
                        $platform,
                        $submitted,
                        403,
                        $error->getMessage(),
                        $providerError->getMessage()
                    );
                }
            } else {
                return $this->fail(
                    $uid,
                    $platform,
                    $submitted,
                    'FETCH_FAILED',
                    $error->getMessage()
                );
            }
        }

        return $this->validateAndFinish(
            $uid,
            $platform,
            $submitted,
            (string)($listing['resolved_url'] ?? $submitted),
            (string)($listing['canonical_url'] ?? $submitted),
            ($listing['external_post_id'] ?? null) ?: null,
            trim((string)($listing['title'] ?? '')),
            trim((string)($listing['description'] ?? '')),
            trim((string)($listing['published_raw'] ?? '')),
            is_array($listing['raw'] ?? null) ? $listing['raw'] : []
        );
    }

    /**
     * EN: Normalize a successfully fetched Craigslist or OfferUp HTML page.
     * 中文：标准化成功直接抓取的 Craigslist 或 OfferUp HTML 页面。
     *
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $submitted Original submitted URL. / 原始提交 URL。
     * @param array $fetch SafeFetcher response. / SafeFetcher 返回结果。
     *
     * @return array Common listing metadata. / 统一帖子元数据。
     */
    private function normalizeDirectMarketplacePage(
        string $platform,
        string $submitted,
        array $fetch
    ): array {
        $html = (string)($fetch['html'] ?? '');
        $meta = $this->meta($html);

        // EN: Marketplace-specific extraction catches Craigslist imgList/gallery
        // URLs and OfferUp JSON-LD/__NEXT_DATA__/serialized image URLs that are
        // not always exposed through og:image.
        // 中文：Marketplace 专用解析补充 Craigslist imgList/gallery，以及
        // OfferUp JSON-LD/__NEXT_DATA__/序列化状态中的图片 URL，避免只依赖 og:image。
        // EN: Verification keeps only the first listing image across every Marketplace platform.
        // 中文：所有 Marketplace 验证统一只保留第一张帖子图片，其余图片不参与验证。
        $meta['images'] = array_slice(array_values(array_unique(array_merge(
            (array)($meta['images'] ?? []),
            MarketplaceImageExtractor::fromHtml($platform, $html)
        ))), 0, 1);

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

        $resolved = trim((string)($fetch['resolved_url'] ?? $submitted));
        $canonical = trim((string)($meta['canonical_url'] ?? ''));
        if ($canonical === '' || !PlatformUrl::allowed($canonical, $platform)) {
            $canonical = $resolved;
        }
        if (!PlatformUrl::allowed($canonical, $platform)) {
            $canonical = $submitted;
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
        $raw['verification_source'] = 'direct_fetch';

        return [
            'provider' => 'direct_fetch',
            'provider_name' => ucfirst($platform) . ' live page',
            'resolved_url' => $resolved,
            'canonical_url' => $canonical,
            'external_post_id' => PlatformUrl::externalId($platform, $canonical, $html),
            'title' => trim((string)($meta['title'] ?? '')),
            'description' => trim((string)($meta['description'] ?? '')),
            'published_raw' => trim((string)($meta['published_at'] ?? '')),
            'raw' => $raw,
        ];
    }

    /**
     * EN: Normalize Bright Data Web Unlocker or Apify data returned after a direct HTTP 403.
     * 中文：标准化直接请求 HTTP 403 后由 Bright Data Web Unlocker 或 Apify 返回的数据。
     *
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $submitted Original submitted URL. / 原始提交 URL。
     * @param array $provider Provider fallback response. / Provider 回退结果。
     *
     * @return array Common listing metadata. / 统一帖子元数据。
     */
    private function normalizeBlockedProviderResult(
        string $platform,
        string $submitted,
        array $provider
    ): array {
        $providerName = trim((string)(
            $provider['_provider_profile_name']
            ?? $provider['provider_name']
            ?? $provider['provider']
            ?? 'Provider fallback'
        ));

        if (trim((string)($provider['html'] ?? '')) !== '') {
            $listing = $this->normalizeDirectMarketplacePage(
                $platform,
                $submitted,
                [
                    'html' => (string)$provider['html'],
                    'resolved_url' => (string)($provider['resolved_url'] ?? $submitted),
                ]
            );
            $listing['provider'] = (string)($provider['provider'] ?? 'provider_html');
            $listing['provider_name'] = $providerName;
            $listing['raw']['provider_fallback'] = [
                'provider' => $listing['provider'],
                'provider_name' => $providerName,
                'profile_id' => $provider['_provider_profile_id'] ?? null,
                'reason' => 'direct_http_403',
                'used' => true,
            ];
            // The provider may return an HTML body plus structured seller/user
            // fields beside that body. Preserve that API account metadata so the
            // final duplicate check uses platform + external account scope.
            $platformAccount = MarketplaceAccount::safeFromProviderResult(
                $platform,
                $provider,
                ['operation' => 'blocked_provider_html_fallback']
            );
            if ($platformAccount !== null) {
                $listing['platform_account'] = $platformAccount;
                $listing['raw']['platform_account'] = $platformAccount;
            }
            return $listing;
        }

        $resolved = trim((string)($provider['resolved_url'] ?? $submitted));
        $canonical = trim((string)($provider['canonical_url'] ?? $resolved));
        if (!PlatformUrl::allowed($canonical, $platform)) {
            $canonical = PlatformUrl::normalize($submitted, $platform) ?: $submitted;
        }

        $raw = is_array($provider['raw'] ?? null) ? $provider['raw'] : [];
        $providerImages = array_slice(array_values(array_unique(array_merge(
            ImageFingerprint::urls($provider),
            ImageFingerprint::urls($raw)
        ))), 0, 1);
        if ($providerImages) {
            $raw['photos'] = [
                ['url' => $providerImages[0]],
            ];
        } else {
            $raw['photos'] = [];
        }
        $raw['images'] = $providerImages;
        $raw['provider_fallback'] = [
            'provider' => (string)($provider['provider'] ?? 'provider_structured'),
            'provider_name' => $providerName,
            'profile_id' => $provider['_provider_profile_id'] ?? null,
            'reason' => 'direct_http_403',
            'used' => true,
        ];
        $platformAccount = MarketplaceAccount::safeFromProviderResult($platform, $provider, ['operation' => 'blocked_provider_fallback']);
        if ($platformAccount !== null) {
            $raw['platform_account'] = $platformAccount;
        }

        $externalId = trim((string)($provider['external_post_id'] ?? ''));
        if ($externalId === '') {
            $externalId = (string)(PlatformUrl::externalId($platform, $canonical) ?? '');
        }

        return [
            'provider' => (string)($provider['provider'] ?? 'provider_structured'),
            'provider_name' => $providerName,
            'resolved_url' => $resolved,
            'canonical_url' => $canonical,
            'external_post_id' => $externalId !== '' ? $externalId : null,
            'title' => trim((string)($provider['title'] ?? '')),
            'description' => trim((string)($provider['description'] ?? '')),
            'published_raw' => trim((string)($provider['published_raw'] ?? '')),
            'platform_account' => $platformAccount,
            'raw' => $raw,
        ];
    }

    /**
     * EN: Finalize Craigslist or OfferUp manual details after direct HTTP 403 and automatic provider fallback failure.
     * 中文：Craigslist 或 OfferUp 直接 HTTP 403 且自动 Provider 回退失败后，使用 Sales 手动信息完成检查。
     *
     * @param int $uid External user identifier. / 外部用户 ID。
     * @param array $source Existing manual-required Inspection. / 现有待手动确认 Inspection。
     * @param string $title Listing title. / 帖子标题。
     * @param string $description Optional description. / 可选描述。
     * @param string $publishedDate Published date. / 发布日期。
     *
     * @return array Structured inspection result. / 结构化 Inspection 结果。
     */
    public function finalizeMarketplaceManual(
        int $uid,
        array $source,
        string $title,
        string $description,
        string $publishedDate
    ): array {
        $platform = strtolower(trim((string)($source['platform'] ?? '')));
        $label = $platform === 'offerup' ? 'OfferUp' : 'Craigslist';
        $expectedFailure = $platform === 'offerup'
            ? 'OFFERUP_REMOTE_BLOCKED'
            : 'CRAIGSLIST_REMOTE_BLOCKED';

        if (
            (int)($source['sales_user_id'] ?? 0) !== $uid
            || !in_array($platform, ['craigslist', 'offerup'], true)
            || (string)($source['verification_status'] ?? '') !== 'manual_pending'
            || (string)($source['failure_code'] ?? '') !== $expectedFailure
        ) {
            throw new \DomainException(
                'This manual marketplace verification request is no longer valid. Check the post again.'
            );
        }

        $title = trim($title);
        $description = trim($description);
        $publishedDate = trim($publishedDate);
        if ($title === '') {
            throw new \DomainException('Enter the ' . $label . ' listing title.');
        }
        if (mb_strlen($title) > 500) {
            throw new \DomainException('The ' . $label . ' listing title is too long.');
        }
        if ($publishedDate === '') {
            throw new \DomainException('Enter the ' . $label . ' published date.');
        }

        $submitted = trim((string)($source['submitted_url'] ?? ''));
        $resolved = trim((string)($source['resolved_url'] ?? '')) ?: $submitted;
        $canonical = trim((string)($source['canonical_url'] ?? '')) ?: $submitted;
        if (!PlatformUrl::allowed($canonical, $platform)) {
            $canonical = $submitted;
        }

        $eid = trim((string)($source['external_post_id'] ?? ''));
        if ($eid === '') {
            $eid = (string)(PlatformUrl::externalId($platform, $canonical) ?? '');
        }

        $sourceMeta = json_decode((string)($source['raw_meta_json'] ?? '{}'), true);
        if (!is_array($sourceMeta)) {
            $sourceMeta = [];
        }
        $meta = array_merge($sourceMeta, [
            'title' => $title,
            'description' => $description,
            'published_at' => $publishedDate,
            'manual_verification_required' => true,
            'manual_verification' => [
                'platform' => $platform,
                'reason' => 'direct_http_403_provider_fallback_failed',
                'confirmed_by_sales_user_id' => $uid,
                'confirmed_at' => date('Y-m-d H:i:s'),
            ],
        ]);

        $result = $this->validateAndFinish(
            $uid,
            $platform,
            $submitted,
            $resolved,
            $canonical,
            $eid !== '' ? $eid : null,
            $title,
            $description,
            $publishedDate,
            $meta
        );
        if (($result['verification_status'] ?? '') !== 'verified') {
            return $result;
        }

        $result['verification_status'] = 'manual_pending';
        $result['failure_code'] = null;
        $result['failure_message'] = null;
        $result['raw_meta']['manual_verification_required'] = true;
        $result['raw_meta']['manual_verification']['status'] = 'pending_admin_review';

        \App\Core\Logger::info(
            $label . ' manual verification completed and is pending Admin review.',
            [
                'event' => 'marketplace_manual_verification_completed',
                'platform' => $platform,
                'sales_user_id' => $uid,
                'submitted_url' => $submitted,
                'external_post_id' => $eid !== '' ? $eid : null,
                'published_date' => $result['published_date'] ?? null,
            ],
            'post-inspector'
        );
        return $result;
    }

    /**
     * EN: Backward-compatible V0.2.13 Craigslist wrapper.
     * 中文：V0.2.13 Craigslist 手动验证兼容包装方法。
     *
     * @param int $uid External user identifier. / 外部用户 ID。
     * @param array $source Existing manual-required Inspection. / 现有待手动确认 Inspection。
     * @param string $title Listing title. / 帖子标题。
     * @param string $description Optional description. / 可选描述。
     * @param string $publishedDate Published date. / 发布日期。
     *
     * @return array Structured inspection result. / 结构化 Inspection 结果。
     */
    public function finalizeCraigslistManual(
        int $uid,
        array $source,
        string $title,
        string $description,
        string $publishedDate
    ): array {
        return $this->finalizeMarketplaceManual(
            $uid,
            $source,
            $title,
            $description,
            $publishedDate
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

        $platformAccount = MarketplaceAccount::safeFromProviderResult('facebook', $item, ['operation' => 'verify_listing']);

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
            'images' => array_slice(ImageFingerprint::urls($item), 0, 1),
        ];
        if ($platformAccount !== null) {
            $raw['platform_account'] = $platformAccount;
        }

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

        $platformAccount = is_array($meta['platform_account'] ?? null)
            ? $meta['platform_account']
            : null;

        if ($dup = Post::duplicate($uid, $platform, $canonical, $eid, $title, $desc, $platformAccount)) {
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
            $report = DuplicateIndex::inspect(
                $uid,
                $platform,
                $title,
                $meta,
                $platformAccount
            );
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
     * EN: Create a manual-required result only after direct HTTP 403 and automatic provider fallback both fail.
     * 中文：仅当直接请求 HTTP 403 且自动 Provider 回退均失败后，创建需要 Sales 手动确认的结果。
     *
     * @param int $uid External user identifier. / 外部用户 ID。
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $submitted Submitted listing URL. / 提交的帖子 URL。
     * @param int $httpStatus Direct HTTP status. / 直接请求 HTTP 状态码。
     * @param string $remoteMessage Direct fetch error. / 直接抓取错误。
     * @param string $providerMessage Provider fallback error. / Provider 回退错误。
     *
     * @return array Structured manual-pending result. / 结构化待手动确认结果。
     */
    private function blockedMarketplaceManualRequired(
        int $uid,
        string $platform,
        string $submitted,
        int $httpStatus,
        string $remoteMessage,
        string $providerMessage = ''
    ): array {
        $platform = strtolower(trim($platform));
        $label = $platform === 'offerup' ? 'OfferUp' : 'Craigslist';
        $failureCode = $platform === 'offerup'
            ? 'OFFERUP_REMOTE_BLOCKED'
            : 'CRAIGSLIST_REMOTE_BLOCKED';
        $eid = PlatformUrl::externalId($platform, $submitted);
        $message = $label . ' blocked direct server verification (HTTP 403), '
            . 'and automatic provider fallback was unavailable. '
            . 'Confirm the listing title and published date to continue for Admin review.';

        \App\Core\Logger::warning(
            $label . ' verification requires manual confirmation after provider fallback.',
            [
                'event' => 'marketplace_manual_verification_required',
                'platform' => $platform,
                'sales_user_id' => $uid,
                'submitted_url' => $submitted,
                'external_post_id' => $eid,
                'remote_http_status' => $httpStatus,
                'remote_error' => $remoteMessage,
                'provider_fallback_error' => $providerMessage,
            ],
            'post-inspector'
        );

        return [
            'sales_user_id' => $uid,
            'platform' => $platform,
            'submitted_url' => $submitted,
            'resolved_url' => $submitted,
            'canonical_url' => $submitted,
            'external_post_id' => $eid,
            'title' => null,
            'description' => null,
            'published_at' => null,
            'published_date' => null,
            'fetched_at' => date('Y-m-d H:i:s'),
            'verification_status' => 'manual_pending',
            'failure_code' => $failureCode,
            'failure_message' => $message,
            'raw_meta' => [
                'manual_verification_required' => true,
                'remote_http_status' => $httpStatus,
                'remote_error' => $remoteMessage,
                'provider_fallback_error' => $providerMessage,
                'verification_source' => $platform . '_direct_then_provider_fallback',
            ],
        ];
    }

    /**
     * EN: Backward-compatible Craigslist helper retained for V0.2.13 contracts.
     * 中文：保留 V0.2.13 Craigslist 兼容辅助方法。
     *
     * @param int $uid External user identifier. / 外部用户 ID。
     * @param string $submitted Submitted Craigslist URL. / Craigslist URL。
     * @param int $httpStatus HTTP status. / HTTP 状态码。
     * @param string $remoteMessage Remote error. / 远程错误。
     *
     * @return array Structured manual-pending result. / 结构化待手动确认结果。
     */
    private function craigslistManualRequired(
        int $uid,
        string $submitted,
        int $httpStatus,
        string $remoteMessage
    ): array {
        return $this->blockedMarketplaceManualRequired(
            $uid,
            'craigslist',
            $submitted,
            $httpStatus,
            $remoteMessage
        );
    }

    /**
     * EN: Extract an HTTP status code from a SafeFetcher exception message.
     * 中文：从 SafeFetcher 异常信息中提取 HTTP 状态码。
     *
     * @param \Throwable $error Fetch exception. / 抓取异常。
     *
     * @return ?int HTTP status when present, otherwise null. / 存在时返回 HTTP 状态码，否则返回 null。
     */
    private function remoteHttpStatus(\Throwable $error): ?int
    {
        if (preg_match('/\\bHTTP\\s+(\\d{3})\\b/i', $error->getMessage(), $match)) {
            return (int)$match[1];
        }

        return null;
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
