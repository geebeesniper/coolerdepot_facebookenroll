<?php
/**
 * File / 文件：app/Services/MarketplaceImageExtractor.php
 * EN: Extracts listing-image URLs from Craigslist/OfferUp HTML and structured payloads.
 * 中文：从 Craigslist/OfferUp HTML 与结构化数据中提取帖子图片 URL。
 * Maintenance / 维护：Keep extraction lightweight; do not execute remote JavaScript or trust arbitrary non-HTTPS URLs.
 * 维护要求：保持轻量解析；不得执行远程 JavaScript，也不得信任任意非 HTTPS URL。
 */
namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * EN: Marketplace-specific image URL extraction and normalization helpers.
 * 中文：Marketplace 专用图片 URL 提取与规范化辅助服务。
 */
class MarketplaceImageExtractor
{
    /**
     * EN: Extract only the first likely listing photo from already-fetched HTML.
     * 中文：从已抓取 HTML 中只提取第一张可能的帖子图片。
     *
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $html HTML source already obtained by direct fetch or provider. / Direct/Provider 已取得的 HTML 源码。
     *
     * @return array Ordered HTTPS image URLs. / 按优先级排序的 HTTPS 图片 URL。
     */
    public static function fromHtml(string $platform, string $html): array
    {
        $platform = strtolower(trim($platform));
        $urls = [];
        $push = static function (?string $url) use (&$urls, $platform): void {
            if ($url === null) {
                return;
            }
            $url = self::normalizeUrl($platform, $url);
            if ($url === null || isset($urls[$url])) {
                return;
            }
            $urls[$url] = true;
        };

        if (trim($html) === '') {
            return [];
        }

        // JSON-LD / Next.js / embedded application JSON are usually the cleanest
        // sources because seller avatars and UI icons are less likely to be mixed in.
        if (class_exists(DOMDocument::class) && class_exists(DOMXPath::class)) {
            libxml_use_internal_errors(true);
            $doc = new DOMDocument();
            if (@$doc->loadHTML($html)) {
                $xpath = new DOMXPath($doc);

            foreach ($xpath->query("//meta[@property='og:image' or @property='og:image:secure_url' or @name='twitter:image']/@content | //link[@rel='image_src']/@href") ?: [] as $node) {
                $push((string)$node->nodeValue);
            }

            foreach ($xpath->query("//script[@type='application/ld+json' or @type='application/json' or @id='__NEXT_DATA__']") ?: [] as $node) {
                $raw = trim((string)$node->nodeValue);
                if ($raw === '') {
                    continue;
                }
                $json = json_decode($raw, true);
                if (!is_array($json)) {
                    continue;
                }
                foreach (ImageFingerprint::urls($json) as $url) {
                    $push($url);
                }
            }

            // DOM fallback. Keep it after structured sources so the cover/gallery
            // images stay before lower-confidence page chrome images.
            foreach ($xpath->query("//img[@src or @data-src or @data-lazy-src or @srcset] | //source[@srcset]") ?: [] as $node) {
                foreach (['src', 'data-src', 'data-lazy-src'] as $attr) {
                    if ($node->attributes?->getNamedItem($attr)) {
                        $push((string)$node->attributes->getNamedItem($attr)->nodeValue);
                    }
                }
                if ($node->attributes?->getNamedItem('srcset')) {
                    $srcset = (string)$node->attributes->getNamedItem('srcset')->nodeValue;
                    $candidates = preg_split('/\s*,\s*/', $srcset) ?: [];
                    // Highest-density/largest srcset candidates are commonly last.
                    foreach (array_reverse($candidates) as $candidate) {
                        $candidate = trim((string)preg_replace('/\s+\d+(?:\.\d+)?[wx]\s*$/i', '', trim($candidate)));
                        if ($candidate !== '') {
                            $push($candidate);
                            break;
                        }
                    }
                }
            }
            }
            libxml_clear_errors();
        }

        if ($platform === 'craigslist') {
            // Craigslist commonly embeds an imgList JavaScript array and may expose
            // image URLs even when only thumbnails are present in the DOM.
            $decoded = str_replace('\\/', '/', $html);
            if (preg_match_all('~https://images\.craigslist\.org/[^"\'<>\s]+~i', $decoded, $matches)) {
                foreach ($matches[0] as $rawUrl) {
                    $push((string)$rawUrl);
                }
            }
        } elseif ($platform === 'offerup') {
            // Next.js serialized state often escapes slashes. Recover image-like
            // URLs even if the exact hydration tree changes between releases.
            $decoded = str_replace(['\\u0026', '\\/'], ['&', '/'], $html);
            if (preg_match_all('~https://[^"\'<>\\s]+?(?:\\.jpe?g|\\.png|\\.webp)(?:\\?[^"\'<>\\s]*)?~i', $decoded, $matches)) {
                foreach ($matches[0] as $rawUrl) {
                    $push((string)$rawUrl);
                }
            }
        }

        return array_slice(array_keys($urls), 0, 1);
    }

    /**
     * EN: Normalize one image candidate and reject obvious page-chrome/non-listing assets.
     * 中文：规范化单个图片候选，并排除明显的页面 UI/非帖子图片资源。
     *
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $url Candidate image URL. / 候选图片 URL。
     *
     * @return ?string Normalized URL or null when rejected. / 规范化 URL；不接受时返回 null。
     */
    public static function normalizeUrl(string $platform, string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = str_replace('\\/', '/', $url);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if (!str_starts_with($url, 'https://')) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $lower = strtolower($url);
        foreach (['favicon', 'sprite', 'logo', 'avatar', 'profile', 'badge', 'qrcode', 'qr-code', 'app-store', 'google-play'] as $noise) {
            if (str_contains($lower, $noise)) {
                return null;
            }
        }

        if ($platform === 'craigslist') {
            if (!($host === 'images.craigslist.org' || str_ends_with($host, '.craigslist.org'))) {
                return null;
            }
            // Craigslist retains larger versions under the same image id. Prefer
            // the documented/common highest listing size when a thumbnail suffix exists.
            $url = preg_replace(
                '/_(?:50x50c|300x300|600x450|600x450c|1200x900)\\.(jpe?g|webp)(?=\\?|$)/i',
                '_1200x900.$1',
                $url
            ) ?: $url;
        }

        return $url;
    }
}
