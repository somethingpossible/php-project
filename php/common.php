<?php
/**
 * 公共工具文件 - Session 版本（修复 ini_set 警告）
 * 核心修复：先配置 ini 参数，再启动 Session
 */

// 第一步：先修改 Session 相关 ini 配置（必须在 session_start() 之前）
ini_set('session.cookie_httponly', 'On'); // 防止JS读取，避免Session劫持
ini_set('session.cookie_samesite', 'Lax'); // 限制跨域访问
ini_set('session.gc_maxlifetime', 7200); // Session 服务器端有效期2小时
ini_set('session.cookie_lifetime', 7200); // Session Cookie 客户端有效期2小时

// 第二步：启动 Session（此时 ini 配置已生效，无警告）
if (!isset($_SESSION)) {
    session_start();
}

// 全局核心配置
define('SESSION_USER_KEY', 'login_user'); // Session 中存储用户信息的键名
define('SESSION_EXPIRE', 7200); // Session 有效期（2小时，单位：秒）

/**
 * 获取 Session 中的用户信息
 * @return array|false 成功返回用户信息数组，失败返回false
 */
function getUserInfo() {
    // 1. 检查 Session 中是否存在用户信息
    if (!isset($_SESSION[SESSION_USER_KEY])) {
        return false;
    }

    $user_info = $_SESSION[SESSION_USER_KEY];

    // 2. 验证数据完整性和过期时间
    if (!isset($user_info['id'], $user_info['username'], $user_info['expire'])) {
        return false;
    }

    // 3. 检查是否过期
    if ($user_info['expire'] < time()) {
        // 过期则清除 Session
        unset($_SESSION[SESSION_USER_KEY]);
        return false;
    }

    // 4. 自动延长 Session 有效期（每次访问刷新过期时间）
    $user_info['expire'] = time() + SESSION_EXPIRE;
    $_SESSION[SESSION_USER_KEY] = $user_info;

    return $user_info;
}

/**
 * 验证用户是否已登录（简化封装）
 * @return bool 已登录返回true，未登录跳转登录页并终止脚本
 */
function checkLogin() {
    $user = getUserInfo();
    if (!$user) {
        header('Location: login.php'); // 未登录跳转到登录页
        exit; // 终止后续代码执行
    }
    return true;
}

/**
 * 存储用户信息到 Session（登录成功时调用）
 * @param array $user_data 用户信息数组（需包含 id, username）
 * @return void
 */
function setUserSession($user_data) {
    // 补充过期时间
    $user_info = [
        'id' => $user_data['id'],
        'username' => $user_data['username'],
        'expire' => time() + SESSION_EXPIRE // 有效期2小时
    ];
    $_SESSION[SESSION_USER_KEY] = $user_info;
}

/**
 * 清除用户 Session（登出时调用）
 * @return void
 */
function clearUserSession() {
    if (isset($_SESSION[SESSION_USER_KEY])) {
        unset($_SESSION[SESSION_USER_KEY]);
    }
    // 可选：销毁整个 Session（如果不需要保留其他 Session 数据）
    // session_destroy();
}

/**
 * 判断用户是否为管理员
 * @param int $user_id 用户ID
 * @return bool true=管理员，false=普通用户
 */
function isAdmin($user_id) {
    global $pdo; // 或重新连接数据库（根据common.php现有逻辑调整）
    
    // 方式1：如果common.php已连接数据库，直接使用全局$pdo
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $role = $stmt->fetchColumn() ?? 'user';
    
    return $role === 'admin';

    // 方式2：如果common.php未连接数据库，重新连接
    /*
    require __DIR__ . '/db_connect.php';
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $role = $stmt->fetchColumn() ?? 'user';
    return $role === 'admin';
    */
}
?>