<?php
// app/views/forum/post.php
<?php if ($post): ?>
    <h1><?=e($post['title'])?></h1>
    <div><?=nl2br(e($post['content']))?></div>
<?php else: ?>
    <h1>发帖</h1>
    <form method="post" action="/forum/post">
        <input name="title" placeholder="标题"><br>
        <textarea name="content" placeholder="内容"></textarea><br>
        <button type="submit">发布</button>
    </form>
<?php endif; ?>
