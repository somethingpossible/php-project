<?php
// app/views/profile/comments.php
<h1>我的评论</h1>
<?php if (!empty($comments)): ?>
    <?php foreach ($comments as $c): ?>
        <div><?=e($c['content'])?> <small><?=e($c['created_at'])?></small></div>
    <?php endforeach; ?>
<?php else: ?>
    <p>暂无评论。</p>
<?php endif; ?>
