<?php
namespace App;

class Config
{
    public static function dbPath()
    {
        return __DIR__ . '/../data/database.sqlite';
    }

    public static function pdo()
    {
        static $pdo;
        if ($pdo) return $pdo;
        $path = self::dbPath();
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $dsn = 'sqlite:' . $path;
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Ensure notes table exists (simple migration)
        $pdo->exec("CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            created_at TEXT
        )");
        return $pdo;
    }
}

?>
