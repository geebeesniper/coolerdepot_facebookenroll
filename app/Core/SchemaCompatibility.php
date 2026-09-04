<?php
/**
 * File / 文件：app/Core/SchemaCompatibility.php
 * EN: Provides one-time schema compatibility checks for direct overlay upgrades.
 * 中文：为直接覆盖升级提供一次性的数据库结构兼容检查。
 * Maintenance / 维护：Only apply idempotent compatibility changes required by released application code; never delete or rewrite business rows.
 * 维护要求：仅执行已发布代码所必需且可重复执行的兼容变更；不得删除或重写业务数据。
 */
namespace App\Core;

use PDO;
use Throwable;

/**
 * EN: Keeps older supported databases compatible when a direct file-overlay package is used.
 * 中文：在使用直接文件覆盖包时，使受支持的旧数据库自动兼容当前代码。
 */
final class SchemaCompatibility
{
    /**
     * EN: Ensure the V0.2.13 manual_pending verification status exists on databases upgraded directly from V0.2.07-V0.2.12.
     * 中文：确保从 V0.2.07-V0.2.12 直接覆盖升级的数据库具备 V0.2.13 引入的 manual_pending 验证状态。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function ensureDirectOverlayCompatibility(): void
    {
        $root = dirname(__DIR__, 2);
        $marker = $root . '/storage/.schema-v0.2.13-manual-pending-ready';

        if (is_file($marker)) {
            return;
        }

        $pdo = null;
        $lockAcquired = false;

        try {
            $pdo = Database::connection();
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            if ($driver !== 'mysql') {
                return;
            }

            // Prevent concurrent first requests from running the same ALTER twice.
            // 防止多个首次请求同时重复执行同一条 ALTER。
            $lockStmt = $pdo->query("SELECT GET_LOCK('cdsp_schema_v0_2_13_manual_pending', 10)");
            $lockAcquired = (int)($lockStmt ? $lockStmt->fetchColumn() : 0) === 1;

            if (!$lockAcquired) {
                Logger::warning(
                    'Schema compatibility lock was not acquired; migration check will retry on a later request.',
                    ['migration' => 'v0.2.13_manual_pending'],
                    'database'
                );
                return;
            }

            $required = [
                'cdsp_post_inspections' => false,
                'cdsp_sales_posts' => true,
            ];

            $needsMigration = false;

            foreach ($required as $table => $hasDefault) {
                $stmt = $pdo->query(
                    "SHOW COLUMNS FROM `{$table}` LIKE 'verification_status'"
                );
                $row = $stmt ? $stmt->fetch() : false;

                if (!$row) {
                    // A missing base table is an installation problem, not an overlay migration.
                    // 基础表缺失属于安装问题，不在覆盖升级中擅自创建业务表。
                    Logger::warning(
                        'Schema compatibility check could not find a required verification_status column.',
                        ['table' => $table, 'migration' => 'v0.2.13_manual_pending'],
                        'database'
                    );
                    return;
                }

                $type = strtolower((string)($row['Type'] ?? ''));
                if (strpos($type, "'manual_pending'") === false) {
                    $needsMigration = true;
                }
            }

            if ($needsMigration) {
                // V0.2.13 only expanded ENUM values. Existing rows are preserved.
                // V0.2.13 仅扩展 ENUM 可选值，现有业务记录保持不变。
                $pdo->exec(
                    "ALTER TABLE cdsp_post_inspections " .
                    "MODIFY COLUMN verification_status " .
                    "ENUM('verified','manual_pending','failed') NOT NULL"
                );
                $pdo->exec(
                    "ALTER TABLE cdsp_sales_posts " .
                    "MODIFY COLUMN verification_status " .
                    "ENUM('verified','manual_pending','failed') NOT NULL DEFAULT 'verified'"
                );
            }

            foreach (array_keys($required) as $table) {
                $stmt = $pdo->query(
                    "SHOW COLUMNS FROM `{$table}` LIKE 'verification_status'"
                );
                $row = $stmt ? $stmt->fetch() : false;
                $type = strtolower((string)($row['Type'] ?? ''));

                if (!$row || strpos($type, "'manual_pending'") === false) {
                    Logger::warning(
                        'Automatic direct-overlay schema compatibility verification did not complete.',
                        ['table' => $table, 'migration' => 'v0.2.13_manual_pending'],
                        'database'
                    );
                    return;
                }
            }

            @file_put_contents(
                $marker,
                "V0.2.13 manual_pending schema compatibility verified at " . date('c') . PHP_EOL,
                LOCK_EX
            );

            Logger::info(
                $needsMigration
                    ? 'Direct-overlay schema compatibility migration completed.'
                    : 'Direct-overlay schema compatibility already satisfied.',
                ['migration' => 'v0.2.13_manual_pending'],
                'database'
            );
        } catch (Throwable $e) {
            // Keep the existing application reachable if the database account lacks ALTER.
            // 若数据库账号缺少 ALTER 权限，保留现有应用可访问性，并记录完整错误供管理员处理。
            Logger::exception(
                $e,
                'database',
                [
                    'event' => 'Direct-overlay schema compatibility migration failed',
                    'migration' => 'v0.2.13_manual_pending',
                ],
                'error'
            );
        } finally {
            if ($lockAcquired && $pdo instanceof PDO) {
                try {
                    $pdo->query("SELECT RELEASE_LOCK('cdsp_schema_v0_2_13_manual_pending')");
                } catch (Throwable $ignored) {
                    // Lock release failure is non-fatal; MySQL releases named locks when the connection closes.
                    // 释放锁失败不影响请求；MySQL 会在连接关闭时自动释放命名锁。
                }
            }
        }
    }
    /**
     * EN: Ensure V0.2.113 daily target history and daily completion tables exist for direct overlay upgrades.
     * 中文：确保 V0.2.113 的每日目标历史与每日完成记录表可用于直接覆盖升级。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function ensureDailyWorkflowCompatibility(): void
    {
        $root = dirname(__DIR__, 2);
        $marker = $root . '/storage/.schema-v0.2.113-daily-workflow-ready';

        if (is_file($marker)) {
            return;
        }

        $pdo = null;
        $lockAcquired = false;

        try {
            $pdo = Database::connection();
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            if ($driver !== 'mysql') {
                return;
            }

            $lockStmt = $pdo->query("SELECT GET_LOCK('cdsp_schema_v0_2_113_daily_workflow', 10)");
            $lockAcquired = (int)($lockStmt ? $lockStmt->fetchColumn() : 0) === 1;

            if (!$lockAcquired) {
                Logger::warning(
                    'Daily workflow schema compatibility lock was not acquired; migration check will retry later.',
                    ['migration' => 'v0.2.113_daily_workflow'],
                    'database'
                );
                return;
            }

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS cdsp_sales_daily_target_history (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    sales_user_id INT UNSIGNED NOT NULL,
                    effective_date DATE NOT NULL,
                    daily_post_target SMALLINT UNSIGNED NOT NULL,
                    changed_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY(id),
                    UNIQUE KEY uq_sales_daily_target_date(sales_user_id,effective_date),
                    KEY idx_sales_daily_target_lookup(sales_user_id,effective_date),
                    CONSTRAINT fk_sales_daily_target_user
                        FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
                    CONSTRAINT fk_sales_daily_target_admin
                        FOREIGN KEY(changed_by) REFERENCES cdsp_users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS cdsp_daily_sales_completions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    sales_user_id INT UNSIGNED NOT NULL,
                    work_date DATE NOT NULL,
                    admin_user_id INT UNSIGNED NOT NULL,
                    completed_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY(id),
                    UNIQUE KEY uq_daily_sales_completion(sales_user_id,work_date),
                    KEY idx_daily_sales_completion_date(work_date,sales_user_id),
                    CONSTRAINT fk_daily_sales_completion_sales
                        FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
                    CONSTRAINT fk_daily_sales_completion_admin
                        FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            // Existing installations have only the current target value. Seed one
            // baseline row so future changes preserve the target that was active
            // before the first V0.2.113 change without rewriting any Sales rows.
            // 旧安装只有当前目标值；写入基准历史后，V0.2.113 之后的修改即可按日期还原。
            $pdo->exec(
                "INSERT IGNORE INTO cdsp_sales_daily_target_history(
                    sales_user_id,effective_date,daily_post_target,changed_by,created_at,updated_at
                 )
                 SELECT
                    id,'1970-01-01',COALESCE(NULLIF(daily_post_target,0),10),NULL,NOW(),NOW()
                 FROM cdsp_users
                 WHERE role='sales'"
            );

            @file_put_contents(
                $marker,
                "V0.2.113 daily workflow schema compatibility verified at " . date('c') . PHP_EOL,
                LOCK_EX
            );

            Logger::info(
                'Daily workflow direct-overlay schema compatibility verified.',
                ['migration' => 'v0.2.113_daily_workflow'],
                'database'
            );
        } catch (Throwable $e) {
            Logger::exception(
                $e,
                'database',
                [
                    'event' => 'Daily workflow direct-overlay schema compatibility failed',
                    'migration' => 'v0.2.113_daily_workflow',
                ],
                'error'
            );
        } finally {
            if ($lockAcquired && $pdo instanceof PDO) {
                try {
                    $pdo->query("SELECT RELEASE_LOCK('cdsp_schema_v0_2_113_daily_workflow')");
                } catch (Throwable $ignored) {
                    // EN: MySQL releases named locks when the connection closes.
                    // 中文：连接关闭时 MySQL 会自动释放命名锁。
                }
            }
        }
    }

}
