<?php
// app/views/profile/deleted_posts.php
<h1>已删除的帖子</h1>
<?php if (!empty($posts)): ?>
    <?php foreach ($posts as $p): ?>
        <article>
            <h2><?=e($p['title'])?></h2>
        </article>
    <?php endforeach; ?>
<?php else: ?>
    <p>暂无已删除的帖子。</p>
<?php endif; ?>
