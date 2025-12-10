<h2>注册</h2>
<?php if (!empty($error)): ?>
    <div style="color:red;"><?=htmlspecialchars($error)?></div>
<?php endif; ?>
<form method="post" action="/register">
    <label>姓名：<input type="text" name="name" value="<?=htmlspecialchars($name ?? '')?>"></label><br>
    <label>邮箱：<input type="email" name="email" value="<?=htmlspecialchars($email ?? '')?>"></label><br>
    <label>密码：<input type="password" name="password"></label><br>
    <button type="submit">注册</button>
</form>
