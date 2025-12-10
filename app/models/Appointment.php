<?php
// app/models/Appointment.php
class Appointment extends Model
{
    protected $table = 'appointments';

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function book($name, $phone)
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (name, phone, created_at) VALUES (:name, :phone, NOW())");
        return $stmt->execute(['name' => $name, 'phone' => $phone]);
    }
}
