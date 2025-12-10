<?php
class Router
{
    protected $path;
    protected $method;

    public function __construct($path, $method = 'GET')
    {
        $this->path = $path ?: '/';
        $this->method = $method;
    }

    public function dispatch()
    {
        // 路径格式 /controller/action/param1/param2
        $segments = array_values(array_filter(explode('/', $this->path)));
        $controller = !empty($segments[0]) ? ucfirst($segments[0]) . 'Controller' : 'HomeController';
        $action = $segments[1] ?? 'index';
        $params = array_slice($segments, 2);

        $controllerFile = __DIR__ . '/../app/controllers/' . $controller . '.php';
        if (!file_exists($controllerFile)) {
            http_response_code(404);
            echo "Controller $controller not found.";
            exit;
        }

        require_once $controllerFile;
        if (!class_exists($controller)) {
            http_response_code(500);
            echo "Controller class $controller missing.";
            exit;
        }

        $c = new $controller();
        if (!method_exists($c, $action)) {
            if (method_exists($c, '__call')) {
                // 可让控制器处理动态 action
                call_user_func_array([$c, $action], $params);
            } else {
                http_response_code(404);
                echo "Action $action not found in $controller.";
            }
            return;
        }

        call_user_func_array([$c, $action], $params);
    }
}
