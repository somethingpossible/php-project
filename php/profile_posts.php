<?php
// 引入公共工具文件（登录验证）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$user_id = $user['id'];
$current_username = $user['username'];

// 连接数据库
require __DIR__ . '/db_connect.php';

// 分页配置
$page_size = 10;
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max($current_page, 1);

// 查询总发帖数（包含已删除的，因为是个人中心需要显示所有自己的帖子）
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM forum_posts WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$total_posts = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_posts / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询用户发帖（新增查询删除状态字段）
$stmt = $pdo->prepare("
    SELECT id, title, content, images, created_at, updated_at, comment_count, like_count,
           delete_type, deleted_at
    FROM forum_posts
    WHERE user_id = :user_id
    ORDER BY updated_at DESC
    LIMIT :offset, :page_size
");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理图片路径和删除状态文本
foreach ($posts as &$post) {
    $post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];
    // 处理删除状态文本
    switch ($post['delete_type']) {
        case 'self':
            $post['delete_status_text'] = '已被作者删除（可恢复）';
            $post['delete_status_class'] = 'status-self';
            break;
        case 'admin':
            $post['delete_status_text'] = '已被管理员删除';
            $post['delete_status_class'] = 'status-admin';
            break;
        default:
            $post['delete_status_text'] = '';
            $post['delete_status_class'] = '';
            break;
    }
}
unset($post);

// 判断当前用户是否为管理员（用于显示恢复按钮权限）
$is_admin = isAdmin($user_id);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的发帖 - 个人中心</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
    <style>
        /* 新增：删除状态标签样式 */
        .delete-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-self {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-admin {
            background-color: #f8d7da;
            color: #721c24;
        }
        /* 帖子项样式优化 */
        .record-item {
            position: relative;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .record-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        /* 恢复按钮样式 */
        .btn-restore {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
        }
        .btn-restore:hover {
            background-color: #218838;
        }
        /* 已删除帖子内容样式 */
        .deleted-content {
            color: #666;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>我的发帖</h1>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">乒乓论坛</a>
                <a href="profile.php">个人中心</a>
                <a href="profile_posts.php" class="active">我的发帖</a>
                <a href="logout.php" style="color: #dc3545;">退出登录</a>
            </div>
        </div>

        <div class="card">
            <h2>我的发帖记录（共 <?= $total_posts ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）</h2>
            <div class="record-list">
                <?php if (empty($posts)): ?>
                    <p style="text-align: center; padding: 30px; color: #666;">你还没有发布过帖子，快去论坛发帖吧！</p>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="record-item">
                            <div class="record-title">
                                <a href="forum_post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                                <!-- 显示删除状态标签 -->
                                <?php if (!empty($post['delete_status_text'])): ?>
                                    <span class="delete-status <?= $post['delete_status_class'] ?>">
                                        <?= $post['delete_status_text'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="record-meta">
                                <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                                <span>最后更新：<?= date('Y-m-d H:i', strtotime($post['updated_at'])) ?></span>
                                <?php if (!empty($post['delete_type'])): ?>
                                    <span>删除时间：<?= date('Y-m-d H:i', strtotime($post['deleted_at'])) ?></span>
                                <?php endif; ?>
                                <span>评论数：<?= $post['comment_count'] ?></span>
                                <span>点赞数：<?= $post['like_count'] ?></span>
                                <?php if (!empty($post['images_arr'])): ?>
                                    <span>图片：<?= count($post['images_arr']) ?>张</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 已删除帖子内容添加半透明样式 -->
                            <div class="post-excerpt <?= !empty($post['delete_type']) ? 'deleted-content' : '' ?>">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <a href="forum_post.php?id=<?= $post['id'] ?>" class="btn btn-sm">查看详情</a>
                                <!-- 显示恢复按钮（仅当帖子已删除且有权限时） -->
                                <?php if (!empty($post['delete_type'])): ?>
                                    <?php if ($post['delete_type'] === 'self' || $is_admin): ?>
                                        <a href="forum_post.php?id=<?= $post['id'] ?>&action=restore" 
                                           class="btn-restore"
                                           onclick="return confirm('确定要恢复该帖子吗？恢复后将重新在论坛首页显示')">
                                            恢复帖子
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页导航 -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="profile_posts.php?page=<?= $current_page - 1 ?>">上一页</a>
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
                        <a href="profile_posts.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="profile_posts.php?page=<?= $current_page + 1 ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>