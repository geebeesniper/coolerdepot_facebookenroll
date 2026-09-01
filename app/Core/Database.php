<?php
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
