<?php
/**
 * File / 文件：scripts/migrate_v0_1_27.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'cdsp_sales_posts'
       AND COLUMN_NAME = 'fetched_image_url'"
);

if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_posts
         ADD COLUMN fetched_image_url TEXT NULL
         AFTER fetched_at"
    );
    echo "Added cdsp_sales_posts.fetched_image_url." . PHP_EOL;
} else {
    echo "cdsp_sales_posts.fetched_image_url already exists." . PHP_EOL;
}

$storage = dirname(__DIR__) . '/storage';
$uploads = $storage . '/uploads';

foreach ([$storage, $uploads] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException(
                'Could not create upload directory: ' . $dir
            );
        }
    }

    @chmod($dir, 0770);
    @chown($dir, 'www-data');
    @chgrp($dir, 'www-data');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $uploads,
        FilesystemIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $path = $item->getPathname();

    @chown($path, 'www-data');
    @chgrp($path, 'www-data');
    @chmod($path, $item->isDir() ? 0770 : 0660);
}

$test = $uploads . '/.write-test-' . bin2hex(random_bytes(4));

if (@file_put_contents($test, 'ok') === false) {
    throw new RuntimeException(
        'Upload storage is still not writable after permission repair.'
    );
}

@unlink($test);

echo "Upload storage prepared for www-data." . PHP_EOL;
echo "v0.1.27 migration complete." . PHP_EOL;
