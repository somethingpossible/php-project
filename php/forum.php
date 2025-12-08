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

// 图片上传配置
define('UPLOAD_DIR', '../upload/forum/'); // 图片存储目录（相对路径）
define('MAX_IMAGES', 3); // 最多上传3张图片
define('MAX_FILE_SIZE', 4 * 1024 * 1024); // 单张图片最大4MB
$allow_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // 允许的图片格式
$allow_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // 允许的文件扩展名

// 确保上传目录存在
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true); // 递归创建目录
}

// 处理发帖提交（含图片上传）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_title'])) {
    $title = trim($_POST['post_title']);
    $content = trim($_POST['post_content']);
    $uploaded_images = []; // 存储上传成功的图片路径

    // 1. 基础表单验证
    if (empty($title) || empty($content)) {
        $message = "标题和内容不能为空！";
    } elseif (mb_strlen($title) > 255) {
        $message = "标题长度不能超过255字！";
    } else {
        // 2. 处理图片上传（如果有选择图片）
        if (!empty($_FILES['post_images']['name'][0])) {
            $total_files = count($_FILES['post_images']['name']);
            
            // 检查上传图片数量
            if ($total_files > MAX_IMAGES) {
                $message = "最多只能上传" . MAX_IMAGES . "张图片！";
            } else {
                // 循环处理每张图片
                for ($i = 0; $i < $total_files; $i++) {
                    $file_name = $_FILES['post_images']['name'][$i];
                    $file_tmp = $_FILES['post_images']['tmp_name'][$i];
                    $file_size = $_FILES['post_images']['size'][$i];
                    $file_type = $_FILES['post_images']['type'][$i];
                    $file_error = $_FILES['post_images']['error'][$i];

                    // 检查上传错误
                    if ($file_error !== UPLOAD_ERR_OK) {
                        $message = "第" . ($i + 1) . "张图片上传失败：错误码" . $file_error;
                        break;
                    }

                    // 检查文件大小
                    if ($file_size > MAX_FILE_SIZE) {
                        $message = "第" . ($i + 1) . "张图片超过" . (MAX_FILE_SIZE / 1024 / 1024) . "MB限制！";
                        break;
                    }

                    // 检查文件类型和扩展名
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($file_type, $allow_types) || !in_array($file_ext, $allow_ext)) {
                        $message = "第" . ($i + 1) . "张图片格式不支持！仅支持jpg/png/gif/webp";
                        break;
                    }

                    // 生成唯一文件名（避免重复）
                    $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                    $dest_path = UPLOAD_DIR . $unique_name;

                    // 移动上传文件到目标目录
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // 存储相对路径（数据库中只存相对路径，方便迁移）
                        $uploaded_images[] = 'upload/forum/' . $unique_name;
                    } else {
                        $message = "第" . ($i + 1) . "张图片上传失败：无法保存文件！";
                        break;
                    }
                }
            }
        }

        // 3. 若图片上传无错误，执行发帖逻辑
        if (empty($message)) {
            try {
                // 将图片路径数组转为字符串（逗号分隔）
                $images_str = implode(',', $uploaded_images);

                $stmt = $pdo->prepare("
                    INSERT INTO forum_posts (user_id, username, title, content, images)
                    VALUES (:user_id, :username, :title, :content, :images)
                ");
                $stmt->execute([
                    ':user_id' => $current_user_id,
                    ':username' => $current_username,
                    ':title' => $title,
                    ':content' => $content,
                    ':images' => $images_str
                ]);
                $new_post_id = $pdo->lastInsertId();
                $message = "发帖成功！" . (count($uploaded_images) > 0 ? "（成功上传" . count($uploaded_images) . "张图片）" : "");
                
                // 跳转到新帖子详情页
                header("Location: forum_post.php?id=$new_post_id&message=" . urlencode($message));
                exit;
            } catch (PDOException $e) {
                // 发帖失败，删除已上传的图片（回滚）
                foreach ($uploaded_images as $img_path) {
                    if (file_exists('../' . $img_path)) {
                        unlink('../' . $img_path);
                    }
                }
                $message = "发帖失败：" . $e->getMessage();
            }
        }
    }
}

// 分页核心配置（不变）
$page_size = 10;
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max($current_page, 1);

// 查询总帖子数（不变）
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM forum_posts");
$stmt->execute();
$total_posts = (int)$stmt->fetch()['total'];
$total_pages = ceil($total_posts / $page_size);
$total_pages = max($total_pages, 1);
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $page_size;

// 分页查询帖子（新增查询images字段）
$stmt = $pdo->prepare("
    SELECT id, title, username, content, images, created_at, updated_at, comment_count
    FROM forum_posts
    ORDER BY updated_at DESC
    LIMIT :offset, :page_size
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理帖子图片（将字符串转为数组，方便前端显示）
foreach ($posts as &$post) {
    $post['images_arr'] = !empty($post['images']) ? explode(',', $post['images']) : [];
}
unset($post);

// 处理提示信息（不变）
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>乒乓论坛 - 乒乓球馆预约系统</title>
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
                <a href="forum.php" class="active">论坛首页</a>
            </div>
        </div>

        <!-- 提示信息（不变） -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- 发帖表单（新增图片上传区域） -->
        <div class="card">
            <h2>发布新帖子</h2>
            <!-- 表单必须添加 enctype="multipart/form-data" 才能上传文件 -->
            <form method="POST" enctype="multipart/form-data">
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

                <!-- 新增图片上传区域 -->
                <div class="upload-container">
                    <div class="upload-title">上传图片（可选，最多<?= MAX_IMAGES ?>张，单张≤<?= MAX_FILE_SIZE / 1024 / 1024 ?>MB）</div>
                    <label class="upload-box">
                        <input type="file" name="post_images[]" id="post_images" multiple 
                               accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                        <div class="upload-icon">📷</div>
                        <div class="upload-tip">点击或拖拽图片到此处上传</div>
                        <div class="upload-limit">支持jpg、png、gif、webp格式</div>
                    </label>
                    <!-- 图片预览区域 -->
                    <div class="preview-images" id="preview_images"></div>
                </div>

                <button type="submit" class="btn">发布帖子</button>
            </form>
        </div>

        <!-- 帖子列表（新增图片预览） -->
        <div class="card post-list">
            <h2>论坛帖子（共 <?= $total_posts ?> 篇 / 第 <?= $current_page ?> 页 / 共 <?= $total_pages ?> 页）</h2>
            <?php if (empty($posts)): ?>
                <p style="text-align: center; padding: 30px; color: #666;">暂无帖子，快来发布第一篇吧！</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-item">
                        <div class="post-title">
                            <a href="forum_post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                        </div>
                        <div class="post-meta">
                            <span>作者：<?= htmlspecialchars($post['username']) ?></span>
                            <span>发布时间：<?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                            <span>最后更新：<?= date('Y-m-d H:i', strtotime($post['updated_at'])) ?></span>
                            <span>评论数：<?= $post['comment_count'] ?></span>
                            <?php if (!empty($post['images_arr'])): ?>
                                <span>图片：<?= count($post['images_arr']) ?>张</span>
                            <?php endif; ?>
                        </div>
                        <!-- 帖子摘要（不变） -->
                        <div class="post-excerpt">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>
                        <!-- 帖子图片预览（列表页只显示第一张图） -->
                        <?php if (!empty($post['images_arr'])): ?>
                            <div class="post-images" style="margin-top: 10px;">
                                <div class="post-image-item">
                                    <img src="../<?= htmlspecialchars($post['images_arr'][0]) ?>" 
                                         alt="帖子图片" title="点击查看完整帖子和所有图片">
                                </div>
                                <?php if (count($post['images_arr']) > 1): ?>
                                    <div style="line-height: 180px; color: #666; margin-left: 10px;">
                                        共<?= count($post['images_arr']) ?>张图片 →
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <!-- 查看详情按钮（不变） -->
                        <a href="forum_post.php?id=<?= $post['id'] ?>" class="btn btn-sm">查看详情</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 分页导航栏（不变） -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="forum.php?page=<?= $current_page - 1 ?>">上一页</a>
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
                        <a href="forum.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="forum.php?page=<?= $current_page + 1 ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 图片预览JS（实时显示选择的图片） -->
    <script>
        const fileInput = document.getElementById('post_images');
        const previewContainer = document.getElementById('preview_images');

        // 监听文件选择事件
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length === 0) return;

            // 清空现有预览
            previewContainer.innerHTML = '';

            // 限制预览数量不超过最大上传数
            const showFiles = Array.from(files).slice(0, <?= MAX_IMAGES ?>);

            showFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-image-item';
                    
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.alt = '预览图片' + (index + 1);
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'preview-image-remove';
                    removeBtn.textContent = '×';
                    removeBtn.onclick = function() {
                        previewItem.remove();
                        // 这里仅移除预览，实际文件仍会上传（如需完全移除需复杂处理，简化版直接提交选中文件）
                    };
                    
                    previewItem.appendChild(img);
                    previewItem.appendChild(removeBtn);
                    previewContainer.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
        });

        // 支持拖拽上传预览
        const uploadBox = document.querySelector('.upload-box');
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadBox.style.borderColor = '#007bff';
            uploadBox.style.backgroundColor = 'rgba(0, 123, 255, 0.05)';
        });

        uploadBox.addEventListener('dragleave', function() {
            uploadBox.style.borderColor = '#ddd';
            uploadBox.style.backgroundColor = 'transparent';
        });

        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadBox.style.borderColor = '#ddd';
            uploadBox.style.backgroundColor = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                // 触发change事件，显示预览
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        });
    </script>
</body>
</html>