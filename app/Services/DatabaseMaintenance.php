<?php
/**
 * File / 文件：app/Services/DatabaseMaintenance.php
 * EN: Provides browser-accessible database diagnostics, safe repair actions, and a guarded SQL console for authenticated Admin users.
 * 中文：为已认证 Admin 提供可通过浏览器使用的数据库诊断、安全修复操作及受保护的 SQL 控制台。
 * Maintenance / 维护：Keep SQL execution behind Admin authentication, CSRF validation, single-statement enforcement, and audit logging.
 * 维护要求：SQL 执行必须始终受 Admin 身份验证、CSRF、单语句限制及审计日志保护。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Setting;
use PDO;
use RuntimeException;
use Throwable;

/**
 * EN: Database maintenance service used by the Admin browser maintenance page.
 * 中文：Admin 浏览器维护页面使用的数据库维护服务。
 */
final class DatabaseMaintenance
{
    private const READ_STATEMENTS = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
    private const WRITE_STATEMENTS = ['ALTER', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'REPLACE'];

    /**
     * EN: Return current database compatibility checks without changing data or schema.
     * 中文：返回当前数据库兼容性检查结果，不修改数据或表结构。
     *
     * @return array Structured compatibility status. / 结构化兼容状态。
     */
    public static function status(): array
    {
        // EN: Diagnostics must never take the Admin page down. Each database
        // probe is isolated because shared-hosting/remote MySQL accounts may
        // restrict information_schema or SHOW metadata even while normal CRUD
        // queries work correctly.
        // 中文：诊断页面绝不能因为数据库探测失败而导致整页 500。远程/共享
        // MySQL 账号可能限制 information_schema 或 SHOW 元数据权限，因此每个
        // 检查都独立捕获异常，并把具体错误返回到页面显示。
        $status = [
            'provider_registry' => [
                'table_exists' => false,
                'provider_count' => null,
                'flag' => null,
                'healthy' => false,
                'repairable' => false,
                'error' => null,
            ],
            'inspection_manual_pending' => [
                'table_exists' => false,
                'column_type' => '',
                'healthy' => false,
                'error' => null,
            ],
            'post_manual_pending' => [
                'table_exists' => false,
                'column_type' => '',
                'healthy' => false,
                'error' => null,
            ],
        ];

        try {
            $pdo = Database::connection();
        } catch (Throwable $e) {
            Logger::exception($e, 'maintenance', ['event' => 'Database maintenance connection failed'], 'error');
            $message = 'Database connection failed: ' . $e->getMessage();
            foreach ($status as &$row) {
                $row['error'] = $message;
            }
            unset($row);
            return $status;
        }

        try {
            $providerTable = self::tableExists($pdo, 'cdsp_provider_profiles');
            $providerCount = $providerTable ? self::tableCount($pdo, 'cdsp_provider_profiles') : 0;
            $registryFlag = null;
            try {
                $registryFlag = Setting::get('provider_registry_enabled', null);
            } catch (Throwable $e) {
                Logger::exception($e, 'maintenance', ['event' => 'Provider registry flag check failed'], 'warning');
                $status['provider_registry']['error'] = 'Provider flag check failed: ' . $e->getMessage();
            }

            $status['provider_registry']['table_exists'] = $providerTable;
            $status['provider_registry']['provider_count'] = $providerCount;
            $status['provider_registry']['flag'] = $registryFlag;
            $status['provider_registry']['healthy'] = $providerTable && ($providerCount === 0 || $registryFlag === '1');
            $status['provider_registry']['repairable'] = $providerTable && $providerCount > 0 && $registryFlag !== '1';
        } catch (Throwable $e) {
            Logger::exception($e, 'maintenance', ['event' => 'Provider Registry diagnostic failed'], 'warning');
            $status['provider_registry']['error'] = 'Provider Registry check failed: ' . $e->getMessage();
        }

        foreach ([
            'inspection_manual_pending' => ['table' => 'cdsp_post_inspections', 'column' => 'verification_status'],
            'post_manual_pending' => ['table' => 'cdsp_sales_posts', 'column' => 'verification_status'],
        ] as $key => $target) {
            try {
                $tableExists = self::tableExists($pdo, $target['table']);
                $info = $tableExists ? self::columnInfo($pdo, $target['table'], $target['column']) : null;
                $status[$key]['table_exists'] = $tableExists;
                $status[$key]['column_type'] = (string)($info['Type'] ?? '');
                $status[$key]['healthy'] = self::enumContains($info, 'manual_pending');
            } catch (Throwable $e) {
                Logger::exception(
                    $e,
                    'maintenance',
                    ['event' => 'Verification status diagnostic failed', 'table' => $target['table']],
                    'warning'
                );
                $status[$key]['error'] = $target['table'] . ' check failed: ' . $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * EN: Run only the known, idempotent compatibility repairs required by recent application versions.
     * 中文：仅运行近期版本所需、已知且可重复安全执行的兼容修复。
     *
     * @param int $adminId Authenticated Admin user ID for audit attribution. / 用于审计归属的已认证 Admin 用户 ID。
     * @return array Repair results. / 修复结果。
     */
    public static function runRecommendedRepairs(int $adminId): array
    {
        $pdo = Database::connection();
        $results = [];

        try {
            $providerTable = self::tableExists($pdo, 'cdsp_provider_profiles');
            $providerCount = $providerTable ? self::tableCount($pdo, 'cdsp_provider_profiles') : 0;
            $flag = Setting::get('provider_registry_enabled', null);

            if ($providerTable && $providerCount > 0 && $flag !== '1') {
                Setting::set('provider_registry_enabled', '1', $adminId, false);
                $results[] = [
                    'key' => 'provider_registry_enabled',
                    'status' => 'applied',
                    'message' => 'Provider Registry flag was set to 1.',
                ];
            } else {
                $results[] = [
                    'key' => 'provider_registry_enabled',
                    'status' => 'skipped',
                    'message' => $providerTable
                        ? ($providerCount > 0 ? 'Provider Registry flag is already ready.' : 'No provider profiles exist, so the flag was not forced.')
                        : 'Provider profile table does not exist.',
                ];
            }
        } catch (Throwable $e) {
            Logger::exception($e, 'maintenance', ['event' => 'Provider Registry repair failed'], 'error');
            $results[] = [
                'key' => 'provider_registry_enabled',
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }

        foreach ([
            ['table' => 'cdsp_post_inspections', 'column' => 'verification_status'],
            ['table' => 'cdsp_sales_posts', 'column' => 'verification_status'],
        ] as $target) {
            try {
                $table = $target['table'];
                $column = $target['column'];
                if (!self::tableExists($pdo, $table)) {
                    $results[] = [
                        'key' => $table . '.' . $column,
                        'status' => 'failed',
                        'message' => 'Required table does not exist.',
                    ];
                    continue;
                }

                $info = self::columnInfo($pdo, $table, $column);
                if (!$info) {
                    $results[] = [
                        'key' => $table . '.' . $column,
                        'status' => 'failed',
                        'message' => 'Required column does not exist.',
                    ];
                    continue;
                }

                if (self::enumContains($info, 'manual_pending')) {
                    $results[] = [
                        'key' => $table . '.' . $column,
                        'status' => 'skipped',
                        'message' => 'manual_pending is already available.',
                    ];
                    continue;
                }

                self::appendEnumValue($pdo, $table, $column, $info, 'manual_pending');
                $results[] = [
                    'key' => $table . '.' . $column,
                    'status' => 'applied',
                    'message' => 'manual_pending was added to the ENUM.',
                ];
            } catch (Throwable $e) {
                Logger::exception(
                    $e,
                    'maintenance',
                    ['event' => 'Verification status ENUM repair failed', 'table' => $target['table']],
                    'error'
                );
                $results[] = [
                    'key' => $target['table'] . '.' . $target['column'],
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        Logger::info(
            'Admin ran recommended database repairs.',
            [
                'admin_id' => $adminId,
                'results' => array_map(
                    static fn(array $row): array => [
                        'key' => (string)$row['key'],
                        'status' => (string)$row['status'],
                    ],
                    $results
                ),
            ],
            'maintenance'
        );

        return $results;
    }

    /**
     * EN: Execute one guarded SQL statement from the Admin maintenance console.
     * 中文：从 Admin 维护控制台执行一条受保护的 SQL 语句。
     *
     * @param string $sql SQL statement supplied by the Admin. / Admin 提交的 SQL 语句。
     * @param string $mode Execution mode: read or write. / 执行模式：read 或 write。
     * @param int $adminId Authenticated Admin user ID. / 已认证 Admin 用户 ID。
     * @return array Query result metadata and at most 200 rows. / 查询结果元数据及最多 200 行数据。
     */
    public static function executeSql(string $sql, string $mode, int $adminId): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new RuntimeException('SQL is required.');
        }
        if (strlen($sql) > 20000) {
            throw new RuntimeException('SQL is too long. Maximum length is 20,000 characters.');
        }

        $sql = preg_replace('/;\s*$/', '', $sql) ?? $sql;
        if (strpos($sql, ';') !== false) {
            throw new RuntimeException('Only one SQL statement can be executed at a time.');
        }

        $clean = self::stripLeadingComments($sql);
        if (!preg_match('/^([A-Za-z]+)/', $clean, $match)) {
            throw new RuntimeException('Unable to determine SQL statement type.');
        }
        $statement = strtoupper($match[1]);
        $mode = strtolower($mode) === 'write' ? 'write' : 'read';

        self::assertNoDangerousSql($clean);

        if ($mode === 'read' && !in_array($statement, self::READ_STATEMENTS, true)) {
            throw new RuntimeException('Read Only mode accepts SELECT, SHOW, DESCRIBE/DESC, or EXPLAIN statements only.');
        }
        if ($mode === 'write' && !in_array($statement, self::WRITE_STATEMENTS, true)) {
            throw new RuntimeException('Write mode accepts ALTER, INSERT, UPDATE, DELETE, CREATE, or REPLACE statements only.');
        }

        $hash = hash('sha256', $sql);
        $pdo = Database::connection();

        try {
            if ($mode === 'read') {
                $stmt = $pdo->query($sql);
                if (!$stmt) {
                    throw new RuntimeException('The database did not return a result set.');
                }
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $truncated = count($rows) > 200;
                if ($truncated) {
                    $rows = array_slice($rows, 0, 200);
                }

                Logger::info(
                    'Admin maintenance read SQL executed.',
                    [
                        'admin_id' => $adminId,
                        'statement' => $statement,
                        'query_sha256' => $hash,
                        'rows_returned' => count($rows),
                        'truncated' => $truncated,
                    ],
                    'maintenance'
                );

                return [
                    'mode' => 'read',
                    'statement' => $statement,
                    'rows' => $rows,
                    'columns' => $rows ? array_keys($rows[0]) : [],
                    'row_count' => count($rows),
                    'truncated' => $truncated,
                    'query_sha256' => $hash,
                ];
            }

            $affected = $pdo->exec($sql);
            if ($affected === false) {
                throw new RuntimeException('The database did not complete the statement.');
            }

            Logger::warning(
                'Admin maintenance write SQL executed.',
                [
                    'admin_id' => $adminId,
                    'statement' => $statement,
                    'query_sha256' => $hash,
                    'affected_rows' => $affected,
                ],
                'maintenance'
            );

            return [
                'mode' => 'write',
                'statement' => $statement,
                'affected_rows' => $affected,
                'query_sha256' => $hash,
            ];
        } catch (Throwable $e) {
            Logger::exception(
                $e,
                'maintenance',
                [
                    'event' => 'Admin maintenance SQL failed',
                    'admin_id' => $adminId,
                    'statement' => $statement,
                    'query_sha256' => $hash,
                ],
                'error'
            );
            throw $e;
        }
    }

    /**
     * EN: Check whether a table exists in the current application database.
     * 中文：检查当前应用数据库中是否存在指定表。
     */
    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * EN: Count rows from one trusted table name used by compatibility checks.
     * 中文：统计兼容性检查所使用的受信任表中的记录数。
     */
    private static function tableCount(PDO $pdo, string $table): int
    {
        self::assertTrustedIdentifier($table);
        return (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    /**
     * EN: Read SHOW COLUMNS metadata for one trusted table and column.
     * 中文：读取指定受信任表和字段的 SHOW COLUMNS 元数据。
     */
    private static function columnInfo(PDO $pdo, string $table, string $column): ?array
    {
        if (!self::tableExists($pdo, $table)) {
            return null;
        }

        self::assertTrustedIdentifier($table);
        self::assertTrustedIdentifier($column);

        // EN: Do not parameterize SHOW COLUMNS ... LIKE ?. With native PDO
        // prepares enabled (ATTR_EMULATE_PREPARES=false), MySQL/MariaDB can
        // reject the placeholder in SHOW metadata syntax with SQL 1064. Use
        // information_schema.columns instead because it is a normal SELECT
        // and safely supports bound parameters on both engines.
        // 中文：不要在 SHOW COLUMNS ... LIKE 中使用 ? 占位符。项目关闭了
        // PDO 模拟预处理后，MySQL/MariaDB 可能直接在 SHOW 元数据语法的
        // ? 位置报 1064。这里改用普通 SELECT 查询 information_schema，
        // MySQL 与 MariaDB 都可以安全绑定参数。
        $stmt = $pdo->prepare(
            'SELECT COLUMN_TYPE AS `Type`, IS_NULLABLE AS `Null`, COLUMN_DEFAULT AS `Default` '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * EN: Check whether a SHOW COLUMNS ENUM type contains the requested value.
     * 中文：检查 SHOW COLUMNS 返回的 ENUM 类型是否包含指定值。
     */
    private static function enumContains(?array $columnInfo, string $value): bool
    {
        if (!$columnInfo) {
            return false;
        }
        $type = strtolower((string)($columnInfo['Type'] ?? ''));
        return str_contains($type, "'" . strtolower($value) . "'");
    }

    /**
     * EN: Append one ENUM value while preserving current values, NULL behavior, and default.
     * 中文：在保留当前 ENUM 值、NULL 属性和默认值的前提下追加一个 ENUM 值。
     */
    private static function appendEnumValue(
        PDO $pdo,
        string $table,
        string $column,
        array $columnInfo,
        string $newValue
    ): void {
        self::assertTrustedIdentifier($table);
        self::assertTrustedIdentifier($column);

        $type = (string)($columnInfo['Type'] ?? '');
        if (!preg_match('/^enum\((.*)\)$/i', $type, $match)) {
            throw new RuntimeException($table . '.' . $column . ' is not an ENUM column.');
        }

        preg_match_all("/'((?:''|[^'])*)'/", $match[1], $matches);
        $values = array_map(
            static fn(string $value): string => str_replace("''", "'", $value),
            $matches[1] ?? []
        );
        if (!$values) {
            throw new RuntimeException('Unable to parse existing ENUM values for ' . $table . '.' . $column . '.');
        }
        if (!in_array($newValue, $values, true)) {
            $failedIndex = array_search('failed', $values, true);
            if ($failedIndex === false) {
                $values[] = $newValue;
            } else {
                array_splice($values, (int)$failedIndex, 0, [$newValue]);
            }
        }

        $quotedValues = implode(',', array_map([$pdo, 'quote'], $values));
        $nullable = strtoupper((string)($columnInfo['Null'] ?? 'NO')) === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $columnInfo['Default'] ?? null;
        $defaultSql = $default === null ? '' : ' DEFAULT ' . $pdo->quote((string)$default);

        $sql = 'ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $column . '` ENUM('
            . $quotedValues . ')' . $nullable . $defaultSql;
        $pdo->exec($sql);
    }

    /**
     * EN: Remove leading SQL comments before statement-type validation.
     * 中文：在判断 SQL 语句类型前移除开头注释。
     */
    private static function stripLeadingComments(string $sql): string
    {
        $previous = null;
        while ($previous !== $sql) {
            $previous = $sql;
            $sql = preg_replace('/^\s*(?:--[^\r\n]*(?:\r?\n|$)|#[^\r\n]*(?:\r?\n|$)|\/\*.*?\*\/\s*)/s', '', $sql) ?? $sql;
        }
        return ltrim($sql);
    }

    /**
     * EN: Reject statements and functions that are too dangerous for the browser maintenance console.
     * 中文：拒绝不适合通过浏览器维护控制台执行的高风险语句及函数。
     */
    private static function assertNoDangerousSql(string $sql): void
    {
        $blocked = [
            '/\bDROP\s+(?:DATABASE|SCHEMA|USER)\b/i',
            '/\bTRUNCATE\b/i',
            '/\bGRANT\b/i',
            '/\bREVOKE\b/i',
            '/\bLOAD\s+DATA\b/i',
            '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i',
            '/\bLOAD_FILE\s*\(/i',
            '/\bSHUTDOWN\b/i',
            '/\bSET\s+(?:GLOBAL|PERSIST)\b/i',
            '/\bKILL\b/i',
            '/\bSLEEP\s*\(/i',
            '/\bBENCHMARK\s*\(/i',
        ];

        foreach ($blocked as $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new RuntimeException('This SQL statement is blocked by the browser maintenance safety policy.');
            }
        }
    }

    /**
     * EN: Allow only hard-coded SQL identifiers used by this service.
     * 中文：仅允许本服务内部硬编码使用的安全 SQL 标识符。
     */
    private static function assertTrustedIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unsafe SQL identifier.');
        }
    }
}
