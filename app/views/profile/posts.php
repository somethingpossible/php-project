<?php
// app/views/profile/posts.php
<h1>我的帖子</h1>
<?php if (!empty($posts)): ?>
    <?php foreach ($posts as $p): ?>
        <article>
            <h2><?=e($p['title'])?></h2>
            <div><?=nl2br(e($p['content']))?></div>
        </article>
    <?php endforeach; ?>
<?php else: ?>
    <p>暂无帖子。</p>
<?php endif; ?>
