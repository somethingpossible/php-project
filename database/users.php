<?php
    require __DIR__ . '/../php/db_connect.php';

   

    $sql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,  -- 存储加密后的密码
        `sex` VARCHAR(10) NOT NULL,
        `age` VARCHAR(3) NOT NULL,
        `approach` VARCHAR(15) NOT NULL
    );";

    $result = $pdo->query($sql);
    if($result) echo '建表成功';
?>