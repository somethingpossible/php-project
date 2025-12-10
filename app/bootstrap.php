<?php
// Very small autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative_class = substr($class, strlen($prefix));
    $path = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple helper for rendering views
function view($path, $data = []) {
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/Views/' . $path . '.php';
    if (!file_exists($viewFile)) {
        throw new Exception("View not found: $viewFile");
    }
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . '/Views/layout.php';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($key, $value = null) {
    if ($value === null) {
        if (isset($_SESSION['flash'][$key])) {
            $v = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $v;
        }
        return null;
    }
    $_SESSION['flash'][$key] = $value;
}

?>
