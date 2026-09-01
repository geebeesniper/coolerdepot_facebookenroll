<?php
/**
 * File / 文件：scripts/cleanup_demo_database.php
 * EN: CLI maintenance/deployment script for cleanup demo database.
 * 中文：用于 cleanup demo database 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\Post;

/**
 * EN: Retrieve the find demo posts helper used by this maintenance CLI script.
 * 中文：读取 当前维护命令行脚本使用的“find demo posts”辅助操作。
 *
 * @param PDO $pdo PDO database connection used by the operation. / 本操作使用的 PDO 数据库连接。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function findDemoPosts(PDO $pdo): array
{
    $ids = [
        '900000000001', '900000000002', 'demo-900000000003',
        '900000000004', '900000000005', 'demo-900000000006',
        '1612547780491408', '1578098323791707', '1754865915754719',
        '1609835460847233', '1546388710570410', '3813795918762562',
        '1606074697620900', '970768882088732', '1556421559266266',
        '1994325934606833',
    ];
    $quoted = implode(',', array_map(static fn(string $v): string => $pdo->quote($v), $ids));
    $stmt = $pdo->query(
        "SELECT id, external_post_id, title, sales_user_id, published_date
         FROM cdsp_sales_posts
         WHERE external_post_id IN ($quoted)
           AND (
                external_post_id LIKE '90000000000%'
             OR external_post_id LIKE 'demo-90000000000%'
             OR (
                  title LIKE 'Facebook Marketplace Sample #%'
                  AND description='Real Facebook Marketplace URL supplied for Sales Post Tracker testing.'
                )
           )
         ORDER BY id"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$apply = in_array('--apply', $argv, true);
$pdo = Database::connection();
$rows = findDemoPosts($pdo);

if (!$rows) {
    echo "No known demo posts found.\n";
    exit(0);
}

echo ($apply ? "APPLY MODE" : "DRY RUN") . ": " . count($rows) . " known demo post(s) found.\n";
foreach ($rows as $row) {
    echo sprintf(
        "- id=%d external_post_id=%s date=%s title=%s\n",
        (int)$row['id'],
        (string)$row['external_post_id'],
        (string)$row['published_date'],
        (string)$row['title']
    );
}

if (!$apply) {
    echo "No database rows were changed. Re-run with --apply to hard-delete only the rows listed above.\n";
    exit(0);
}

foreach ($rows as $row) {
    Post::hardDelete((int)$row['id']);
}

echo "Deleted " . count($rows) . " demo post(s) and their post-linked review/comment/attachment/index/deletion data.\n";
echo "Sales users were preserved.\n";
