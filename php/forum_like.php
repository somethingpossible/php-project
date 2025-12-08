<?php
/**
 * 点赞/取消点赞接口（AJAX请求）
 */
include 'common.php';
require __DIR__ . '/db_connect.php';

// 验证登录状态
$user = getUserInfo();
if (!$user) {
    echo json_encode(['code' => 0, 'msg' => '请先登录！']);
    exit;
}
$user_id = $user['id'];

// 验证请求参数
if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
    echo json_encode(['code' => 0, 'msg' => '无效的帖子ID！']);
    exit;
}
$post_id = (int)$_POST['post_id'];

try {
    // 检查帖子是否存在
    $stmt = $pdo->prepare("SELECT id FROM forum_posts WHERE id = :post_id");
    $stmt->execute([':post_id' => $post_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['code' => 0, 'msg' => '帖子不存在或已被删除！']);
        exit;
    }

    // 检查用户是否已点赞
    $stmt = $pdo->prepare("SELECT id FROM forum_likes WHERE user_id = :user_id AND post_id = :post_id");
    $stmt->execute([
        ':user_id' => $user_id,
        ':post_id' => $post_id
    ]);
    $like_record = $stmt->fetch();

    if ($like_record) {
        // 已点赞 → 取消点赞
        $pdo->beginTransaction();

        // 删除点赞记录
        $stmt = $pdo->prepare("DELETE FROM forum_likes WHERE id = :id");
        $stmt->execute([':id' => $like_record['id']]);

        // 帖子点赞数减1
        $stmt = $pdo->prepare("UPDATE forum_posts SET like_count = like_count - 1 WHERE id = :post_id");
        $stmt->execute([':post_id' => $post_id]);

        $pdo->commit();

        // 查询最新点赞数
        $stmt = $pdo->prepare("SELECT like_count FROM forum_posts WHERE id = :post_id");
        $stmt->execute([':post_id' => $post_id]);
        $like_count = $stmt->fetch()['like_count'];

        echo json_encode([
            'code' => 1,
            'msg' => '取消点赞成功！',
            'is_liked' => 0,
            'like_count' => $like_count
        ]);
    } else {
        // 未点赞 → 点赞
        $pdo->beginTransaction();

        // 添加点赞记录
        $stmt = $pdo->prepare("INSERT INTO forum_likes (user_id, post_id) VALUES (:user_id, :post_id)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':post_id' => $post_id
        ]);

        // 帖子点赞数加1
        $stmt = $pdo->prepare("UPDATE forum_posts SET like_count = like_count + 1 WHERE id = :post_id");
        $stmt->execute([':post_id' => $post_id]);

        $pdo->commit();

        // 查询最新点赞数
        $stmt = $pdo->prepare("SELECT like_count FROM forum_posts WHERE id = :post_id");
        $stmt->execute([':post_id' => $post_id]);
        $like_count = $stmt->fetch()['like_count'];

        echo json_encode([
            'code' => 1,
            'msg' => '点赞成功！',
            'is_liked' => 1,
            'like_count' => $like_count
        ]);
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['code' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
    exit;
}
?>