<?php
/**
 * File / 文件：scripts/export_transfer_database.php
 * EN: CLI maintenance/deployment script for export transfer database.
 * 中文：用于 export transfer database 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

/**
 * EN: Perform the demo external ids helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“demo external ids”辅助操作。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function demoExternalIds(): array
{
    return [
        '900000000001',
        '900000000002',
        'demo-900000000003',
        '900000000004',
        '900000000005',
        'demo-900000000006',
        '1612547780491408',
        '1578098323791707',
        '1754865915754719',
        '1609835460847233',
        '1546388710570410',
        '3813795918762562',
        '1606074697620900',
        '970768882088732',
        '1556421559266266',
        '1994325934606833',
    ];
}

/**
 * EN: Perform the sql string list helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“sql string list”辅助操作。
 *
 * @param PDO $pdo PDO database connection used by the operation. / 本操作使用的 PDO 数据库连接。
 * @param array $values Values value used by this operation. / 本操作使用的“values”参数值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function sqlStringList(PDO $pdo, array $values): string
{
    return implode(',', array_map(static fn(string $v): string => $pdo->quote($v), $values));
}

/**
 * EN: Perform the sql int list helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“sql int list”辅助操作。
 *
 * @param array $values Values value used by this operation. / 本操作使用的“values”参数值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function sqlIntList(array $values): string
{
    $values = array_values(array_unique(array_map('intval', $values)));
    return $values ? implode(',', $values) : 'NULL';
}

/**
 * EN: Resolve the resolve demo ids helper used by this maintenance CLI script.
 * 中文：解析或确定 当前维护命令行脚本使用的“resolve demo ids”辅助操作。
 *
 * @param PDO $pdo PDO database connection used by the operation. / 本操作使用的 PDO 数据库连接。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function resolveDemoIds(PDO $pdo): array
{
    $external = sqlStringList($pdo, demoExternalIds());
    $sql = "SELECT id
            FROM cdsp_sales_posts
            WHERE external_post_id IN ($external)
              AND (
                    external_post_id LIKE '90000000000%'
                 OR external_post_id LIKE 'demo-90000000000%'
                 OR (
                      title LIKE 'Facebook Marketplace Sample #%'
                      AND description='Real Facebook Marketplace URL supplied for Sales Post Tracker testing.'
                    )
              )";

    $postIds = array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    $postList = sqlIntList($postIds);

    $reviewIds = $postIds
        ? array_map('intval', $pdo->query("SELECT id FROM cdsp_post_reviews WHERE post_id IN ($postList)")->fetchAll(PDO::FETCH_COLUMN))
        : [];
    $commentIds = $postIds
        ? array_map('intval', $pdo->query("SELECT id FROM cdsp_post_review_comments WHERE post_id IN ($postList)")->fetchAll(PDO::FETCH_COLUMN))
        : [];

    return [
        'post_ids' => $postIds,
        'review_ids' => $reviewIds,
        'comment_ids' => $commentIds,
    ];
}

/**
 * EN: Perform the export where helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“export where”辅助操作。
 *
 * @param string $table Table value used by this operation. / 本操作使用的“table”参数值。
 * @param array $demo Demo value used by this operation. / 本操作使用的“demo”参数值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function exportWhere(string $table, array $demo): string
{
    $posts = sqlIntList($demo['post_ids']);
    $reviews = sqlIntList($demo['review_ids']);
    $comments = sqlIntList($demo['comment_ids']);

    return match ($table) {
        // Authentication sessions and Bearer tokens are runtime credentials and
        // must never be migrated to another server as active credentials.
        'cdsp_auth_sessions',
        'cdsp_auth_handoffs',
        'cdsp_api_tokens' => ' WHERE 1=0',
        'cdsp_sales_posts' => " WHERE id NOT IN ($posts)",
        'cdsp_post_reviews',
        'cdsp_post_review_history',
        'cdsp_post_review_comments',
        'cdsp_deletion_requests',
        'cdsp_post_image_fingerprints' => " WHERE post_id NOT IN ($posts)",
        'cdsp_review_attachments' =>
            " WHERE NOT ("
            . "(entity_type='post_note' AND entity_id IN ($posts)) OR "
            . "(entity_type='post_review' AND entity_id IN ($reviews)) OR "
            . "(entity_type='post_comment' AND entity_id IN ($comments))"
            . ")",
        default => '',
    };
}

/**
 * EN: Perform the sql literal helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“sql literal”辅助操作。
 *
 * @param PDO $pdo PDO database connection used by the operation. / 本操作使用的 PDO 数据库连接。
 * @param mixed $value Value processed or stored by this operation. / 本操作处理或保存的值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function sqlLiteral(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    return $pdo->quote((string)$value);
}

/**
 * EN: Perform the export table helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“export table”辅助操作。
 *
 * @param PDO $pdo PDO database connection used by the operation. / 本操作使用的 PDO 数据库连接。
 * @param mixed $fh Fh value used by this operation. / 本操作使用的“fh”参数值。
 * @param string $table Table value used by this operation. / 本操作使用的“table”参数值。
 * @param array $demo Demo value used by this operation. / 本操作使用的“demo”参数值。
 *
 * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
function exportTable(PDO $pdo, $fh, string $table, array $demo): int
{
    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    $create = $pdo->query("SHOW CREATE TABLE $quotedTable")->fetch(PDO::FETCH_NUM);
    if (!$create || !isset($create[1])) {
        throw new RuntimeException("Could not read schema for $table.");
    }

    fwrite($fh, "\n-- ------------------------------------------------------------------\n");
    fwrite($fh, "-- Table: $table\n");
    fwrite($fh, "-- ------------------------------------------------------------------\n");
    fwrite($fh, "DROP TABLE IF EXISTS $quotedTable;\n");
    fwrite($fh, $create[1] . ";\n");

    $stmt = $pdo->query("SELECT * FROM $quotedTable" . exportWhere($table, $demo));
    $columns = [];
    $count = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$columns) {
            $columns = array_keys($row);
        }
        $columnSql = implode(',', array_map(
            static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        ));
        $valueSql = implode(',', array_map(
            static fn(string $column): string => sqlLiteral($pdo, $row[$column]),
            $columns
        ));
        fwrite($fh, "INSERT INTO $quotedTable ($columnSql) VALUES ($valueSql);\n");
        $count++;
    }

    return $count;
}

/**
 * EN: Perform the output path helper used by this maintenance CLI script.
 * 中文：执行 当前维护命令行脚本使用的“output path”辅助操作。
 *
 * @param array $argv Argv value used by this operation. / 本操作使用的“argv”参数值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function outputPath(array $argv): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--output=')) {
            $value = trim(substr($arg, 9));
            if ($value !== '') {
                return $value;
            }
        }
    }

    return dirname(__DIR__) . '/storage/transfer/cdsp-production-clean.sql';
}

$pdo = Database::connection();
$target = outputPath($argv);
$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
    throw new RuntimeException("Could not create export directory: $dir");
}

$fh = fopen($target, 'wb');
if ($fh === false) {
    throw new RuntimeException("Could not open export file: $target");
}
@chmod($target, 0600);

try {
    $demo = resolveDemoIds($pdo);
    $tables = $pdo->query("SHOW TABLES LIKE 'cdsp\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    sort($tables, SORT_STRING);

    $secret = (string)($config['auth']['handoff_secret'] ?? '');
    $secretFingerprint = $secret !== '' ? hash('sha256', $secret) : 'NOT_CONFIGURED';
    $providerTokenCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cdsp_provider_profiles WHERE token_encrypted IS NOT NULL AND token_encrypted<>''"
    )->fetchColumn();
    $legacySecretCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cdsp_settings WHERE is_secret=1 AND setting_value<>''"
    )->fetchColumn();

    fwrite($fh, "-- CoolerDepot Sales Post Tracker production transfer database\n");
    fwrite($fh, "-- Generated: " . gmdate('Y-m-d\\TH:i:s\\Z') . "\n");
    fwrite($fh, "-- Demo posts excluded: " . count($demo['post_ids']) . "\n");
    fwrite($fh, "-- Encrypted provider tokens preserved: $providerTokenCount\n");
    fwrite($fh, "-- Encrypted legacy secret settings preserved: $legacySecretCount\n");
    fwrite($fh, "-- AUTH_HANDOFF_SECRET SHA-256 fingerprint: $secretFingerprint\n");
    fwrite($fh, "-- IMPORTANT: The destination must use the SAME AUTH_HANDOFF_SECRET to decrypt migrated tokens.\n");
    fwrite($fh, "-- IMPORTANT: The secret itself is intentionally NOT written to this dump.\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = exportTable($pdo, $fh, (string)$table, $demo);
    }

    fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);

    $bytes = filesize($target) ?: 0;
    fwrite(STDOUT, "Production database export complete.\n");
    fwrite(STDOUT, "File: $target\n");
    fwrite(STDOUT, "Tables: " . count($tables) . "\n");
    fwrite(STDOUT, "Demo posts excluded: " . count($demo['post_ids']) . "\n");
    fwrite(STDOUT, "Encrypted provider tokens preserved: $providerTokenCount\n");
    fwrite(STDOUT, "Encrypted legacy secret settings preserved: $legacySecretCount\n");
    fwrite(STDOUT, "AUTH_HANDOFF_SECRET fingerprint: $secretFingerprint\n");
    fwrite(STDOUT, "Bytes: $bytes\n");
} catch (Throwable $e) {
    if (is_resource($fh)) {
        fclose($fh);
    }
    @unlink($target);
    fwrite(STDERR, "Database export failed: " . $e->getMessage() . "\n");
    exit(1);
}
