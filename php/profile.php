<?php
// 引入公共工具文件（登录验证）
include 'common.php';

// 强制登录验证（未登录跳转到登录页）
checkLogin();

$user = getUserInfo();
$user_id = $user['id'];
$message = '';

// 连接数据库
require __DIR__ . '/db_connect.php';

// 头像上传配置
define('AVATAR_UPLOAD_DIR', '../upload/avatar/'); // 头像存储目录
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 最大2MB
$allow_avatar_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allow_avatar_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// 确保头像目录存在
if (!file_exists(AVATAR_UPLOAD_DIR)) {
    mkdir(AVATAR_UPLOAD_DIR, 0755, true);
}

// 处理头像上传提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (empty($_FILES['avatar_file']['name'])) {
        $message = "请选择要上传的头像图片！";
    } else {
        $file_name = $_FILES['avatar_file']['name'];
        $file_tmp = $_FILES['avatar_file']['tmp_name'];
        $file_size = $_FILES['avatar_file']['size'];
        $file_type = $_FILES['avatar_file']['type'];
        $file_error = $_FILES['avatar_file']['error'];

        // 验证上传错误
        if ($file_error !== UPLOAD_ERR_OK) {
            $message = "头像上传失败：错误码" . $file_error;
        }
        // 验证文件大小
        elseif ($file_size > AVATAR_MAX_SIZE) {
            $message = "头像大小不能超过" . (AVATAR_MAX_SIZE / 1024 / 1024) . "MB！";
        }
        // 验证文件类型
        else {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_type, $allow_avatar_types) || !in_array($file_ext, $allow_avatar_ext)) {
                $message = "头像格式不支持！仅支持jpg/png/gif/webp";
            } else {
                try {
                    // 生成唯一文件名（用户ID+时间戳+随机数）
                    $unique_name = 'avatar_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    $dest_path = AVATAR_UPLOAD_DIR . $unique_name;

                    // 移动上传文件到目标目录
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // 获取旧头像路径（用于删除）
                        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = :user_id");
                        $stmt->execute([':user_id' => $user_id]);
                        $old_avatar = $stmt->fetch()['avatar'];

                        // 更新数据库中的头像路径（存储相对路径）
                        $new_avatar_path = 'upload/avatar/' . $unique_name;
                        $stmt = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :user_id");
                        $stmt->execute([
                            ':avatar' => $new_avatar_path,
                            ':user_id' => $user_id
                        ]);

                        // 删除旧头像文件（跳过默认头像）
                        if (!empty($old_avatar) && file_exists('../' . $old_avatar) && strpos($old_avatar, 'default-avatar') === false) {
                            unlink('../' . $old_avatar);
                        }

                        $message = "头像上传成功！";
                        // 刷新页面更新头像显示
                        header("Location: profile.php?message=" . urlencode($message));
                        exit;
                    } else {
                        $message = "头像上传失败：无法保存文件，请检查目录权限！";
                    }
                } catch (PDOException $e) {
                    $message = "头像更新失败：" . $e->getMessage();
                }
            }
        }
    }
}

// 查询用户完整信息（新增查询 account 字段）
$stmt = $pdo->prepare("
    SELECT id, username, account, nickname, avatar, phone, created_at 
    FROM users 
    WHERE id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// 处理用户信息默认值
$user_info['nickname'] = empty($user_info['nickname']) ? $user_info['username'] : $user_info['nickname'];
$has_avatar = !empty($user_info['avatar']) && file_exists('../' . $user_info['avatar']);
$current_avatar = $has_avatar ? '../' . $user_info['avatar'] : '../images/default-avatar.png';
$avatar_original = $has_avatar ? '../' . $user_info['avatar'] : ''; // 原图路径（无头像时为空）

// 统计用户相关数据
// 1. 发帖数（正常帖子）
$stmt = $pdo->prepare("SELECT COUNT(*) AS post_count FROM forum_posts WHERE user_id = :user_id AND delete_type = 'none'");
$stmt->execute([':user_id' => $user_id]);
$post_count = $stmt->fetch()['post_count'];

// 2. 已删除帖子数（新增）
$stmt = $pdo->prepare("SELECT COUNT(*) AS deleted_post_count FROM forum_posts WHERE user_id = :user_id AND delete_type != 'none'");
$stmt->execute([':user_id' => $user_id]);
$deleted_post_count = $stmt->fetch()['deleted_post_count'];

// 3. 点赞数（用户收到的总点赞）
$stmt = $pdo->prepare("
    SELECT COUNT(f_l.id) AS like_count 
    FROM forum_likes f_l
    JOIN forum_posts f_p ON f_l.post_id = f_p.id
    WHERE f_p.user_id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$received_like_count = $stmt->fetch()['like_count'];

// 4. 评论数
$stmt = $pdo->prepare("SELECT COUNT(*) AS comment_count FROM forum_comments WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$comment_count = $stmt->fetch()['comment_count'];

// 5. 用户点赞的帖子数
$stmt = $pdo->prepare("SELECT COUNT(*) AS my_like_count FROM forum_likes WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$my_like_count = $stmt->fetch()['my_like_count'];

// 处理提示信息
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人信息中心 - 乒乓球馆预约系统</title>
    <link rel="stylesheet" href="../css/forum.css" type="text/css">
    <style>
        .stat-card {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 8px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        /* 新增：已删除帖子统计项样式 */
        .stat-item.deleted {
            background: #fef7fb;
        }
        .stat-number.deleted {
            color: #dc3545;
        }
        /* 新增：功能按钮样式调整 */
        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        .profile-btn.deleted {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .profile-btn.deleted:hover {
            background-color: #f0c1c7;
        }
        /* 账号显示样式优化 */
        .profile-meta .account-item {
            color: #007bff;
            font-weight: 500;
        }
        .profile-meta .account-tip {
            font-size: 12px;
            color: #999;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>个人信息中心</h1>
            <div class="nav">
                <a href="../indexs.php">主页面</a>
                <a href="appointment.php">球桌预约</a>
                <a href="forum.php">乒乓论坛</a>
                <a href="profile.php" class="active">个人中心</a>
                <a href="logout.php" style="color: #dc3545;">退出登录</a>
            </div>
        </div>

        <!-- 提示信息 -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- 个人信息卡片 -->
        <div class="profile-card">
            <form method="POST" enctype="multipart/form-data" id="avatar_form">
                <!-- 头像框（核心交互区域） -->
                <div class="profile-header">
                    <label for="avatar_file" class="avatar-container" id="avatar_wrapper">
                        <?php if ($has_avatar): ?>
                            <!-- 有头像：显示头像 -->
                            <img src="<?= htmlspecialchars($current_avatar) ?>" alt="用户头像" id="avatar_img">
                        <?php else: ?>
                            <!-- 无头像：显示占位提示 -->
                            <div class="avatar-placeholder">
                                <div class="avatar-placeholder-icon">📷</div>
                                <div class="avatar-placeholder-text">点击上传头像</div>
                            </div>
                        <?php endif; ?>
                        <!-- 隐藏的文件输入框 -->
                        <input type="file" name="avatar_file" id="avatar_file" accept="image/jpeg,image/png,image/gif,image/webp" />
                        <input type="hidden" name="upload_avatar" value="1" />
                    </label>

                    <div class="profile-info">
                        <h2><?= htmlspecialchars($user_info['nickname']) ?></h2>
                        <div class="profile-meta">
                            <div>用户名：<?= htmlspecialchars($user_info['username']) ?></div>
                            <!-- 新增：显示登录账号 -->
                            <div>
                                登录账号：<span class="account-item"><?= htmlspecialchars($user_info['account']) ?></span>
                                <span class="account-tip">(用于登录，不可修改)</span>
                            </div>
                            <div>用户ID：<?= $user_info['id'] ?></div>
                            <div>注册时间：<?= date('Y-m-d H:i', strtotime($user_info['created_at'])) ?></div>
                            <div>手机号：<?= empty($user_info['phone']) ? '未绑定' : htmlspecialchars($user_info['phone']) ?></div>
                        </div>
                        <!-- 头像操作提示 -->
                        <?php if ($has_avatar): ?>
                            <div style="margin-top: 10px; font-size: 13px; color: #999;">
                                点击头像查看原图 | <a href="javascript:;" id="replace_avatar" style="color: #007bff;">更换头像</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- 数据统计（新增已删除帖子统计） -->
            <div class="stat-card">
                <div class="stat-item">
                    <div class="stat-number"><?= $post_count ?></div>
                    <div class="stat-label">我的发帖</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $received_like_count ?></div>
                    <div class="stat-label">收到点赞</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $comment_count ?></div>
                    <div class="stat-label">我的评论</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $my_like_count ?></div>
                    <div class="stat-label">我的点赞</div>
                </div>
                <div class="stat-item deleted">
                    <div class="stat-number deleted"><?= $deleted_post_count ?></div>
                    <div class="stat-label">已删除帖子</div>
                </div>
            </div>

            <!-- 功能按钮（新增查看删除帖子入口） -->
            <div class="profile-actions">
                <a href="profile_posts.php" class="profile-btn">
                    <i>📝</i> 我的发帖
                </a>
                <a href="profile_likes.php" class="profile-btn">
                    <i>❤️</i> 我的点赞
                </a>
                <a href="profile_comments.php" class="profile-btn secondary">
                    <i>💬</i> 我的评论
                </a>
                <a href="profile_deleted_posts.php" class="profile-btn deleted">
                    <i>🗑️</i> 查看删除帖子
                </a>
            </div>
        </div>
    </div>

    <!-- 查看原图弹窗 -->
    <div class="avatar-view-modal" id="avatar_view_modal">
        <div class="avatar-view-content">
            <img src="" alt="头像原图" class="avatar-view-img" id="avatar_view_img">
            <div class="avatar-view-close" id="avatar_view_close">×</div>
        </div>
    </div>

    <script>
        // 核心元素
        const avatarWrapper = document.getElementById('avatar_wrapper');
        const avatarImg = document.getElementById('avatar_img');
        const avatarFile = document.getElementById('avatar_file');
        const avatarForm = document.getElementById('avatar_form');
        const replaceAvatar = document.getElementById('replace_avatar');
        const avatarViewModal = document.getElementById('avatar_view_modal');
        const avatarViewImg = document.getElementById('avatar_view_img');
        const avatarViewClose = document.getElementById('avatar_view_close');
        const hasAvatar = <?= $has_avatar ? 'true' : 'false' ?>;
        const avatarOriginal = "<?= htmlspecialchars($avatar_original) ?>";

        // 有头像时：点击头像查看原图（阻止表单提交）
        if (hasAvatar && avatarImg) {
            avatarWrapper.addEventListener('click', function(e) {
                // 点击的是头像图片（不是更换头像链接）
                if (e.target === avatarImg || e.target === avatarWrapper) {
                    e.preventDefault();
                    // 显示原图弹窗
                    avatarViewImg.src = avatarOriginal;
                    avatarViewModal.classList.add('active');
                }
            });
        }

        // 更换头像：点击链接触发文件选择
        if (replaceAvatar) {
            replaceAvatar.addEventListener('click', function() {
                avatarFile.click();
            });
        }

        // 关闭原图弹窗
        avatarViewClose.addEventListener('click', function() {
            avatarViewModal.classList.remove('active');
        });

        // 点击弹窗外部关闭
        avatarViewModal.addEventListener('click', function(e) {
            if (e.target === avatarViewModal) {
                avatarViewModal.classList.remove('active');
            }
        });

        // 选择图片后自动提交表单（无需额外确认按钮）
        avatarFile.addEventListener('change', function() {
            if (this.files.length > 0) {
                avatarForm.submit();
            }
        });

        // 键盘ESC关闭弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && avatarViewModal.classList.contains('active')) {
                avatarViewModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>