<?php
/**
 * 手机号唯一性验证接口（AJAX请求专用）
 */
header('Content-Type: application/json; charset=utf-8'); // 固定返回JSON格式
require __DIR__ . '/db_connect.php'; // 引入数据库连接

// 接收手机号参数
$phone = trim($_POST['phone'] ?? '');

// 基础验证
if (empty($phone)) {
    echo json_encode(['code' => -1, 'msg' => '手机号不能为空']);
    exit;
}
if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    echo json_encode(['code' => -1, 'msg' => '手机号格式错误']);
    exit;
}

// 查询数据库
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE approach = :phone LIMIT 1");
    $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        // 手机号已存在
        echo json_encode(['code' => 1, 'msg' => '该手机号已注册']);
    } else {
        // 手机号可用
        echo json_encode(['code' => 0, 'msg' => '手机号可用']);
    }
} catch (PDOException $e) {
    // 数据库错误
    echo json_encode(['code' => -2, 'msg' => '数据库错误，请重试']);
}