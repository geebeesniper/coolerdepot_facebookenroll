<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Services\PostInspector;

$urls = [
    'https://www.facebook.com/marketplace/item/1612547780491408',
    'https://www.facebook.com/marketplace/item/1578098323791707',
    'https://www.facebook.com/marketplace/item/1754865915754719',
    'https://www.facebook.com/marketplace/item/1609835460847233',
    'https://www.facebook.com/marketplace/item/1546388710570410',
    'https://www.facebook.com/marketplace/item/3813795918762562',
    'https://www.facebook.com/marketplace/item/1606074697620900',
    'https://www.facebook.com/marketplace/item/970768882088732',
    'https://www.facebook.com/marketplace/item/1556421559266266',
    'https://www.facebook.com/marketplace/item/1994325934606833',
];

$pdo = Database::connection();
$stmt = $pdo->query(
    "SELECT id, sales_id, display_name
     FROM cdsp_users
     WHERE role='sales' AND active=1
     ORDER BY id
     LIMIT 1"
);
$user = $stmt->fetch();

if (!$user) {
    fwrite(STDERR, "No active Sales user exists in cdsp_users.\n");
    exit(1);
}

echo "Testing Facebook Marketplace links from this server\n";
echo "Sales context: {$user['display_name']} (#{$user['sales_id']})\n";
echo "Timezone: {$config['app']['timezone']}\n";
echo str_repeat('=', 100) . "\n";

$inspector = new PostInspector();

foreach ($urls as $i => $url) {
    $result = $inspector->inspect((int)$user['id'], 'facebook', $url);

    echo sprintf("[%02d] %s\n", $i + 1, $url);
    echo "     status       : " . ($result['verification_status'] ?? '') . "\n";
    echo "     failure_code : " . ($result['failure_code'] ?? '') . "\n";
    echo "     message      : " . ($result['failure_message'] ?? '') . "\n";
    echo "     resolved_url : " . ($result['resolved_url'] ?? '') . "\n";
    echo "     canonical    : " . ($result['canonical_url'] ?? '') . "\n";
    echo "     item_id      : " . ($result['external_post_id'] ?? '') . "\n";
    echo "     title        : " . mb_substr((string)($result['title'] ?? ''), 0, 180) . "\n";
    echo "     published_at : " . ($result['published_at'] ?? '') . "\n";

    $description = preg_replace('/\s+/u', ' ', (string)($result['description'] ?? ''));
    echo "     description  : " . mb_substr($description, 0, 220) . "\n";
    echo str_repeat('-', 100) . "\n";
}
