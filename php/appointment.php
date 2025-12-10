<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>乒乓球馆预约系统</title>
    <link rel="stylesheet" href="../css/appointment.css" type="text/css">
</head>
<body>
    <div class="container">
        <?php
        // 引入公共工具文件（已包含 session_start() 和登录验证）
        include 'common.php';
        
        // 强制验证登录（未登录自动跳转到登录页）
        checkLogin();
        
        $message = '';
        $user = getUserInfo(); // 从 Session 获取用户信息
        $current_username = $user['username']; // 登录用户的用户名
        $current_user_id = $user['id']; // 登录用户ID
        
        require __DIR__ . '/db_connect.php';

        // ===================== 新增：管理员权限判断 =====================
        // 1. 查询当前用户的角色（假设 users 表中有 role 字段：admin=管理员，user=普通用户）
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
        $stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_role = $stmt->fetchColumn() ?? 'user'; // 默认普通用户

        // 2. 如果是管理员，跳转到球桌管理界面（需自行创建 table_manage.php）
        if ($user_role === 'admin') {
            header("Location: table_manage.php?message=" . urlencode("管理员已进入球桌管理模式"));
            exit;
        }
        // ==============================================================

        // 1. 处理预约操作（点击立即预约）
        if (isset($_GET['reserve'])) {
            $table_id = (int)$_GET['reserve'];
            $duration = 1; // 默认预约1小时

            try {
                $pdo->beginTransaction();

                // 获取当前球桌状态和已预约用户
                $stmt = $pdo->prepare("
                    SELECT tr.status, GROUP_CONCAT(r.user_name) AS players 
                    FROM table_reservations tr
                    LEFT JOIN reservations r ON tr.id = r.table_id
                    WHERE tr.id = :table_id
                    GROUP BY tr.id
                ");
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->execute();
                $table = $stmt->fetch();

                if (!$table) {
                    throw new Exception("球桌不存在！");
                }

                // 检查球桌状态（仅空桌/可拼桌可预约）
                if ($table['status'] === 'full') {
                    throw new Exception("球桌已预约满员，无法操作！");
                }

                // 检查当前用户是否已预约该球桌
                $players = $table['players'] ? explode(',', $table['players']) : [];
                if (in_array($current_username, $players)) {
                    throw new Exception("您已预约该球桌，无需重复操作！");
                }

                // 添加预约记录
                $stmt = $pdo->prepare("
                    INSERT INTO reservations (table_id, user_name, duration) 
                    VALUES (:table_id, :user_name, :duration)
                ");
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->bindValue(':user_name', $current_username, PDO::PARAM_STR);
                $stmt->bindValue(':duration', $duration, PDO::PARAM_INT);
                $stmt->execute();

                // 更新球桌状态（1人→满员，空桌→可拼桌）
                $new_status = (count($players) >= 1) ? 'full' : 'one';
                $stmt = $pdo->prepare("
                    UPDATE table_reservations 
                    SET status = :new_status, updated_at = NOW() 
                    WHERE id = :table_id
                ");
                $stmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->execute();

                $pdo->commit();
                $message = count($players) >= 1 ? "成功加入球桌 #$table_id 拼桌！" : "成功预约球桌 #$table_id！";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "操作失败：" . $e->getMessage();
            }

            header("Location: appointment.php?message=" . urlencode($message));
            exit;
        }

        // 2. 处理取消预约操作
        if (isset($_GET['cancel'])) {
            $table_id = (int)$_GET['cancel'];

            try {
                $pdo->beginTransaction();

                // 步骤1：检查该球桌是否有当前用户的预约记录
                $stmt = $pdo->prepare("
                    SELECT r.id FROM reservations r
                    WHERE r.table_id = :table_id AND r.user_name = :user_name
                ");
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->bindValue(':user_name', $current_username, PDO::PARAM_STR);
                $stmt->execute();
                $reservation = $stmt->fetch();

                if (!$reservation) {
                    throw new Exception("您未预约该球桌，无法取消！");
                }

                // 步骤2：删除当前用户的预约记录
                $stmt = $pdo->prepare("
                    DELETE FROM reservations 
                    WHERE id = :reservation_id AND user_name = :user_name
                ");
                $stmt->bindValue(':reservation_id', $reservation['id'], PDO::PARAM_INT);
                $stmt->bindValue(':user_name', $current_username, PDO::PARAM_STR);
                $stmt->execute();

                // 步骤3：查询该球桌剩余的预约记录
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS remaining_count, GROUP_CONCAT(user_name) AS remaining_players
                    FROM reservations 
                    WHERE table_id = :table_id
                ");
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->execute();
                $remaining = $stmt->fetch();

                // 步骤4：更新球桌状态
                $remaining_count = (int)$remaining['remaining_count'];
                if ($remaining_count === 0) {
                    $new_status = 'empty'; // 无剩余预约→空桌
                } elseif ($remaining_count === 1) {
                    $new_status = 'one'; // 剩余1人→可拼桌
                } else {
                    $new_status = 'full'; // 剩余2人→满员
                }

                $stmt = $pdo->prepare("
                    UPDATE table_reservations 
                    SET status = :new_status, updated_at = NOW() 
                    WHERE id = :table_id
                ");
                $stmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
                $stmt->bindValue(':table_id', $table_id, PDO::PARAM_INT);
                $stmt->execute();

                $pdo->commit();
                $message = "成功取消球桌 #$table_id 的预约！";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "取消失败：" . $e->getMessage();
            }

            header("Location: appointment.php?message=" . urlencode($message));
            exit;
        }

        // 获取提示信息（从跳转参数中获取）
        $message = $_GET['message'] ?? '';

        // 获取所有球桌信息和对应预约人
        $stmt = $pdo->prepare("
            SELECT tr.id, tr.status, GROUP_CONCAT(r.user_name) AS players 
            FROM table_reservations tr
            LEFT JOIN reservations r ON tr.id = r.table_id
            GROUP BY tr.id
            ORDER BY tr.id ASC
        ");
        $stmt->execute();
        $tables = $stmt->fetchAll();

        // 处理球员列表和当前用户是否已预约该球桌
        foreach ($tables as &$table) {
            $table['players'] = $table['players'] ? explode(',', $table['players']) : [];
            $table['is_booked_by_current_user'] = in_array($current_username, $table['players']); // 当前用户是否已预约
        }
        unset($table); // 释放引用
        ?>

        <div class="header">
            <h1>乒乓球馆预约系统</h1>
            <p>当前登录用户：<?= htmlspecialchars($current_username) ?>（ID：<?= $current_user_id ?>）</p>
            <p>当前时间：<?php echo date('Y-m-d H:i:s'); ?></p>
            <a href="logout.php" style="color: #007bff; text-decoration: none;">退出登录</a>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="table-grid">
            <?php foreach ($tables as $table): ?>
                <div class="table-card status-<?php echo $table['status']; ?>">
                    <div class="table-name">球桌 #<?php echo $table['id']; ?></div>
                    <span class="status-badge badge-<?php echo $table['status']; ?>">
                        <?php 
                        // 球桌状态文本（空桌/可拼桌/已预约）
                        $status_text = [
                            'empty' => '空桌',
                            'one' => '可拼桌',
                            'full' => '已预约'
                        ];
                        echo $status_text[$table['status']];
                        ?>
                    </span>
                    
                    <?php if (!empty($table['players'])): ?>
                        <div class="table-info">
                            <strong>预约球员：</strong><?php echo implode('、', $table['players']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 按钮区域：根据状态显示不同按钮 -->
                    <div class="btn-group">
                        <?php if ($table['status'] == 'empty' || $table['status'] == 'one'): ?>
                            <?php if (!$table['is_booked_by_current_user']): ?>
                                <!-- 未预约该球桌，显示预约按钮 -->
                                <a href="?reserve=<?php echo $table['id']; ?>" class="btn btn-reserve">立即预约</a>
                            <?php else: ?>
                                <!-- 已预约该球桌，显示取消按钮 -->
                                <a href="?cancel=<?php echo $table['id']; ?>" class="btn btn-cancel" onclick="return confirm('确定要取消预约吗？')">取消预约</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($table['is_booked_by_current_user']): ?>
                                <!-- 已预约满员的球桌，显示取消按钮 -->
                                <a href="?cancel=<?php echo $table['id']; ?>" class="btn btn-cancel" onclick="return confirm('确定要取消预约吗？')">取消预约</a>
                            <?php else: ?>
                                <!-- 未预约满员的球桌，显示已预约按钮（禁用） -->
                                <button class="btn btn-full" disabled>已预约</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>