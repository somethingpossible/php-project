<?php
// app/views/profile/likes.php
<h1>我点赞的帖子</h1>
<?php if (!empty($likes)): ?>
    <?php foreach ($likes as $p): ?>
        <div><a href="/forum/post/<?=e($p['id'])?>"><?=e($p['title'])?></a></div>
    <?php endforeach; ?>
<?php else: ?>
    <p>暂无点赞。</p>
<?php endif; ?>
