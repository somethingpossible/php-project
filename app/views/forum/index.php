<?php
// app/views/forum/index.php
<h1>论坛</h1>
<?php if (!empty($posts)): ?>
    <?php foreach ($posts as $p): ?>
        <article>
            <h2><?=e($p['title'])?></h2>
            <div><?=nl2br(e($p['content']))?></div>
            <a href="/forum/post/<?=e($p['id'])?>">查看</a>
        </article>
    <?php endforeach; ?>
<?php else: ?>
    <p>暂无帖子。</p>
<?php endif; ?>
