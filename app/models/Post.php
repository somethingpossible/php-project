<?php
// app/models/Post.php
class Post extends Model
{
    protected $table = 'posts';

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($title, $content)
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (title, content, created_at) VALUES (:title, :content, NOW())");
        return $stmt->execute(['title' => $title, 'content' => $content]);
    }

    public function like($id)
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET likes = likes + 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
