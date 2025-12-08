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

// 查询总评论数
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM forum_comments WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$total_comments = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_comments / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询用户评论（关联帖子信息）
$stmt = $pdo->prepare("
    SELECT f_c.id, f_c.post_id, f_c.content, f_c.created_at, f_p.title 
    FROM forum_comments f_c
    JOIN forum_posts f_p ON f_c.post_id = f_p.id
    WHERE f_c.user_id = :user_id
    ORDER BY f_c.created_at DESC
    LIMIT :offset, :page_size
");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的评论 - 个人中心</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>我的评论</h1>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">乒乓论坛</a>
                <a href="profile.php">个人中心</a>
                <a href="profile_comments.php" class="active">我的评论</a>
                <a href="logout.php" style="color: #dc3545;">退出登录</a>
            </div>
        </div>

        <div class="card">
            <h2>我的评论记录（共 <?= $total_comments ?> 条 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）</h2>
            <div class="record-list">
                <?php if (empty($comments)): ?>
                    <p style="text-align: center; padding: 30px; color: #666;">你还没有发表过任何评论！</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-record-item">
                            <div class="comment-record-post">
                                评论了帖子：<a href="forum_post.php?id=<?= $comment['post_id'] ?>"><?= htmlspecialchars($comment['title']) ?></a>
                            </div>
                            <div class="comment-record-content">
                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                            </div>
                            <div class="record-meta">
                                评论时间：<?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?>
                            </div>
                            <a href="forum_post.php?id=<?= $comment['post_id'] ?>#post-<?= $comment['post_id'] ?>" class="btn btn-sm">查看原帖</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页导航 -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="profile_comments.php?page=<?= $current_page - 1 ?>">上一页</a>
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
                        <a href="profile_comments.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="profile_comments.php?page=<?= $current_page + 1 ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>