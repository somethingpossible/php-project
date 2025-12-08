<?php
// 引入公共工具文件（登录验证）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$user_id = $user['id'];

// 连接数据库
require __DIR__ . '/db_connect.php';

// 分页配置
$page_size = 10;
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max($current_page, 1);

// 查询总点赞数
$stmt = $pdo->prepare("
    SELECT COUNT(f_l.id) AS total 
    FROM forum_likes f_l
    JOIN forum_posts f_p ON f_l.post_id = f_p.id
    WHERE f_l.user_id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$total_likes = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_likes / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询用户点赞的帖子
$stmt = $pdo->prepare("
    SELECT f_p.id, f_p.title, f_p.username, f_p.content, f_p.images, f_p.created_at, f_p.comment_count, f_p.like_count, f_l.created_at AS like_time
    FROM forum_likes f_l
    JOIN forum_posts f_p ON f_l.post_id = f_p.id
    WHERE f_l.user_id = :user_id
    ORDER BY f_l.created_at DESC
    LIMIT :offset, :page_size
");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
$stmt->execute();
$liked_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理图片路径
foreach ($liked_posts as &$post) {
    $post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];
}
unset($post);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的点赞 - 个人中心</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>我的点赞</h1>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">乒乓论坛</a>
                <a href="profile.php">个人中心</a>
                <a href="profile_likes.php" class="active">我的点赞</a>
                <a href="logout.php" style="color: #dc3545;">退出登录</a>
            </div>
        </div>

        <div class="card">
            <h2>我点赞的帖子（共 <?= $total_likes ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）</h2>
            <div class="record-list">
                <?php if (empty($liked_posts)): ?>
                    <p style="text-align: center; padding: 30px; color: #666;">你还没有点赞过任何帖子！</p>
                <?php else: ?>
                    <?php foreach ($liked_posts as $post): ?>
                        <div class="record-item">
                            <div class="record-title">
                                <a href="forum_post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                            </div>
                            <div class="record-meta">
                                <span>作者：<?= htmlspecialchars($post['username']) ?></span>
                                <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                                <span>点赞时间：<?= date('Y-m-d H:i', strtotime($post['like_time'])) ?></span>
                                <span>评论数：<?= $post['comment_count'] ?></span>
                                <span>点赞数：<?= $post['like_count'] ?></span>
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
                    <a href="profile_likes.php?page=<?= $current_page - 1 ?>">上一页</a>
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
                        <a href="profile_likes.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="profile_likes.php?page=<?= $current_page + 1 ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>