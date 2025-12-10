<?php
// 轻量前端控制器（public/index.php）

// 错误显示（开发时开启）
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

// 自动加载 app/controllers 和 app/models
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/controllers/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 获取请求路径
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
$path = '/' . trim(substr($uri, strlen($base)), '/');

// 启动路由
$router = new Router($path, $_SERVER['REQUEST_METHOD']);
$router->dispatch();
