<?php
// 引入公共工具文件（登录验证、Session处理）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$current_user_id = $user['id'];
$current_username = $user['username'];
$message = '';

// 连接数据库
require __DIR__ . '/db_connect.php';

// 处理发帖提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_title'])) {
    $title = trim($_POST['post_title']);
    $content = trim($_POST['post_content']);

    if (empty($title) || empty($content)) {
        $message = "标题和内容不能为空！";
    } elseif (mb_strlen($title) > 255) {
        $message = "标题长度不能超过255字！";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO forum_posts (user_id, username, title, content)
                VALUES (:user_id, :username, :title, :content)
            ");
            $stmt->execute([
                ':user_id' => $current_user_id,
                ':username' => $current_username,
                ':title' => $title,
                ':content' => $content
            ]);
            $new_post_id = $pdo->lastInsertId();
            $message = "发帖成功！";
            // 跳转到新帖子详情页
            header("Location: forum_post.php?id=$new_post_id&message=" . urlencode($message));
            exit;
        } catch (PDOException $e) {
            $message = "发帖失败：" . $e->getMessage();
        }
    }
}

// 分页核心配置
$page_size = 10; // 每页显示10篇帖子
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max($current_page, 1);

// 查询总帖子数
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM forum_posts");
$stmt->execute();
$total_posts = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_posts / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询帖子（仅查询摘要信息）
$stmt = $pdo->prepare("
    SELECT id, title, username, content, created_at, updated_at, comment_count
    FROM forum_posts
    ORDER BY updated_at DESC
    LIMIT :offset, :page_size
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理提示信息
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>乒乓论坛 - 乒乓球馆预约系统</title>
    <!-- 引入独立CSS文件 -->
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>乒乓论坛</h1>
            <div class="user-info">
                当前登录：<?= htmlspecialchars($current_username) ?>（ID：<?= $current_user_id ?>）
                <a href="logout.php" style="margin-left: 15px; color: #dc3545;">退出登录</a>
            </div>
            <div class="nav">
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php" class="active">论坛首页</a>
            </div>
        </div>

        <!-- 提示信息 -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- 发帖表单 -->
        <div class="card">
            <h2>发布新帖子</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="post_title">帖子标题</label>
                    <input type="text" id="post_title" name="post_title" required 
                           placeholder="请输入帖子标题（如：乒乓球拍推荐、发球技巧交流等）"
                           value="<?= htmlspecialchars($_POST['post_title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="post_content">帖子内容</label>
                    <textarea id="post_content" name="post_content" required 
                              placeholder="请详细描述你的内容..."><?= htmlspecialchars($_POST['post_content'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn">发布帖子</button>
            </form>
        </div>

        <!-- 帖子列表 + 分页 -->
        <div class="card post-list">
            <h2>论坛帖子（共 <?= $total_posts ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）</h2>
            <?php if (empty($posts)): ?>
                <p style="text-align: center; padding: 30px; color: #666;">暂无帖子，快来发布第一篇吧！</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-item">
                        <div class="post-title">
                            <!-- 点击标题跳转到帖子详情页 -->
                            <a href="forum_post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                        </div>
                        <div class="post-meta">
                            <span>作者：<?= htmlspecialchars($post['username']) ?></span>
                            <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                            <span>最后更新：<?= date('Y-m-d H:i', strtotime($post['updated_at'])) ?></span>
                            <span>评论数：<?= $post['comment_count'] ?></span>
                        </div>
                        <!-- 帖子摘要（折叠显示） -->
                        <div class="post-excerpt">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>
                        <!-- 查看详情按钮 -->
                        <a href="forum_post.php?id=<?= $post['id'] ?>" class="btn btn-sm">查看详情</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 分页导航栏 -->
            <div class="pagination">
                <!-- 上一页 -->
                <?php if ($current_page > 1): ?>
                    <a href="forum.php?page=<?= $current_page - 1 ?>">上一页</a>
                <?php else: ?>
                    <span class="disabled">上一页</span>
                <?php endif; ?>

                <!-- 页码导航 -->
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
                        <a href="forum.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- 下一页 -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="forum.php?page=<?= $current_page + 1 ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>