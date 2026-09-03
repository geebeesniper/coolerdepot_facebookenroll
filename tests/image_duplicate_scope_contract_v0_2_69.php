<?php
/**
 * V0.2.69 contract: exact first-image duplicates follow the same scope as title duplicates.
 * Marketplace image: same Sales + same platform only.
 * Website image: company website library remains a separate hard duplicate source.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/DuplicateIndex.php');
$model = file_get_contents($root . '/app/Models/Post.php');
$count = 0;
$check = function (bool $ok, string $label) use (&$count): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS ' . $label . PHP_EOL;
    $count++;
};

$check(str_contains($service, 'findOwnPlatformExactImage'), 'image matching is isolated in an own-Sales/platform helper');
$check(str_contains($service, 'WHERE p.sales_user_id=?'), 'image SQL requires current Sales user');
$check(str_contains($service, 'AND LOWER(p.platform)=?'), 'image SQL requires current marketplace platform');
$check(str_contains($service, 'AND f.sha256=?'), 'image duplicate remains exact SHA-256 only');
$check(str_contains($service, "SELECT page_url,title FROM cdsp_website_references WHERE sha256=? LIMIT 1"), 'website exact-image comparison remains separate');
$check(str_contains($service, "'kind'=>'same_platform_image'"), 'marketplace image duplicate kind remains stable');
$check(str_contains($service, "'kind'=>'website_exact_image'"), 'website image duplicate kind remains stable');
$check(str_contains($model, 'WHERE sales_user_id=? AND platform=? AND BINARY title=BINARY ?'), 'title rule remains same Sales + same platform');

// Runtime scope checks when PDO SQLite is available. These checks use an isolated
// in-memory DB and never read production credentials.
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    spl_autoload_register(function ($class) use ($root) {
        if (str_starts_with($class, 'App\\')) {
            require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        }
    });
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $prop = new ReflectionProperty(App\Core\Database::class, 'pdo');
    $prop->setValue(null, $pdo);
    $pdo->exec('CREATE TABLE cdsp_sales_posts(id INTEGER PRIMARY KEY,sales_user_id INTEGER,platform TEXT,title TEXT,canonical_url TEXT,deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cdsp_post_image_fingerprints(id INTEGER PRIMARY KEY,post_id INTEGER,image_url TEXT,sha256 TEXT,dhash TEXT)');
    $pdo->exec('CREATE TABLE cdsp_website_references(id INTEGER PRIMARY KEY,page_url TEXT,title TEXT,image_url TEXT,sha256 TEXT,dhash TEXT,imported_at TEXT)');
    $pdo->exec("INSERT INTO cdsp_sales_posts(id,sales_user_id,platform,title,canonical_url,deleted_at) VALUES(1,11,'facebook','Existing','https://facebook.example/1',NULL)");
    $sha = str_repeat('a', 64);
    $stmt = $pdo->prepare('INSERT INTO cdsp_post_image_fingerprints(id,post_id,image_url,sha256,dhash) VALUES(1,1,?,?,NULL)');
    $stmt->execute(['https://images.example/one.jpg', $sha]);
    $asset = [['url'=>'https://images.example/new.jpg','sha256'=>$sha,'dhash'=>null]];

    $own = App\Services\DuplicateIndex::compare(11, 'facebook', 'Different title', $asset);
    $otherSales = App\Services\DuplicateIndex::compare(22, 'facebook', 'Different title', $asset);
    $otherPlatform = App\Services\DuplicateIndex::compare(11, 'offerup', 'Different title', $asset);
    $check($own['blocked'] !== null && (($own['matches'][0]['kind'] ?? '') === 'same_platform_image'), 'same Sales + same platform exact image blocks');
    $check($otherSales['blocked'] === null, 'different Sales exact image does not compare');
    $check($otherPlatform['blocked'] === null, 'different marketplace platform exact image does not compare');
} else {
    echo "SKIP runtime scope checks: PDO SQLite unavailable.\n";
}

echo $count . " V0.2.69 image duplicate scope checks passed.\n";
