<?php
declare(strict_types=1);
final class DB {
    private static ?PDO $pdo = null;
    public static function conn(): PDO {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $d = (require __DIR__ . '/config.php')['db'];
        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s',$d['driver'],$d['host'],$d['port'],$d['name'],$d['charset']);
        self::$pdo = new PDO($dsn,$d['user'],$d['pass'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        return self::$pdo;
    }
    public static function run(string $sql, array $params = []): PDOStatement {
        $st = self::conn()->prepare($sql); $st->execute($params); return $st;
    }
}
