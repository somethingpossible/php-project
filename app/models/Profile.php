<?php
// app/models/Profile.php
class Profile extends Model
{
    protected $table = 'users';

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getComments($userId)
    {
        if (!$userId) return [];
        $stmt = $this->db->prepare("SELECT * FROM comments WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getLikes($userId)
    {
        if (!$userId) return [];
        $stmt = $this->db->prepare("SELECT p.* FROM posts p JOIN likes l ON p.id = l.post_id WHERE l.user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getPosts($userId)
    {
        if (!$userId) return [];
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getDeletedPosts($userId)
    {
        if (!$userId) return [];
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE user_id = :uid AND deleted = 1 ORDER BY created_at DESC");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }
}
