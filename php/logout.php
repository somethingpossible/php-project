<?php
// 引入公共工具文件（已包含 session_start()）
include 'common.php';

// 清除用户 Session（登出核心逻辑）
clearUserSession();

// 可选：销毁整个 Session（彻底清除所有 Session 数据）
session_destroy();

// 跳转到登录页
header('Location: login.php');
exit;
?>