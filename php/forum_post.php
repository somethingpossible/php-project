<?php
// 引入公共工具文件（登录验证、Session处理）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$current_user_id = $user['id'];
$current_username = $user['username'];
$message = '';
$post = null;

// 连接数据库
require __DIR__ . '/db_connect.php';

// 验证帖子ID（不变）
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: forum.php?message=" . urlencode("无效的帖子ID！"));
    exit;
}
$post_id = (int)$_GET['id'];

// 处理评论提交（不变）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content'])) {
    $content = trim($_POST['comment_content']);

    if (empty($content)) {
        $message = "评论内容不能为空！";
    } else {
        try {
            $pdo->beginTransaction();

            // 检查帖子是否存在
            $stmt = $pdo->prepare("SELECT id FROM forum_posts WHERE id = :post_id");
            $stmt->execute([':post_id' => $post_id]);
            if (!$stmt->fetch()) {
                throw new Exception("帖子不存在！");
            }

            // 添加评论
            $stmt = $pdo->prepare("
                INSERT INTO forum_comments (post_id, user_id, username, content)
                VALUES (:post_id, :user_id, :username, :content)
            ");
            $stmt->execute([
                ':post_id' => $post_id,
                ':user_id' => $current_user_id,
                ':username' => $current_username,
                ':content' => $content
            ]);

            // 更新帖子的评论数和最后更新时间
            $stmt = $pdo->prepare("
                UPDATE forum_posts
                SET comment_count = comment_count + 1, updated_at = NOW()
                WHERE id = :post_id
            ");
            $stmt->execute([':post_id' => $post_id]);

            $pdo->commit();
            $message = "评论成功！";
            header("Location: forum_post.php?id=$post_id&message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "评论失败：" . $e->getMessage();
        }
    }
}

// 查询帖子详情（新增查询images字段）
$stmt = $pdo->prepare("
    SELECT id, title, username, content, images, created_at, updated_at, comment_count, user_id AS post_user_id
    FROM forum_posts
    WHERE id = :post_id
");
$stmt->execute([':post_id' => $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: forum.php?message=" . urlencode("该帖子不存在或已被删除！"));
    exit;
}

// 处理帖子图片（将字符串转为数组）
$post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];

// 查询该帖子的所有评论（不变）
$stmt = $pdo->prepare("
    SELECT id, username, content, created_at, user_id
    FROM forum_comments
    WHERE post_id = :post_id
    ORDER BY created_at ASC
");
$stmt->execute([':post_id' => $post_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理提示信息（不变）
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - 乒乓论坛</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
</head>
<body>
    <div class="container">
        <!-- 头部导航（不变） -->
        <div class="header">
            <h1>乒乓论坛</h1>
            <div class="user-info">
                当前登录：<?= htmlspecialchars($current_username) ?>（ID：<?= $current_user_id ?>）
                <a href="logout.php" style="margin-left: 15px; color: #dc3545;">退出登录</a>
            </div>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">论坛首页</a>
                <a href="forum_post.php?id=<?= $post['id'] ?>" class="active">帖子详情</a>
            </div>
        </div>

        <!-- 提示信息（不变） -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- 帖子详情（新增完整图片显示） -->
        <div class="card post-detail">
            <h2><?= htmlspecialchars($post['title']) ?></h2>
            <div class="post-meta">
                <span>作者：<?= htmlspecialchars($post['username']) ?></span>
                <span>发布时间：<?= date('Y-m-d H:i:s', strtotime($post['created_at'])) ?></span>
                <span>最后更新：<?= date('Y-m-d H:i:s', strtotime($post['updated_at'])) ?></span>
                <span>评论数：<?= $post['comment_count'] ?></span>
                <?php if (!empty($post['images_arr'])): ?>
                    <span>图片：<?= count($post['images_arr']) ?>张</span>
                <?php endif; ?>
            </div>

            <!-- 新增：显示所有帖子图片 -->
            <?php if (!empty($post['images_arr'])): ?>
                <div class="post-images">
                    <?php foreach ($post['images_arr'] as $img_path): ?>
                        <div class="post-image-item">
                            <img src="../<?= htmlspecialchars($img_path) ?>" 
                                 alt="帖子图片" title="点击查看原图">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="post-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>
            <div class="post-actions">
                <a href="forum.php" class="btn btn-sm">返回论坛首页</a>
            </div>
        </div>

        <!-- 评论区（不变） -->
        <div class="card comment-section">
            <h2>评论区（共 <?= count($comments) ?> 条评论）</h2>

            <div class="comment-list">
                <?php if (empty($comments)): ?>
                    <p style="color: #666; margin-bottom: 20px;">暂无评论，快来抢沙发！</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <div class="comment-meta">
                                <span>
                                    <?= htmlspecialchars($comment['username']) ?>
                                    <?php if ($comment['user_id'] == $post['post_user_id']): ?>
                                        <span style="color: #dc3545; margin-left: 5px;">作者</span>
                                    <?php endif; ?>
                                </span>
                                <span><?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div class="comment-content">
                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="comment-form">
                <h3>发表你的评论</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="comment_content">评论内容</label>
                        <textarea id="comment_content" name="comment_content" required 
                                  placeholder="请输入你的评论..."><?= htmlspecialchars($_POST['comment_content'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn">提交评论</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>