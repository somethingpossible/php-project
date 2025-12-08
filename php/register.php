<?php
// require __DIR__ . '/../files/register.html';

include 'db_connect.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);         //trim()去除字符串首尾空格和换行
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $sex = ($_POST['sex']);
    $age = (int)($_POST['age']);
    $approach = ($_POST['approach']);
    
    // $email = trim($_POST['email']);
    
    //验证输入
    if (empty($username) || empty($password)) {
        $error = '请填写所有必填字段！';
        if(empty($username)) echo"姓名";
        else echo "密码";
    } 
    elseif ($password !== $confirm_password) {
        $error = '两次密码输入不一致！';
    } 
    else {
        //检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = '用户名或邮箱已被注册！';
        } else {
            //密码加密（使用password_hash）
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 插入数据
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username,password,sex,age,approach) VALUES (?,?,?,?,?)");
                $insertresult = $stmt->execute([$username, $hashed_password, $sex, $age, $approach]);
                
                if (!$insertresult) {
                    $error = '注册失败：' . implode(' ', $stmt->errorInfo());
                } else {
                    echo "注册成功";
                }
            } catch (PDOException $e) {
                $error = '数据库错误：' . $e->getMessage();
            }
            if (!empty($error)){
                echo "<p style='color:red'>$error</p>";
                die();
            }
            //注册成功，跳转到登录页
            header('Location: login.php');
            exit;
        }
    }
    if (!empty($error)){
                echo "<p style='color:red'>$error</p>";
            }
}
?>