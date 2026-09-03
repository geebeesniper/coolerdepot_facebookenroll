<?php
/**
 * File / 文件：app/Services/InspectionProcessLock.php
 * EN: Provides a durable per-Sales Marketplace verification lock that Admin can inspect and force-unlock.
 * 中文：提供按 Sales 用户隔离、可由 Admin 查看并手动解锁的 Marketplace 验证锁。
 *
 * EN: Locks are stored in MySQL instead of connection-owned advisory locks. This allows an
 * authenticated Admin to clear a stuck gate from Settings. Each request owns a random token;
 * a late release from an older request cannot delete a newer request's lock.
 * 中文：锁保存于 MySQL，而不是绑定数据库连接的 advisory lock，因此 Admin 可以从 Settings
 * 清除卡住的锁。每次请求拥有随机 token；旧请求迟到的 release 不会误删新请求的锁。
 */
namespace App\Services;

use App\Core\Database;

/**
 * EN: Coordinates one active Marketplace inspection per authenticated Sales user.
 * 中文：保证每个已登录 Sales 用户同一时间最多只有一个 Marketplace Inspection 在运行。
 */
final class InspectionProcessLock
{
    /** @var array<int,string> Request-owned lock tokens keyed by Sales user ID. / 当前请求持有的锁 token。 */
    private static array $ownedTokens = [];

    /**
     * EN: Ensure the durable lock table exists. The operation is idempotent and safe on every request.
     * 中文：确保持久化锁表存在；该操作可重复执行，不会重建或删除已有记录。
     *
     * @return void No value is returned. / 无返回值。
     */
    private static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $check = Database::connection()->prepare(
            "SELECT 1
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name='cdsp_inspection_locks'
             LIMIT 1"
        );
        $check->execute();
        if ($check->fetchColumn()) {
            $ready = true;
            return;
        }

        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS cdsp_inspection_locks (
                sales_user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                lock_token CHAR(64) NOT NULL,
                platform VARCHAR(32) NULL,
                url_hash CHAR(64) NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_cdsp_inspection_locks_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    }

    /**
     * EN: Acquire the Sales user's verification gate immediately without waiting.
     * 中文：立即尝试获取该 Sales 用户的验证门锁，不等待其他验证完成。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @param string $platform Marketplace platform name. / Marketplace 平台名称。
     * @param string $url Normalized listing URL. / 标准化后的帖子 URL。
     * @return bool True when this request acquired the lock. / 当前请求成功获得锁时返回 true。
     */
    public static function acquire(int $salesUserId, string $platform = '', string $url = ''): bool
    {
        self::ensureTable();

        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare(
            "INSERT IGNORE INTO cdsp_inspection_locks
                (sales_user_id, lock_token, platform, url_hash, started_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $salesUserId,
            $token,
            strtolower(trim($platform)) ?: null,
            $url !== '' ? hash('sha256', $url) : null,
        ]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        self::$ownedTokens[$salesUserId] = $token;
        return true;
    }

    /**
     * EN: Check whether the Sales user currently has a verification gate record.
     * 中文：检查该 Sales 用户当前是否存在验证锁记录。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @return bool True while a verification gate is active. / 验证锁存在时返回 true。
     */
    public static function isLocked(int $salesUserId): bool
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM cdsp_inspection_locks WHERE sales_user_id=? LIMIT 1'
        );
        $stmt->execute([$salesUserId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * EN: Release only the lock token owned by this request.
     * 中文：仅释放当前请求自己持有的 token，防止误删后来建立的新锁。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @return void No value is returned. / 无返回值。
     */
    public static function release(int $salesUserId): void
    {
        self::ensureTable();
        $token = self::$ownedTokens[$salesUserId] ?? '';
        if ($token === '') {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM cdsp_inspection_locks WHERE sales_user_id=? AND lock_token=?'
        );
        $stmt->execute([$salesUserId, $token]);
        unset(self::$ownedTokens[$salesUserId]);
    }

    /**
     * EN: Return active verification locks with Sales names for the Admin Settings page.
     * 中文：返回当前验证锁及 Sales 名称，供 Admin Settings 显示。
     *
     * @return array<int,array<string,mixed>> Active lock rows. / 当前锁记录。
     */
    public static function activeLocks(): array
    {
        self::ensureTable();
        return Database::connection()->query(
            "SELECT
                l.sales_user_id,
                l.platform,
                l.started_at,
                l.updated_at,
                u.sales_id,
                u.display_name
             FROM cdsp_inspection_locks l
             LEFT JOIN cdsp_users u ON u.id=l.sales_user_id
             ORDER BY l.started_at ASC, l.sales_user_id ASC"
        )->fetchAll() ?: [];
    }

    /**
     * EN: Force-clear one Sales verification gate from Admin Settings.
     * 中文：由 Admin Settings 强制清除指定 Sales 的验证门锁。
     *
     * EN: This removes the gate record only; it cannot terminate a PHP/provider request already
     * executing. The request-token check in release() prevents that older request from deleting a
     * newer lock if the Sales starts another verification after the Admin unlock.
     * 中文：该操作只清除门锁记录，无法终止已经执行中的 PHP/Provider 请求。release() 的 token
     * 校验会防止旧请求结束时误删 Admin 解锁后新建的验证锁。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @return bool True when a lock row was removed. / 成功删除锁记录时返回 true。
     */
    public static function forceRelease(int $salesUserId): bool
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'DELETE FROM cdsp_inspection_locks WHERE sales_user_id=?'
        );
        $stmt->execute([$salesUserId]);
        return $stmt->rowCount() > 0;
    }
}
