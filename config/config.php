<?php
return [
    'base_url' => 'http://localhost', // 修改为你的 base url
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'your_db',
        'user' => 'your_user',
        'pass' => 'your_pass',
        'charset' => 'utf8mb4',
    ],
    // 兼容旧代码用常量（可选）
    'BASE_URL' => 'http://localhost',
];

define('BASE_URL', 'http://localhost');
