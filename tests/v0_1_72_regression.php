<?php
$root = dirname(__DIR__);
$inspector = file_get_contents($root.'/app/Services/PostInspector.php');
$post = file_get_contents($root.'/app/Models/Post.php');
$migration = file_get_contents($root.'/scripts/migrate_v0_1_72.php');

$checks = [
    'facebook share resolves to stable marketplace URL after numeric ID' =>
        str_contains($inspector, "'https://www.facebook.com/marketplace/item/' . \$eid"),
    'duplicate lookup still checks platform external post id' =>
        str_contains($post, 'WHERE platform=? AND external_post_id=?'),
    'title duplicate remains byte-for-byte exact' =>
        str_contains($post, 'BINARY title=BINARY ?'),
    'migration allows NULL review state' =>
        str_contains($migration, "ENUM('good','bad') NULL DEFAULT NULL"),
    'migration converts pending review to NULL' =>
        str_contains($migration, "WHEN 'pending' THEN NULL"),
];

$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label."\n";
    if (!$ok) $failed++;
}
exit($failed ? 1 : 0);
