<?php
/**
 * File / 文件：app/Core/Database.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

use PDO;
use Throwable;

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
     * EN: Implements the application operation `connection` (connection).
     * 中文：实现应用操作 `connection`（connection）。
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
