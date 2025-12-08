<?php
    require __DIR__ . '/../php/db_connect.php';

    $sql = "CREATE TABLE IF NOT EXISTS tables (
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT '球桌ID',
    table_number VARCHAR(10) NOT NULL COMMENT '球桌编号',
    status ENUM('empty', 'one', 'full') NOT NULL DEFAULT 'empty' COMMENT '状态：空桌/1人/满员',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY uk_table_number (table_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='球桌信息表';";

    $result = $pdo->query($sql);
    if($result) echo '建表成功';
?>