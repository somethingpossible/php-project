<?php
class Controller
{
    protected $data = [];

    protected function view($path, $data = [])
    {
        $this->data = $data;
        $viewFile = __DIR__ . '/../app/views/' . $path . '.php';
        if (!file_exists($viewFile)) {
            echo "View not found: $viewFile";
            return;
        }
        // 提供简易数据变量
        extract($data);
        require __DIR__ . '/../app/views/layouts/header.php';
        require $viewFile;
        require __DIR__ . '/../app/views/layouts/footer.php';
    }

    protected function json($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
