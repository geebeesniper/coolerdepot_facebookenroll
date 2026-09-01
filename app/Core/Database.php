<?php
/**
 * File / 文件：app/Core/Database.php
 * EN: Defines the shared Database core infrastructure component.
 * 中文：定义全应用共享的 Database 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

use PDO;
use Throwable;

/**
 * EN: Core infrastructure component that provides database behavior shared across the application.
 * 中文：提供全应用共享 database 能力的核心基础设施组件。
 */
class Database
{
    private static ?PDO $pdo = null;

    /**
     * Create one shared PDO connection for the request.
     *
     * Connection failures are logged before rethrowing. The logger's file sink
     * does not depend on MySQL, so database outages remain diagnosable.
     */
    /**
     * EN: Return the shared PDO connection configured for the application database.
     * 中文：返回根据应用数据库配置建立的共享 PDO 连接。
     *
     * @return PDO PDO database connection used by the application. / 应用使用的 PDO 数据库连接。
     *
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        global $config;
        $d = $config['db'];
        $dsn = "mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";

        try {
            self::$pdo = new PDO($dsn, $d['user'], $d['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            Logger::exception(
                $e,
                'database',
                [
                    'event' => 'Database connection failed',
                    'db_host' => (string)$d['host'],
                    'db_port' => (string)$d['port'],
                    'db_name' => (string)$d['name'],
                ],
                'critical'
            );
            throw $e;
        }

        return self::$pdo;
    }
}
