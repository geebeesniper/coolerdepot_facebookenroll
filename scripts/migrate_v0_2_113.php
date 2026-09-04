<?php
/**
 * File / 文件：scripts/migrate_v0_2_113.php
 * EN: Apply the V0.2.113 daily target history and completion schema migration.
 * 中文：执行 V0.2.113 Daily Target 历史与每日完成记录数据库迁移。
 */
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\SchemaCompatibility;

SchemaCompatibility::ensureDailyWorkflowCompatibility();
echo "V0.2.113 daily workflow schema ready.\n";
