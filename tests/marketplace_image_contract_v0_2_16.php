<?php
/**
 * V0.2.16 Craigslist/OfferUp image extraction contract test.
 * V0.2.16 Craigslist/OfferUp 图片解析契约测试。
 */
$root = dirname(__DIR__);
require_once $root . '/app/Services/ImageFingerprint.php';
require_once $root . '/app/Services/MarketplaceImageExtractor.php';

use App\Services\ImageFingerprint;
use App\Services\MarketplaceImageExtractor;

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $relative) use ($root): string {
    $text = @file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException('Could not read ' . $relative);
    }
    return $text;
};

$version = trim($read('VERSION'));
$expect(version_compare($version, '0.2.16', '>='), 'VERSION must be 0.2.16 or newer.');

$craigslistHtml = <<<'HTML'
<html><head><meta property="og:image" content="https://images.craigslist.org/a_300x300.jpg"></head>
<body><script>var imgList = [{"url":"https://images.craigslist.org/b_600x450.jpg"},{"url":"https://images.craigslist.org/c_50x50c.jpg"}];</script></body></html>
HTML;
$craigslistImages = MarketplaceImageExtractor::fromHtml('craigslist', $craigslistHtml);
$expect(in_array('https://images.craigslist.org/a_1200x900.jpg', $craigslistImages, true), 'Craigslist og:image should upgrade to 1200x900.');
$expect(count($craigslistImages) === 1, 'V0.2.25 verification must keep only the first Craigslist listing image.');

$offerupState = [
    'props' => [
        'pageProps' => [
            'item' => [
                'photos' => [
                    ['images' => ['detail' => ['url' => 'https://cdn.example.com/item-detail.webp']]],
                    ['images' => ['orig' => ['url' => 'https://cdn.example.com/item-original.jpg']]],
                ],
            ],
        ],
    ],
];
$offerupHtml = '<html><body><script id="__NEXT_DATA__" type="application/json">'
    . json_encode($offerupState, JSON_UNESCAPED_SLASHES)
    . '</script></body></html>';
$offerupImages = MarketplaceImageExtractor::fromHtml('offerup', $offerupHtml);
$expect($offerupImages === ['https://cdn.example.com/item-detail.webp'], 'V0.2.25 verification must keep only the first OfferUp listing image.');

$structured = ImageFingerprint::urls([
    'galleryUrls' => ['https://cdn.example.com/one.jpg'],
    'itemPhotos' => [['images' => ['orig' => ['url' => 'https://cdn.example.com/two.jpg']]]],
]);
$expect(in_array('https://cdn.example.com/one.jpg', $structured, true), 'galleryUrls must be recognized as image data.');
$expect(in_array('https://cdn.example.com/two.jpg', $structured, true), 'itemPhotos/orig image must be recognized.');

$postModel = $read('app/Models/Post.php');
$expect(str_contains($postModel, 'ImageFingerprint::urls($meta)'), 'Post save must preserve extracted image URLs independent of fingerprint success.');
$expect(str_contains($postModel, '$fetchedImageUrl'), 'Post save must persist the first extracted listing image.');

$api = $read('app/Controllers/ApiController.php');
$expect(str_contains($api, "'images' => array_slice"), 'Inspection API must return extracted images.');

$view = $read('app/Views/sales/_submit_form.php');
$expect(str_contains($view, 'id="resultImages"'), 'Sales verification result image preview is missing.');

$js = $read('public/assets/app.js');
$expect(str_contains($js, 'const resultImages=Array.isArray(d.images)?d.images:[];'), 'Sales JS does not render inspection images.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "V0.2.16 marketplace image extraction contract: PASS\n";
