<?php
// 引入公共工具文件（已包含 session_start()）
include 'common.php';

$error = '';

// 已登录直接跳转

// 处理登录提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 简单输入验证
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码！';
    } else {
        // 连接数据库查询用户
        include 'db_connect.php';
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 验证密码并设置 Session
        if ($user && password_verify($password, $user['password'])) {
            // 登录成功，存储用户信息到 Session（调用公共函数）
            setUserSession([
                'id' => $user['id'],
                'username' => $user['username']
            ]);

            header('Location: appointment.php');
            exit;
        } else {
            $error = '用户名或密码错误！';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>用户登录</title>
    <meta charset="utf-8">
    <style>
        .container { width: 300px; margin: 50px auto; }
        .error { color: red; margin-bottom: 10px; }
        input { width: 100%; margin: 8px 0; padding: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        a { color: #007bff; text-decoration: none; }
        p { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>用户登录</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="用户名/邮箱" required 
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <input type="password" name="password" placeholder="密码" required>
            <button type="submit">登录</button>
        </form>
        <p>没有账号？<a href="../files/register.html">立即注册</a></p>
    </div>
</body>
</html>