<?php
/**
 * 登出处理逻辑 - 适配 common.php 的 Session 清除规则
 */

// 引入公共工具（包含 session_start() 和 clearUserSession() 函数）
include 'common.php';

// 清除用户Session（调用common.php中的函数）
clearUserSession();

// 可选：销毁整个Session（彻底清除所有Session数据）
session_destroy();

// 跳转登录页并提示
header('Location: login.php?message=' . urlencode('登出成功，请重新登录！'));
exit;
?>