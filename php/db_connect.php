<?php
// 数据库配置

try {
    // PDO连接数据库
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=ledong;",'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败：" . $e->getMessage());
}

?>