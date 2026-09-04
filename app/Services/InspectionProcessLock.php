<?php
/**
 * File / 文件：app/Services/InspectionProcessLock.php
 * EN: Provides a self-healing per-Sales Marketplace verification lock with an Admin manual-unlock fallback.
 * 中文：提供按 Sales 用户隔离、可自动恢复并保留 Admin 手动解锁兜底的 Marketplace 验证锁。
 *
 * EN: Each durable row is paired with a dedicated MySQL liveness lease owned by a separate PDO
 * connection. If PHP/FPM exits abnormally, that connection closes and MySQL releases the lease
 * automatically. The next acquire/status/settings read detects the orphan row and removes it.
 * A hard safety timeout also recovers a process that remains connected but is genuinely stuck.
 * 中文：每条持久化锁记录都配有独立 PDO 连接持有的 MySQL 存活 lease。PHP/FPM 异常退出时，
 * 该连接会关闭，MySQL 自动释放 lease；下一次获取锁、状态检查或 Settings 读取会识别孤儿记录并
 * 自动清除。若进程仍保持连接但确实卡死，还会由硬性安全超时自动恢复。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Setting;
use PDO;

/**
 * EN: Coordinates one active Marketplace inspection per authenticated Sales user.
 * 中文：保证每个已登录 Sales 用户同一时间最多只有一个 Marketplace Inspection 在运行。
 */
final class InspectionProcessLock
{
    /** @var array<int,string> Request-owned durable lock tokens keyed by Sales user ID. / 当前请求持有的持久化锁 token。 */
    private static array $ownedTokens = [];

    /** @var array<int,\PDO> Dedicated liveness connections keyed by Sales user ID. / 当前请求持有的独立存活连接。 */
    private static array $leaseConnections = [];

    /** @var array<int,string> MySQL liveness lock names keyed by Sales user ID. / 当前请求持有的 MySQL 存活锁名称。 */
    private static array $leaseNames = [];

    /** @var bool Whether the shutdown recovery callback is registered. / 是否已注册请求结束自动释放回调。 */
    private static bool $shutdownRegistered = false;

    /** EN: Default connected-but-stuck recovery timeout in minutes. / 连接仍存活但卡死时的默认恢复分钟数。 */
    private const DEFAULT_RECOVERY_MINUTES = 5;

    /** EN: Safety floor prevents a normal provider chain from being unlocked too early. / 安全下限，避免正常 Provider Chain 被过早解锁。 */
    private const MIN_RECOVERY_MINUTES = 5;

    /** EN: Upper bound keeps an accidental Admin value from recreating a very long lockout. / 上限避免误设置重新造成长时间锁定。 */
    private const MAX_RECOVERY_MINUTES = 60;

    /** EN: Persistent Admin setting key. / Admin 后台持久化设置键。 */
    private const RECOVERY_SETTING_KEY = 'inspection_lock_recovery_minutes';

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
     * EN: Return the environment/config fallback retained for installations that have not saved the Admin setting yet.
     * 中文：返回环境变量/配置文件兜底值；尚未在 Admin 保存该设置的安装仍继续使用此值。
     *
     * @return int Recovery timeout in seconds. / 自动恢复超时秒数。
     */
    private static function fallbackRecoverySeconds(): int
    {
        global $config;
        $seconds = (int)($config['app']['inspection_lock_recovery_seconds'] ?? (self::DEFAULT_RECOVERY_MINUTES * 60));
        return max(
            self::MIN_RECOVERY_MINUTES * 60,
            min(self::MAX_RECOVERY_MINUTES * 60, $seconds)
        );
    }

    /**
     * EN: Return the Admin-configured connected-but-stuck recovery timeout in minutes.
     * 中文：返回 Admin 后台设置的“连接仍存活但卡死”自动恢复分钟数。
     *
     * Dead/disconnected requests are still recovered immediately from the missing MySQL liveness lease;
     * this setting is only the hard safety timeout for a lease that still appears alive.
     * 进程死亡或断线仍会因 MySQL liveness lease 消失而立即恢复；本设置只控制 lease 仍显示存活时的硬性兜底超时。
     *
     * @return int Timeout in whole minutes. / 整数分钟。
     */
    public static function recoveryMinutes(): int
    {
        $fallback = max(
            self::MIN_RECOVERY_MINUTES,
            min(self::MAX_RECOVERY_MINUTES, (int)ceil(self::fallbackRecoverySeconds() / 60))
        );

        try {
            $stored = Setting::get(self::RECOVERY_SETTING_KEY, null);
            if ($stored === null || !preg_match('/^\d+$/', trim($stored))) {
                return $fallback;
            }

            return max(
                self::MIN_RECOVERY_MINUTES,
                min(self::MAX_RECOVERY_MINUTES, (int)$stored)
            );
        } catch (\Throwable $e) {
            // EN: Lock recovery must stay available even if the settings table is temporarily unavailable.
            // 中文：即使 Settings 表暂时不可用，验证锁自动恢复也必须继续工作，因此回退到配置值。
            return $fallback;
        }
    }

    /** @return int Minimum Admin-selectable recovery timeout in minutes. / Admin 可设置的最短恢复分钟数。 */
    public static function minRecoveryMinutes(): int
    {
        return self::MIN_RECOVERY_MINUTES;
    }

    /** @return int Maximum Admin-selectable recovery timeout in minutes. / Admin 可设置的最长恢复分钟数。 */
    public static function maxRecoveryMinutes(): int
    {
        return self::MAX_RECOVERY_MINUTES;
    }

    /**
     * EN: Validate and persist the Admin hard-recovery timeout.
     * 中文：校验并保存 Admin 设置的硬性自动恢复超时。
     *
     * @param int $minutes Whole minutes requested by Admin. / Admin 请求的整数分钟。
     * @param int $updatedBy Admin user ID. / Admin 用户 ID。
     * @return void No value is returned. / 无返回值。
     */
    public static function setRecoveryMinutes(int $minutes, int $updatedBy): void
    {
        if ($minutes < self::MIN_RECOVERY_MINUTES || $minutes > self::MAX_RECOVERY_MINUTES) {
            throw new \InvalidArgumentException(
                'Verification recovery timeout must be between '
                . self::MIN_RECOVERY_MINUTES
                . ' and '
                . self::MAX_RECOVERY_MINUTES
                . ' minutes.'
            );
        }

        Setting::set(self::RECOVERY_SETTING_KEY, (string)$minutes, $updatedBy);
    }

    /**
     * EN: Return the maximum age before even a live-looking verification lock is considered stuck.
     * 中文：返回安全硬超时时间；即使 lease 仍显示存活，超过此时间也视为真正卡死并自动恢复。
     *
     * @return int Recovery timeout in seconds. / 自动恢复超时秒数。
     */
    private static function hardRecoverySeconds(): int
    {
        return self::recoveryMinutes() * 60;
    }

    /**
     * EN: Build a short MySQL advisory-lock name from a random request token.
     * 中文：根据随机请求 token 生成长度安全的 MySQL advisory lock 名称。
     *
     * @param string $token Durable lock token. / 持久化锁 token。
     * @return string MySQL lock name shorter than the MySQL 5.6 limit. / 小于 MySQL 5.6 限制的锁名称。
     */
    private static function leaseName(string $token): string
    {
        return 'cdsp_il_' . substr(hash('sha256', $token), 0, 48);
    }

    /**
     * EN: Open a dedicated PDO connection and acquire the token-specific liveness lease.
     * 中文：打开独立 PDO 连接并获取该 token 专属的存活 lease。
     *
     * A dedicated connection is intentional: MySQL 5.6 can release an earlier GET_LOCK when the
     * same session acquires a different named lock. Keeping this lease on its own connection makes
     * it independent from queue/schema/scan advisory locks used elsewhere in the request.
     * 使用独立连接是刻意设计：MySQL 5.6 同一连接再次 GET_LOCK 时可能释放之前的 named lock；
     * 独立连接可避免 Queue/Schema/Scan 等其他 advisory lock 干扰验证锁的存活标记。
     *
     * @param string $token Durable lock token. / 持久化锁 token。
     * @return array{0:\PDO,1:string} Dedicated connection and acquired lease name. / 独立连接与已获取 lease 名称。
     */
    private static function acquireLease(string $token): array
    {
        global $config;
        $db = $config['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $name = self::leaseName($token);
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$name]);
        if ((int)$stmt->fetchColumn() !== 1) {
            $pdo = null;
            throw new \RuntimeException('Verification liveness lease could not be acquired.');
        }

        return [$pdo, $name];
    }

    /**
     * EN: Release a temporary liveness connection that was not adopted by a durable lock row.
     * 中文：释放尚未被持久化锁记录接管的临时存活连接。
     *
     * @param \PDO $pdo Dedicated liveness PDO connection. / 独立存活 PDO 连接。
     * @param string $name MySQL advisory-lock name. / MySQL advisory lock 名称。
     * @return void No value is returned. / 无返回值。
     */
    private static function releaseLease(PDO $pdo, string $name): void
    {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$name]);
        } catch (\Throwable $ignore) {
            // EN: Closing the dedicated PDO connection also releases the MySQL lease.
            // 中文：即使显式 RELEASE_LOCK 失败，关闭独立 PDO 连接也会释放 MySQL lease。
        }
    }

    /**
     * EN: Detect and clear one orphaned or hard-timeout durable lock row.
     * 中文：检测并清除一条孤儿锁记录或超过硬性安全超时的锁记录。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @return bool True when an unhealthy row was automatically removed. / 自动清除异常锁时返回 true。
     */
    private static function recoverUnhealthyLock(int $salesUserId): bool
    {
        self::ensureTable();
        $rowStmt = Database::connection()->prepare(
            'SELECT sales_user_id, lock_token, started_at,
                    TIMESTAMPDIFF(SECOND, started_at, NOW()) AS age_seconds
             FROM cdsp_inspection_locks
             WHERE sales_user_id=?
             LIMIT 1'
        );
        $rowStmt->execute([$salesUserId]);
        $row = $rowStmt->fetch();
        if (!$row) {
            return false;
        }

        $token = (string)$row['lock_token'];
        $leaseName = self::leaseName($token);
        $probe = Database::connection()->prepare('SELECT IS_USED_LOCK(?)');
        $probe->execute([$leaseName]);
        $ownerConnectionId = $probe->fetchColumn();
        $leaseAlive = $ownerConnectionId !== false && $ownerConnectionId !== null;
        $ageSeconds = max(0, (int)($row['age_seconds'] ?? 0));
        $hardTimeout = self::hardRecoverySeconds();

        if ($leaseAlive && $ageSeconds < $hardTimeout) {
            return false;
        }

        $delete = Database::connection()->prepare(
            'DELETE FROM cdsp_inspection_locks WHERE sales_user_id=? AND lock_token=?'
        );
        $delete->execute([$salesUserId, $token]);
        if ($delete->rowCount() !== 1) {
            return false;
        }

        Logger::log(
            'warning',
            $leaseAlive
                ? 'Marketplace verification lock auto-recovered after hard safety timeout'
                : 'Orphaned Marketplace verification lock auto-recovered',
            [
                'event' => $leaseAlive
                    ? 'inspection_process_lock_auto_recovered_timeout'
                    : 'inspection_process_lock_auto_recovered_orphan',
                'sales_user_id' => $salesUserId,
                'age_seconds' => $ageSeconds,
                'hard_timeout_seconds' => $hardTimeout,
            ],
            'post-inspector'
        );

        return true;
    }

    /**
     * EN: Acquire the Sales user's verification gate immediately without waiting.
     * 中文：立即尝试获取该 Sales 用户的验证门锁，不等待其他验证完成。
     *
     * EN: If an older durable row has lost its liveness lease, it is recovered automatically and
     * acquisition is retried once. A hard timeout also prevents a connected-but-stuck request from
     * blocking the Sales user forever.
     * 中文：若旧锁记录已失去存活 lease，会先自动恢复并重试一次；硬性超时还能避免连接仍在但请求
     * 实际卡死时永久阻塞 Sales。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @param string $platform Marketplace platform name. / Marketplace 平台名称。
     * @param string $url Normalized listing URL. / 标准化后的帖子 URL。
     * @return bool True when this request acquired the lock. / 当前请求成功获得锁时返回 true。
     */
    public static function acquire(int $salesUserId, string $platform = '', string $url = ''): bool
    {
        self::ensureTable();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = bin2hex(random_bytes(32));
            [$leasePdo, $leaseName] = self::acquireLease($token);

            try {
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
            } catch (\Throwable $e) {
                self::releaseLease($leasePdo, $leaseName);
                throw $e;
            }

            if ($stmt->rowCount() === 1) {
                self::$ownedTokens[$salesUserId] = $token;
                self::$leaseConnections[$salesUserId] = $leasePdo;
                self::$leaseNames[$salesUserId] = $leaseName;
                self::registerShutdownRecovery();
                return true;
            }

            self::releaseLease($leasePdo, $leaseName);
            unset($leasePdo);

            if ($attempt === 0 && self::recoverUnhealthyLock($salesUserId)) {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * EN: Check whether the Sales user currently has a healthy verification gate record.
     * 中文：检查该 Sales 用户当前是否存在健康的验证锁记录。
     *
     * Orphaned or timed-out locks are recovered during the read, so a page reload or reopened
     * Submit modal can self-heal without Admin action.
     * 读取状态时会自动恢复孤儿锁或超时锁，因此刷新页面或重新打开 Submit 弹窗即可自愈，
     * 不需要 Admin 介入。
     *
     * @param int $salesUserId Internal Sales user row ID. / Sales 用户内部记录 ID。
     * @return bool True while a healthy verification gate is active. / 健康验证锁存在时返回 true。
     */
    public static function isLocked(int $salesUserId): bool
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM cdsp_inspection_locks WHERE sales_user_id=? LIMIT 1'
        );
        $stmt->execute([$salesUserId]);
        if (!(bool)$stmt->fetchColumn()) {
            return false;
        }

        if (self::recoverUnhealthyLock($salesUserId)) {
            return false;
        }

        return true;
    }

    /**
     * EN: Release only the lock token owned by this request, then release its dedicated liveness lease.
     * 中文：仅释放当前请求自己持有的 token，并释放其独立存活 lease，防止误删后来建立的新锁。
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

        try {
            $stmt = Database::connection()->prepare(
                'DELETE FROM cdsp_inspection_locks WHERE sales_user_id=? AND lock_token=?'
            );
            $stmt->execute([$salesUserId, $token]);
        } finally {
            $leasePdo = self::$leaseConnections[$salesUserId] ?? null;
            $leaseName = self::$leaseNames[$salesUserId] ?? self::leaseName($token);
            if ($leasePdo instanceof PDO) {
                self::releaseLease($leasePdo, $leaseName);
            }
            unset(
                self::$ownedTokens[$salesUserId],
                self::$leaseConnections[$salesUserId],
                self::$leaseNames[$salesUserId]
            );
        }
    }

    /**
     * EN: Register a shutdown callback once so fatal request termination gets one final release attempt.
     * 中文：仅注册一次 shutdown 回调，让 Fatal/request termination 仍有最后一次自动释放机会。
     *
     * @return void No value is returned. / 无返回值。
     */
    private static function registerShutdownRecovery(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            self::releaseAllOwned();
        });
    }

    /**
     * EN: Release every verification lock still owned by this PHP request during shutdown.
     * 中文：在 PHP 请求结束时释放本请求仍持有的全部验证锁。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function releaseAllOwned(): void
    {
        foreach (array_keys(self::$ownedTokens) as $salesUserId) {
            try {
                self::release((int)$salesUserId);
            } catch (\Throwable $e) {
                try {
                    Logger::exception(
                        $e,
                        'post-inspector',
                        [
                            'event' => 'Inspection process lock shutdown release failed',
                            'sales_user_id' => (int)$salesUserId,
                        ],
                        'warning'
                    );
                } catch (\Throwable $ignore) {
                    // EN: Process teardown still closes the dedicated lease connection automatically.
                    // 中文：即使日志也失败，进程结束仍会自动关闭独立 lease 连接。
                }
            }
        }
    }

    /**
     * EN: Return healthy active verification locks with Sales names for the Admin Settings fallback panel.
     * 中文：返回健康的当前验证锁及 Sales 名称，供 Admin Settings 的兜底面板显示。
     *
     * The list read itself performs recovery, so Settings never keeps presenting an orphaned lock that
     * can already be removed automatically.
     * 列表读取本身也会执行自愈，因此 Settings 不会继续显示已经可以自动清理的孤儿锁。
     *
     * @return array<int,array<string,mixed>> Healthy active lock rows. / 健康的当前锁记录。
     */
    public static function activeLocks(): array
    {
        self::ensureTable();
        $ids = Database::connection()->query(
            'SELECT sales_user_id FROM cdsp_inspection_locks ORDER BY sales_user_id ASC'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($ids as $salesUserId) {
            self::recoverUnhealthyLock((int)$salesUserId);
        }

        $hardTimeout = self::hardRecoverySeconds();
        return Database::connection()->query(
            "SELECT
                l.sales_user_id,
                l.platform,
                l.started_at,
                l.updated_at,
                DATE_ADD(l.started_at, INTERVAL {$hardTimeout} SECOND) AS auto_recover_after,
                u.sales_id,
                u.display_name
             FROM cdsp_inspection_locks l
             LEFT JOIN cdsp_users u ON u.id=l.sales_user_id
             ORDER BY l.started_at ASC, l.sales_user_id ASC"
        )->fetchAll() ?: [];
    }

    /**
     * EN: Force-clear one Sales verification gate from Admin Settings as an in-case fallback.
     * 中文：作为自动恢复之外的兜底手段，由 Admin Settings 手动清除指定 Sales 的验证门锁。
     *
     * EN: This intentionally removes only the durable gate row. An older provider request that is
     * still running keeps its token-specific liveness connection, but because the new request gets a
     * different token it can proceed after the Admin explicitly chooses Manual Unlock. The old
     * request's token check prevents its late release from deleting the newer row.
     * 中文：该操作只删除持久化门锁记录。若旧 Provider 请求仍在执行，它会继续持有自己 token 专属的
     * 存活连接；但新请求会使用不同 token，因此 Admin 明确点击 Manual Unlock 后仍可继续。旧请求结束时
     * 的 token 校验会阻止它误删后来建立的新锁。
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
