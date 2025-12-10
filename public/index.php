<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/bootstrap.php';

// Simple front controller / router
$route = $_GET['route'] ?? 'home/index';
$parts = explode('/', $route);
$controllerName = !empty($parts[0]) ? $parts[0] : 'home';
$actionName = $parts[1] ?? 'index';

$controllerClass = '\\App\\Controllers\\' . ucfirst($controllerName) . 'Controller';
if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo "Controller not found: $controllerClass";
    exit;
}

$controller = new $controllerClass();
$action = $actionName;
if (!method_exists($controller, $action)) {
    http_response_code(404);
    echo "Action not found: $action";
    exit;
}

// Call action
$controller->$action();

?>
