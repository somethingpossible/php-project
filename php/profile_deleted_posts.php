<?php
// 引入公共工具文件（登录验证）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$user_id = $user['id'];
$current_username = $user['username'];
$message = '';

// 连接数据库
require __DIR__ . '/db_connect.php';

// 判断当前用户是否为管理员（核心：区分查询范围）
$is_admin = isAdmin($user_id);

// ------------ 处理查询条件 ------------
$search_username = trim($_GET['search_username'] ?? ''); // 用户名搜索关键词
$start_date = trim($_GET['start_date'] ?? ''); // 开始日期
$end_date = trim($_GET['end_date'] ?? ''); // 结束日期

// 验证日期格式（仅管理员时验证）
if ($is_admin) {
    $date_error = '';
    if (!empty($start_date) && !strtotime($start_date)) {
        $date_error = "开始日期格式错误（请输入YYYY-MM-DD）";
    }
    if (!empty($end_date) && !strtotime($end_date)) {
        $date_error = "结束日期格式错误（请输入YYYY-MM-DD）";
    }
    if (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
        $date_error = "开始日期不能晚于结束日期";
    }
    if ($date_error) {
        $message = $date_error;
    }
}

// 分页配置
$page_size = 10;
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max($current_page, 1);

// ------------ 核心：根据身份和查询条件构建查询SQL ------------
// 基础查询SQL和参数
if ($is_admin) {
    // 管理员：查询所有用户的已删除帖子，支持多条件筛选
    $sql_count = "
        SELECT COUNT(*) AS total 
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.delete_type != 'none'
    ";
    $sql_select = "
        SELECT fp.id, fp.title, fp.content, fp.images, fp.created_at, fp.updated_at, 
               fp.comment_count, fp.like_count, fp.delete_type, fp.deleted_at, 
               fp.deleted_by, fp.user_id AS post_user_id, u.username AS post_username
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.delete_type != 'none'
    ";
    $params = [];

    // 用户名查询条件（模糊匹配）
    if (!empty($search_username) && empty($date_error)) {
        $sql_count .= " AND u.username LIKE :username";
        $sql_select .= " AND u.username LIKE :username";
        $params[':username'] = "%" . $search_username . "%";
    }

    // 日期范围查询条件（按删除时间筛选）
    if (!empty($start_date) && empty($date_error)) {
        $sql_count .= " AND fp.deleted_at >= :start_date";
        $sql_select .= " AND fp.deleted_at >= :start_date";
        $params[':start_date'] = $start_date . " 00:00:00";
    }
    if (!empty($end_date) && empty($date_error)) {
        $sql_count .= " AND fp.deleted_at <= :end_date";
        $sql_select .= " AND fp.deleted_at <= :end_date";
        $params[':end_date'] = $end_date . " 23:59:59";
    }
} else {
    // 普通用户：仅查询自己的已删除帖子，无筛选条件
    $sql_count = "
        SELECT COUNT(*) AS total 
        FROM forum_posts 
        WHERE user_id = :user_id AND delete_type != 'none'
    ";
    $sql_select = "
        SELECT id, title, content, images, created_at, updated_at, comment_count, like_count,
               delete_type, deleted_at, deleted_by, user_id AS post_user_id
        FROM forum_posts
        WHERE user_id = :user_id AND delete_type != 'none'
    ";
    $params = [':user_id' => $user_id];
}

// 执行总数查询
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_posts = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_posts / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询已删除帖子（关键修复：显式绑定LIMIT参数类型）
$sql_select .= " ORDER BY deleted_at DESC LIMIT :offset, :page_size";
$stmt = $pdo->prepare($sql_select);

// 绑定基础参数
foreach ($params as $key => $value) {
    // 根据参数类型自动绑定（字符串/日期参数）
    if (strpos($key, ':start_date') === 0 || strpos($key, ':end_date') === 0) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    } elseif (strpos($key, ':user_id') === 0) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}

// 关键修复：LIMIT参数显式绑定为整数类型
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);

// 执行查询（之前报错的位置，现已修复）
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ---------------------------------------------------

// 处理帖子数据：图片路径、删除状态文本、删除人信息、发帖人信息
foreach ($posts as &$post) {
    // 处理图片路径
    $post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];
    
    // 处理删除状态文本和样式
    switch ($post['delete_type']) {
        case 'self':
            $post['delete_status_text'] = '已被作者删除（可恢复）';
            $post['delete_status_class'] = 'status-self';
            $post['delete_by_text'] = '删除人：本人';
            break;
        case 'admin':
            $post['delete_status_text'] = '已被管理员删除';
            $post['delete_status_class'] = 'status-admin';
            // 查询删除该帖子的管理员用户名
            $stmt_admin = $pdo->prepare("SELECT username FROM users WHERE id = :deleted_by");
            $stmt_admin->bindValue(':deleted_by', $post['deleted_by'], PDO::PARAM_INT);
            $stmt_admin->execute();
            $admin_name = $stmt_admin->fetchColumn() ?? '未知管理员';
            $post['delete_by_text'] = "删除人：管理员【$admin_name】";
            break;
        default:
            $post['delete_status_text'] = '已删除';
            $post['delete_status_class'] = 'status-unknown';
            $post['delete_by_text'] = '删除人：未知';
            break;
    }
    
    // 管理员视角：补充发帖人信息（普通用户无需显示）
    if ($is_admin) {
        $post['poster_info'] = "发帖人：{$post['post_username']}（ID：{$post['post_user_id']}）";
    } else {
        $post['poster_info'] = '';
    }
}
unset($post);

// 处理提示信息
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的删除帖子 - 个人中心</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
    <link rel="stylesheet" href="../css/profile_deleted_posts.css" type="text/css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $is_admin ? '所有已删除帖子' : '我的删除帖子' ?></h1>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">乒乓论坛</a>
                <a href="profile.php">个人中心</a>
                <a href="profile_posts.php">我的发帖</a>
                <a href="profile_deleted_posts.php" class="active"><?= $is_admin ? '所有删除帖子' : '查看删除帖子' ?></a>
                <a href="logout.php" style="color: #dc3545;">退出登录</a>
            </div>
        </div>

        <!-- 提示信息 -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>
                <?php if ($is_admin): ?>
                    全站已删除帖子记录（共 <?= $total_posts ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）
                    <span class="admin-title-note">管理员视角：显示所有用户的已删除帖子</span>
                <?php else: ?>
                    我的已删除帖子记录（共 <?= $total_posts ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）
                    <small style="font-size: 14px; font-weight: normal; color: #666; margin-left: 10px;">
                        已删除帖子不会在论坛首页显示
                    </small>
                <?php endif; ?>
            </h2>

            <!-- 管理员查询表单 -->
            <?php if ($is_admin): ?>
                <form class="admin-search-form" method="GET">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #333;">高级查询</h3>
                    
                    <div class="search-form-row">
                        <!-- 用户名搜索 -->
                        <div class="search-form-group">
                            <label for="search_username">用户名搜索（模糊匹配）</label>
                            <input type="text" id="search_username" name="search_username" 
                                   class="search-form-control" placeholder="输入用户名关键词..."
                                   value="<?= htmlspecialchars($search_username) ?>">
                        </div>
                        
                        <!-- 开始日期 -->
                        <div class="search-form-group">
                            <label for="start_date">删除开始日期</label>
                            <input type="date" id="start_date" name="start_date" 
                                   class="search-form-control"
                                   value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        
                        <!-- 结束日期 -->
                        <div class="search-form-group">
                            <label for="end_date">删除结束日期</label>
                            <input type="date" id="end_date" name="end_date" 
                                   class="search-form-control"
                                   value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                    </div>
                    
                    <div class="search-btn-group">
                        <button type="submit" class="btn-search">查询</button>
                        <button type="button" class="btn-reset" onclick="window.location.href='profile_deleted_posts.php'">重置</button>
                    </div>
                </form>

                <!-- 查询结果提示 -->
                <?php if (!empty($search_username) || !empty($start_date) || !empty($end_date)): ?>
                    <div class="search-result-note">
                        当前查询条件：
                        <?php if (!empty($search_username)): ?>
                            用户名包含「<?= htmlspecialchars($search_username) ?>」
                        <?php endif; ?>
                        <?php if (!empty($start_date) && !empty($end_date)): ?>
                            <?php if (!empty($search_username)): ?> | <?php endif; ?>
                            删除时间 <?= htmlspecialchars($start_date) ?> 至 <?= htmlspecialchars($end_date) ?>
                        <?php elseif (!empty($start_date)): ?>
                            <?php if (!empty($search_username)): ?> | <?php endif; ?>
                            删除时间 ≥ <?= htmlspecialchars($start_date) ?>
                        <?php elseif (!empty($end_date)): ?>
                            <?php if (!empty($search_username)): ?> | <?php endif; ?>
                            删除时间 ≤ <?= htmlspecialchars($end_date) ?>
                        <?php endif; ?>
                        <a href="profile_deleted_posts.php" style="margin-left: 10px; color: #007bff;">清除条件</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="record-list">
                <?php if (empty($posts)): ?>
                    <div class="no-deleted-posts">
                        <i>🗑️</i>
                        <?php if ($is_admin): ?>
                            <?php if (!empty($search_username) || !empty($start_date) || !empty($end_date)): ?>
                                <p>未找到符合条件的已删除帖子</p>
                                <p style="margin-top: 10px; font-size: 13px;">建议调整查询条件后重试</p>
                            <?php else: ?>
                                <p>全站暂无已删除的帖子</p>
                                <p style="margin-top: 10px; font-size: 13px;">所有用户删除的帖子会保存在这里，管理员可统一管理</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>你暂无已删除的帖子</p>
                            <p style="margin-top: 10px; font-size: 13px;">删除后的帖子会保存在这里，可随时恢复</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="record-item">
                            <div class="record-title">
                                <?= htmlspecialchars($post['title']) ?>
                                <!-- 显示删除状态标签 -->
                                <span class="delete-status <?= $post['delete_status_class'] ?>">
                                    <?= $post['delete_status_text'] ?>
                                </span>
                                <!-- 管理员视角：显示发帖人信息 -->
                                <?php if ($is_admin && !empty($post['poster_info'])): ?>
                                    <span class="poster-tag"><?= $post['poster_info'] ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="record-meta">
                                <!-- 管理员视角：优先显示发帖人信息 -->
                                <?php if ($is_admin && !empty($post['poster_info'])): ?>
                                    <span><?= $post['poster_info'] ?></span>
                                <?php endif; ?>
                                <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                                <span>删除时间：<?= date('Y-m-d H:i', strtotime($post['deleted_at'])) ?></span>
                                <span><?= $post['delete_by_text'] ?></span>
                                <span>评论数：<?= $post['comment_count'] ?></span>
                                <span>点赞数：<?= $post['like_count'] ?></span>
                                <?php if (!empty($post['images_arr'])): ?>
                                    <span>图片：<?= count($post['images_arr']) ?>张</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="deleted-content">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <!-- 恢复按钮（管理员可恢复所有，普通用户仅恢复自己的） -->
                                <?php if ($is_admin || ($post['post_user_id'] == $user_id && $post['delete_type'] === 'self')): ?>
                                    <a href="forum_post.php?id=<?= $post['id'] ?>&action=restore" 
                                       class="btn-restore"
                                       onclick="return confirm('确定要恢复该帖子吗？\n恢复后将重新在论坛首页显示，所有评论和点赞数据保留')">
                                        恢复帖子
                                    </a>
                                <?php endif; ?>
                                <!-- 查看详情按钮 -->
                                <a href="forum_post.php?id=<?= $post['id'] ?>" class="btn-view">
                                    查看详情
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页导航（保持查询条件） -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="profile_deleted_posts.php?page=<?= $current_page - 1 ?>&search_username=<?= urlencode($search_username) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">上一页</a>
                <?php else: ?>
                    <span class="disabled">上一页</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                if ($end_page - $start_page < 4) {
                    $start_page = max(1, $end_page - 4);
                }
                ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="profile_deleted_posts.php?page=<?= $i ?>&search_username=<?= urlencode($search_username) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="profile_deleted_posts.php?page=<?= $current_page + 1 ?>&search_username=<?= urlencode($search_username) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>