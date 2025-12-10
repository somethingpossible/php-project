<?php
namespace App\Models;

class Note
{
    protected static function pdo()
    {
        return \App\Config::pdo();
    }

    public static function all()
    {
        $stmt = self::pdo()->query('SELECT * FROM notes ORDER BY id DESC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find($id)
    {
        $stmt = self::pdo()->prepare('SELECT * FROM notes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        $stmt = self::pdo()->prepare('INSERT INTO notes (title, content, created_at) VALUES (:title, :content, :created_at)');
        $stmt->execute([
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':created_at' => date('c'),
        ]);
        return self::pdo()->lastInsertId();
    }
}

?>
