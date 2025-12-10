<?php
// 引入公共工具文件（已包含 session_start() 和登录验证）
include 'common.php';

// 强制验证登录（未登录自动跳转到登录页）
checkLogin();

$user = getUserInfo();
$current_user_id = $user['id'];
$current_username = $user['username'];
$message = $_GET['message'] ?? '';

require __DIR__ . '/db_connect.php';

// ===================== 管理员权限验证 =====================
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
$stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
$stmt->execute();
$user_role = $stmt->fetchColumn() ?? 'user';

if ($user_role !== 'admin') {
    header("Location: appointment.php?message=" . urlencode("无权限访问管理员页面！"));
    exit;
}
// ==========================================================

// 新增：1. 处理添加球桌操作
if (isset($_POST['add_table'])) {
    $table_name = trim($_POST['table_name']);
    
    // 数据校验
    if (empty($table_name)) {
        $message = "球桌名称不能为空！";
    } elseif (strlen($table_name) > 50) {
        $message = "球桌名称不能超过50个字符！";
    } else {
        try {
            // 检查球桌名称是否已存在（避免重复）
            $stmt = $pdo->prepare("SELECT id FROM table_reservations WHERE name = :name");
            $stmt->bindValue(':name', $table_name, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                throw new Exception("球桌名称「$table_name」已存在，请更换名称！");
            }
            
            // 插入新球桌（默认状态为空桌，后续自动同步）
            $stmt = $pdo->prepare("
                INSERT INTO table_reservations (name, status, created_at, updated_at)
                VALUES (:name, 'empty', NOW(), NOW())
            ");
            $stmt->bindValue(':name', $table_name, PDO::PARAM_STR);
            $stmt->execute();
            
            $new_table_id = $pdo->lastInsertId();
            $message = "成功添加新球桌：#$new_table_id $table_name（默认状态：空桌）";
        } catch (Exception $e) {
            $message = "添加球桌失败：" . $e->getMessage();
        }
    }
}

// 新增：2. 处理删除球桌操作
if (isset($_GET['delete_table'])) {
    $table_id = (int)$_GET['delete_table'];
    
    try {
        $pdo->beginTransaction();
        
        // 步骤1：检查球桌是否存在
        $stmt = $pdo->prepare("SELECT id, name FROM table_reservations WHERE id = :table_id");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();
        $table = $stmt->fetch();
        
        if (!$table) {
            throw new Exception("要删除的球桌不存在！");
        }
        
        // 步骤2：删除该球桌的所有预约记录（级联删除）
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE table_id = :table_id");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // 步骤3：删除球桌本身
        $stmt = $pdo->prepare("DELETE FROM table_reservations WHERE id = :table_id");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
        $message = "成功删除球桌：#{$table['id']} {$table['name']}（已同步删除该球桌的所有预约记录）";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "删除球桌失败：" . $e->getMessage();
    }
    
    header("Location: table_manage.php?message=" . urlencode($message));
    exit;
}

// 移除：手动更新球桌状态的功能（删除原update_status相关代码）

// 4. 处理强制清除预约（原有功能，优化状态同步）
if (isset($_GET['clear'])) {
    $table_id = (int)$_GET['clear'];

    try {
        $pdo->beginTransaction();

        // 步骤1：删除该球桌所有预约
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE table_id = :table_id");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();

        // 步骤2：自动同步状态为「空桌」（0人）
        $stmt = $pdo->prepare("
            UPDATE table_reservations 
            SET status = 'empty', updated_at = NOW() 
            WHERE id = :table_id
        ");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        $message = "成功清除球桌 #$table_id 的所有预约记录！球桌状态已更新为空桌";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "清除失败：" . $e->getMessage();
    }

    header("Location: table_manage.php?message=" . urlencode($message));
    exit;
}

// 5. 处理删除指定用户的预约（原有功能，优化状态同步）
if (isset($_GET['remove_user'])) {
    $table_id = (int)$_GET['table_id'];
    $user_name = $_GET['user_name'];

    try {
        $pdo->beginTransaction();

        // 步骤1：删除指定用户的预约
        $stmt = $pdo->prepare("
            DELETE FROM reservations 
            WHERE table_id = :table_id AND user_name = :user_name
        ");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_name', $user_name, PDO::PARAM_STR);
        $stmt->execute();

        // 步骤2：查询剩余预约人数，自动同步状态
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS remaining_count FROM reservations WHERE table_id = :table_id
        ");
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();
        $remaining_count = (int)$stmt->fetchColumn();

        // 核心：根据剩余人数设置状态
        if ($remaining_count === 0) {
            $new_status = 'empty';
            $status_text = '空桌';
        } elseif ($remaining_count === 1) {
            $new_status = 'one';
            $status_text = '可拼桌';
        } else {
            $new_status = 'full';
            $status_text = '人数已满';
        }

        // 步骤3：更新球桌状态
        $stmt = $pdo->prepare("
            UPDATE table_reservations 
            SET status = :new_status, updated_at = NOW() 
            WHERE id = :table_id
        ");
        $stmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
        $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        $message = "成功移除球桌 #$table_id 上用户【$user_name】的预约！球桌状态已更新为：$status_text";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "移除失败：" . $e->getMessage();
    }

    header("Location: table_manage.php?message=" . urlencode($message));
    exit;
}

// 6. 获取所有球桌的详细信息（含预约人数，状态自动计算）
$stmt = $pdo->prepare("
    SELECT tr.id, tr.name, tr.status, tr.created_at, tr.updated_at, 
           GROUP_CONCAT(r.user_name) AS players,
           COUNT(r.id) AS player_count  -- 直接查询预约人数
    FROM table_reservations tr
    LEFT JOIN reservations r ON tr.id = r.table_id
    GROUP BY tr.id
    ORDER BY tr.id ASC
");
$stmt->execute();
$tables = $stmt->fetchAll();

// 处理球桌信息（状态根据人数重新校准，确保一致性）
foreach ($tables as &$table) {
    $table['players'] = $table['players'] ? explode(',', $table['players']) : [];
    $player_count = (int)$table['player_count'];
    
    // 强制根据人数同步状态（避免数据不一致）
    if ($player_count === 0) {
        $table['status'] = 'empty';
        $table['status_text'] = '空桌';
    } elseif ($player_count === 1) {
        $table['status'] = 'one';
        $table['status_text'] = '可拼桌';
    } else {
        $table['status'] = 'full';
        $table['status_text'] = '人数已满';
    }
    
    $table['player_count'] = $player_count;
    $table['create_time_text'] = date('Y-m-d H:i', strtotime($table['created_at']));
}
unset($table);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>球桌管理系统（管理员）</title>
    <link rel="stylesheet" href="../css/appointment.css" type="text/css">
    <link rel="stylesheet" href="../css/table_manage.css" type="text/css">
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>球桌管理系统（管理员专属）</h1>
            <div>
                <span class="admin-role-tag">管理员：<?= htmlspecialchars($current_username) ?></span>
                <!-- 修复：退出登录带重定向 -->
                <?php
                $current_url = urlencode($_SERVER['REQUEST_URI']);
                echo '<a href="logout.php?redirect=' . $current_url . '" style="color: #dc3545; margin-left: 15px; text-decoration: none;">退出登录</a>';
                ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- 新增：添加球桌表单区域 -->
        <div class="add-table-section">
            <h2 class="add-table-title">📌 添加新球桌</h2>
            <form method="POST" class="add-table-form">
                <div class="form-group">
                    <label for="table_name">球桌名称</label>
                    <input type="text" id="table_name" name="table_name" class="form-control" 
                           placeholder="例如：一号桌（红双喜）、VIP区二号桌" required>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        建议包含区域/品牌信息，便于用户识别
                    </small>
                </div>
                <button type="submit" name="add_table" class="btn-admin btn-add">添加球桌</button>
            </form>
        </div>

        <div class="manage-actions" style="margin-bottom: 20px; text-align: right;">
            <a href="appointment.php" class="btn-admin btn-back">返回用户预约界面</a>
        </div>

        <div class="table-manage-list">
            <?php if (empty($tables)): ?>
                <!-- 无球桌时的提示 -->
                <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <p style="color: #666; font-size: 16px;">暂无球桌数据，请点击上方「添加球桌」创建第一个球桌</p>
                </div>
            <?php else: ?>
                <?php foreach ($tables as $table): ?>
                    <div class="table-manage-card">
                        <!-- 删除球桌按钮 -->
                        <button class="delete-table-btn" 
                                onclick="if(confirm('确定要删除球桌 #<?= $table['id'] ?> <?= htmlspecialchars($table['name']) ?>吗？\n该操作会同步删除所有相关预约记录，且不可恢复！')) window.location.href='?delete_table=<?= $table['id'] ?>'">
                            删除球桌
                        </button>

                        <div class="table-manage-header">
                            <div class="table-manage-title">
                                球桌 #<?= $table['id'] ?>：<?= htmlspecialchars($table['name']) ?>
                            </div>
                            <!-- <span class="status-badge-admin badge-<?= $table['status'] ?>-admin">
                                <?= $table['status_text'] ?>（<?= $table['player_count'] ?>人预约）
                            </span> -->
                        </div>

                        <!-- 球桌创建时间信息 -->
                        <div class="table-meta">
                            <span>创建时间：<?= $table['create_time_text'] ?></span> | 
                            <span>最后更新：<?= date('Y-m-d H:i:s', strtotime($table['updated_at'])) ?></span>
                        </div>

                        <div class="player-list">
                            <strong>当前预约用户（共 <?= $table['player_count'] ?> 人）：</strong>
                            <?php if ($table['player_count'] > 0): ?>
                                <?php foreach ($table['players'] as $player): ?>
                                    <div class="player-item">
                                        <?= htmlspecialchars($player) ?>
                                        <span class="player-remove" onclick="if(confirm('确定要移除用户【<?= htmlspecialchars($player) ?>】的预约吗？')) window.location.href='?remove_user=1&table_id=<?= $table['id'] ?>&user_name=<?= urlencode($player) ?>'">×</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #666;">暂无预约用户</span>
                            <?php endif; ?>
                        </div>

                        <div class="manage-btn-group">
                            <!-- 移除手动更新状态的表单，只保留清除预约按钮 -->
                            <a href="?clear=<?= $table['id'] ?>" class="btn-admin btn-clear" onclick="return confirm('确定要清除该球桌的所有预约记录吗？此操作不可恢复！')">清除所有预约</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>