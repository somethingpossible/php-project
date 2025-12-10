<?php
// app/views/profile/index.php
<h1>个人中心</h1>
<?php if ($user): ?>
    <p>姓名：<?=e($user['name'])?></p>
    <p>邮箱：<?=e($user['email'])?></p>
<?php else: ?>
    <p>未登录。<a href="/login">登录</a></p>
<?php endif; ?>
