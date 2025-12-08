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

// 查询总发帖数
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM forum_posts WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$total_posts = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_posts / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询用户发帖
$stmt = $pdo->prepare("
    SELECT id, title, content, images, created_at, updated_at, comment_count, like_count
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

// 处理图片路径
foreach ($posts as &$post) {
    $post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];
}
unset($post);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的发帖 - 个人中心</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
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
                            </div>
                            <div class="record-meta">
                                <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                                <span>最后更新：<?= date('Y-m-d H:i', strtotime($post['updated_at'])) ?></span>
                                <span>评论数：<?= $post['comment_count'] ?></span>
                                <span>点赞数：<?= $post['like_count'] ?></span>
                                <?php if (!empty($post['images_arr'])): ?>
                                    <span>图片：<?= count($post['images_arr']) ?>张</span>
                                <?php endif; ?>
                            </div>
                            <div class="post-excerpt">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                            <a href="forum_post.php?id=<?= $post['id'] ?>" class="btn btn-sm">查看详情</a>
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