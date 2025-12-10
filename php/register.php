<?php
/**
 * 注册处理逻辑 - 适配：手机号唯一+纯数字账号+双登录
 */

// 引入公共工具和数据库连接（common.php 已包含 session_start()）
require __DIR__ . '/db_connect.php';
include 'common.php';

// 已登录用户直接跳转首页
$user = getUserInfo();
if ($user) {
    header('Location: ../indexs.php?message=' . urlencode('您已登录，无需重复注册！'));
    exit;
}

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // 接收表单参数（与前端name属性一致）
    $username = trim($_POST['username'] ?? ''); // 姓名（users表的username字段）
    $password = trim($_POST['password'] ?? '');
    $confirm_pwd = trim($_POST['confirm_pwd'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $age = trim($_POST['age'] ?? '');
    $approach = trim($_POST['approach'] ?? ''); // 手机号

    // 基础表单验证
    $errors = [];
    if (empty($username) || strlen($username) > 20) {
        $errors[] = "姓名不能为空且不超过20字符";
    }
    if (empty($password) || strlen($password) < 6 || strlen($password) > 20) {
        $errors[] = "密码不能为空且长度为6-20位";
    }
    if ($password !== $confirm_pwd) {
        $errors[] = "两次密码输入不一致";
    }
    if (empty($sex)) {
        $errors[] = "请选择性别";
    }
    if (empty($age) || !is_numeric($age) || $age < 1 || $age > 120) {
        $errors[] = "年龄格式错误（需为1-120之间的数字）";
    }
    if (empty($approach) || !preg_match('/^1[3-9]\d{9}$/', $approach)) {
        $errors[] = "请输入有效的手机号";
    }

    // 验证失败跳转回注册页
    if (!empty($errors)) {
        $message = implode('；', $errors);
        header("Location: ../files/register.html?message=" . urlencode($message));
        exit;
    }

    // ---------------- 新增：手机号唯一验证 ----------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE approach = :approach");
    $stmt->bindValue(':approach', $approach, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch()) {
        $message = "该手机号已被注册，请更换其他手机号！";
        header("Location: ../files/register.html?message=" . urlencode($message));
        exit;
    }

    // ---------------- 核心：生成纯数字唯一账号 ----------------
    function generateUniqueAccount($pdo) {
        $length = 8; // 纯数字账号长度（8位，可调整为6-11位）
        $chars = '0123456789'; // 仅数字字符池
        $maxAttempts = 15; // 最大重试次数（纯数字重复概率略高，增加重试次数）
        $attempt = 0;

        do {
            $account = '';
            // 生成纯数字账号（确保首位不为0）
            $account .= $chars[mt_rand(1, 9)]; // 首位1-9
            for ($i = 1; $i < $length; $i++) {
                $account .= $chars[mt_rand(0, 9)]; // 后续0-9
            }

            // 检查账号是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE account = :account");
            $stmt->bindValue(':account', $account, PDO::PARAM_STR);
            $stmt->execute();
            $isExists = $stmt->fetch();

            $attempt++;
        } while ($isExists && $attempt < $maxAttempts);

        // 超过重试次数抛出错误
        if ($attempt >= $maxAttempts && $isExists) {
            throw new Exception("账号生成失败，请刷新页面重试");
        }

        return $account;
    }

    try {
        // 生成纯数字唯一账号
        $account = generateUniqueAccount($pdo);

        // 密码加密存储（使用PHP默认强哈希算法）
        $hashedPwd = password_hash($password, PASSWORD_DEFAULT);

        // 插入用户数据（严格对应users表字段）
        $stmt = $pdo->prepare("
            INSERT INTO users (account, username, password, sex, age, approach, created_at)
            VALUES (:account, :username, :password, :sex, :age, :approach, NOW())
        ");

        // 绑定参数
        $stmt->bindValue(':account', $account, PDO::PARAM_STR); // 纯数字唯一账号
        $stmt->bindValue(':username', $username, PDO::PARAM_STR); // 用户输入的姓名
        $stmt->bindValue(':password', $hashedPwd, PDO::PARAM_STR); // 加密后的密码
        $stmt->bindValue(':sex', $sex, PDO::PARAM_STR);
        $stmt->bindValue(':age', $age, PDO::PARAM_STR);
        $stmt->bindValue(':approach', $approach, PDO::PARAM_STR); // 手机号（唯一）

        $stmt->execute();

        // 注册成功，跳转登录页并显示账号
        $message = "注册成功！您的登录账号为：$account（可使用账号或手机号登录），请牢记！";
        header("Location: login.php?message=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        // 捕获数据库错误（如唯一索引冲突）
        $message = "注册失败：数据库错误（" . $e->getMessage() . "）";
        header("Location: ../files/register.html?message=" . urlencode($message));
        exit;
    } catch (Exception $e) {
        $message = "注册失败：" . $e->getMessage();
        header("Location: ../files/register.html?message=" . urlencode($message));
        exit;
    }
}

// 非POST请求直接跳转注册页
header("Location: ../files/register.html");
exit;
?>