<?php
// app/models/TableManager.php
class TableManager extends Model
{
    public function listTables()
    {
        $stmt = $this->db->query("SHOW TABLES");
        return $stmt->fetchAll();
    }

    public function repair($table)
    {
        return $this->db->exec("REPAIR TABLE `" . str_replace('`','', $table) . "`");
    }
}
