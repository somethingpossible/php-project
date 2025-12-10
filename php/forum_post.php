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

// ===================== 新增：处理删除/恢复帖子操作 =====================
if (isset($_GET['action'])) {
    $action = $_GET['action']; // delete=删除，restore=恢复
    $post_id = (int)$_GET['id'];

    // 1. 查询帖子信息（含删除状态）
    $stmt = $pdo->prepare("
        SELECT id, user_id, delete_type 
        FROM forum_posts 
        WHERE id = :post_id
    ");
    $stmt->execute([':post_id' => $post_id]);
    $target_post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_post) {
        $message = "帖子不存在！";
        header("Location: forum.php?message=" . urlencode($message));
        exit;
    }

    // 2. 权限验证
    $is_owner = ($target_post['user_id'] == $current_user_id); // 帖子作者
    $is_admin = isAdmin($current_user_id); // 管理员（需在common.php中实现）

    // 3. 处理删除操作
    if ($action === 'delete') {
        // 普通用户只能删除自己的帖子（未删除状态）
        if (!$is_owner && !$is_admin) {
            $message = "无权限删除该帖子！";
        } elseif ($target_post['delete_type'] !== 'none') {
            $message = "该帖子已被删除，无需重复操作！";
        } else {
            try {
                // 标记删除类型（self=用户自删，admin=管理员删除）
                $delete_type = $is_owner ? 'self' : 'admin';
                
                $stmt = $pdo->prepare("
                    UPDATE forum_posts
                    SET delete_type = :delete_type,
                        deleted_at = NOW(),
                        deleted_by = :deleted_by
                    WHERE id = :post_id
                ");
                $stmt->execute([
                    ':delete_type' => $delete_type,
                    ':deleted_by' => $current_user_id,
                    ':post_id' => $post_id
                ]);

                $message = $is_owner ? "帖子已标记为删除（可恢复）！" : "已管理员删除该帖子！";
            } catch (Exception $e) {
                $message = "删除失败：" . $e->getMessage();
            }
        }
    }

    // 4. 处理恢复操作
    if ($action === 'restore') {
        // 验证恢复权限：
        // - 普通用户：只能恢复自己删除的帖子
        // - 管理员：可恢复所有类型的删除帖子
        if ($target_post['delete_type'] === 'none') {
            $message = "该帖子未被删除，无需恢复！";
        } elseif ($is_owner && $target_post['delete_type'] === 'self') {
            // 普通用户恢复自己的帖子
            try {
                $stmt = $pdo->prepare("
                    UPDATE forum_posts
                    SET delete_type = 'none',
                        deleted_at = NULL,
                        deleted_by = NULL
                    WHERE id = :post_id
                ");
                $stmt->execute([':post_id' => $post_id]);
                $message = "帖子已成功恢复！";
            } catch (Exception $e) {
                $message = "恢复失败：" . $e->getMessage();
            }
        } elseif ($is_admin) {
            // 管理员恢复任意帖子
            try {
                $stmt = $pdo->prepare("
                    UPDATE forum_posts
                    SET delete_type = 'none',
                        deleted_at = NULL,
                        deleted_by = NULL
                    WHERE id = :post_id
                ");
                $stmt->execute([':post_id' => $post_id]);
                $message = "帖子已成功恢复（管理员操作）！";
            } catch (Exception $e) {
                $message = "恢复失败：" . $e->getMessage();
            }
        } else {
            $message = "无权限恢复该帖子！";
        }
    }

    header("Location: forum_post.php?id=$post_id&message=" . urlencode($message));
    exit;
}
// ======================================================================

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

            // 检查帖子是否存在且未被删除
            $stmt = $pdo->prepare("
                SELECT id FROM forum_posts 
                WHERE id = :post_id AND delete_type = 'none'
            ");
            $stmt->execute([':post_id' => $post_id]);
            if (!$stmt->fetch()) {
                throw new Exception("帖子不存在或已被删除！");
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

// 查询帖子详情（新增查询删除状态字段）
$stmt = $pdo->prepare("
    SELECT id, title, username, content, images, created_at, updated_at, comment_count, 
           user_id AS post_user_id, delete_type, deleted_at
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

// 判断当前用户是否为管理员（新增）
$is_admin = isAdmin($current_user_id);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - 乒乓论坛</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
    <style>
        /* 新增：删除状态标签样式 */
        .delete-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .tag-self {
            background-color: #fff3cd;
            color: #856404;
        }
        .tag-admin {
            background-color: #f8d7da;
            color: #721c24;
        }
        /* 新增：操作按钮样式优化 */
        .post-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-restore {
            background-color: #28a745;
            color: white;
            border: none;
        }
        .btn-restore:hover {
            background-color: #218838;
        }
        /* 新增：帖子已删除提示样式 */
        .deleted-notice {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .deleted-notice h3 {
            margin: 0 0 10px 0;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部导航（不变） -->
        <div class="header">
            <h1>乒乓论坛</h1>
            <div class="user-info">
                当前登录：<?= htmlspecialchars($current_username) ?>（ID：<?= $current_user_id ?>）
                <?php if ($is_admin): ?>
                    <span style="color: #dc3545; margin: 0 5px;">|</span>
                    <span style="color: #dc3545;">管理员</span>
                <?php endif; ?>
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

        <!-- 帖子详情（新增删除状态显示和操作按钮） -->
        <div class="card post-detail">
            <h2>
                <?= htmlspecialchars($post['title']) ?>
                <!-- 新增：显示删除状态标签 -->
                <?php if ($post['delete_type'] === 'self'): ?>
                    <span class="delete-tag tag-self">已被作者删除（可恢复）</span>
                <?php elseif ($post['delete_type'] === 'admin'): ?>
                    <span class="delete-tag tag-admin">已被管理员删除</span>
                <?php endif; ?>
            </h2>
            
            <div class="post-meta">
                <span>作者：<?= htmlspecialchars($post['username']) ?></span>
                <span>发布时间：<?= date('Y-m-d H:i:s', strtotime($post['created_at'])) ?></span>
                <span>最后更新：<?= date('Y-m-d H:i:s', strtotime($post['updated_at'])) ?></span>
                <span>评论数：<?= $post['comment_count'] ?></span>
                <?php if (!empty($post['images_arr'])): ?>
                    <span>图片：<?= count($post['images_arr']) ?>张</span>
                <?php endif; ?>
                <!-- 新增：显示删除时间（如果已删除） -->
                <?php if ($post['delete_type'] !== 'none'): ?>
                    <span>删除时间：<?= date('Y-m-d H:i:s', strtotime($post['deleted_at'])) ?></span>
                <?php endif; ?>
            </div>

            <!-- 新增：已删除帖子提示（评论区禁用） -->
            <?php if ($post['delete_type'] !== 'none'): ?>
                <div class="deleted-notice">
                    <?php if ($post['delete_type'] === 'self'): ?>
                        <h3>帖子已被作者删除</h3>
                        <p>该帖子已被作者标记为删除，暂时不会在论坛首页显示。作者可随时恢复该帖子。</p>
                    <?php else: ?>
                        <h3>帖子已被管理员删除</h3>
                        <p>该帖子已被管理员标记为删除，仅管理员可恢复显示。</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 新增：显示所有帖子图片 -->
            <?php if (!empty($post['images_arr']) && $post['delete_type'] === 'none'): ?>
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

            <!-- 新增：删除/恢复操作按钮 -->
            <div class="post-actions">
                <a href="forum.php" class="btn btn-sm">返回论坛首页</a>
                
                <!-- 帖子作者或管理员可见的操作按钮 -->
                <?php if ($current_user_id == $post['post_user_id'] || $is_admin): ?>
                    <?php if ($post['delete_type'] === 'none'): ?>
                        <!-- 未删除：显示删除按钮 -->
                        <a href="forum_post.php?id=<?= $post['id'] ?>&action=delete" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirm('确定要删除该帖子吗？\n- 作者删除：可随时恢复，不在论坛显示\n- 管理员删除：仅管理员可恢复')">
                            <?php echo $is_admin && $current_user_id != $post['post_user_id'] ? '管理员删除' : '删除帖子'; ?>
                        </a>
                    <?php else: ?>
                        <!-- 已删除：显示恢复按钮 -->
                        <a href="forum_post.php?id=<?= $post['id'] ?>&action=restore" 
                           class="btn btn-sm btn-restore" 
                           onclick="return confirm('确定要恢复该帖子吗？恢复后将重新在论坛首页显示')">
                            恢复帖子
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 评论区（已删除帖子禁用评论功能） -->
        <?php if ($post['delete_type'] === 'none'): ?>
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
        <?php endif; ?>
    </div>
</body>
</html>