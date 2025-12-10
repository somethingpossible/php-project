<?php
// app/controllers/Admin/TableManageController.php
namespace Admin;
class TableManageController extends \Controller
{
    protected $tableManager;

    public function __construct()
    {
        $this->tableManager = new \TableManager();
    }

    public function index()
    {
        $tables = $this->tableManager->listTables();
        $this->view('admin/table_manage', ['tables' => $tables]);
    }

    public function repair()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $table = $_POST['table'] ?? '';
            $this->tableManager->repair($table);
            $this->redirect('/admin/table_manage');
        }
    }
}
