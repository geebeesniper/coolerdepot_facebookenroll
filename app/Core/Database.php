<?php
namespace App\Core;
use PDO;

class Database {
    private static $pdo;
    public static function connection(): PDO {
        if (self::$pdo instanceof PDO) return self::$pdo;
        global $config;
        $d=$config['db'];
        $dsn="mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";
        self::$pdo=new PDO($dsn,$d['user'],$d['pass'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]);
        return self::$pdo;
    }
}
