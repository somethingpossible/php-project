<?php
/**
 * 登录处理逻辑 - 适配：账号/手机号双登录
 */

// 引入公共工具（已包含 session_start() 和 Session 配置）
include 'common.php';

$error = '';
$successMessage = '';

// 已登录用户直接跳转首页
$user = getUserInfo();
if ($user) {
    header('Location: ../indexs.php?message=' . urlencode('欢迎回来，' . $user['username'] . '！'));
    exit;
}

// 接收注册成功后的提示信息
if (isset($_GET['message'])) {
    $successMessage = htmlspecialchars($_GET['message']);
}

// 处理登录提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginAccount = trim($_POST['username'] ?? ''); // 账号/手机号
    $password = $_POST['password'] ?? '';

    // 简单输入验证
    if (empty($loginAccount) || empty($password)) {
        $error = '请填写账号/手机号和密码！';
    } else {
        try {
            // 连接数据库查询用户（匹配 account 或 approach 字段）
            include 'db_connect.php';
            $stmt = $pdo->prepare("
                SELECT id, account, username, password, role, approach 
                FROM users 
                WHERE account = :loginAccount OR approach = :loginAccount
            ");
            $stmt->bindValue(':loginAccount', $loginAccount, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 验证密码
            if (!$user || !password_verify($password, $user['password'])) {
                $error = '账号/手机号或密码错误！';
            } else {
                // 登录成功：按 common.php 规则存储Session（包含过期时间）
                setUserSession([
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'account' => $user['account'], // 存储账号
                    'phone' => $user['approach'], // 存储手机号
                    'role' => $user['role'] // 存储角色
                ]);

                // 跳转首页
                header('Location: ../indexs.php?message=' . urlencode('登录成功，欢迎使用！'));
                exit;
            }
        } catch (PDOException $e) {
            $error = '登录失败：数据库错误（' . $e->getMessage() . '）';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <title>乐动球馆登录</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Microsoft YaHei", sans-serif;
            background-color: #f5f5f5;
        }
        .container {
            width: 320px;
            margin: 80px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
            font-size: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40,167,69,0.25);
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #218838;
        }
        .message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-tip {
            text-align: center;
            margin-top: -10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
        }
        .register-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        .register-link a {
            color: #28a745;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>乐动球馆登录</h2>
        
        <!-- 成功提示（注册后跳转） -->
        <?php if ($successMessage): ?>
            <div class="message success"><?= $successMessage ?></div>
        <?php endif; ?>
        
        <!-- 错误提示 -->
        <?php if ($error): ?>
            <div class="message error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="账号/手机号" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="login-tip">支持纯数字账号或手机号登录</div>
            <div class="form-group">
                <input type="password" name="password" placeholder="密码" required>
            </div>
            <button type="submit">登录</button>
        </form>

        <div class="register-link">
            没有账号？<a href="../files/register.html">立即注册</a>
        </div>
    </div>
</body>
</html>