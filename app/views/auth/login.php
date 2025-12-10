<h2>登录</h2>
<?php if (!empty($error)): ?>
    <div style="color:red;"><?=htmlspecialchars($error)?></div>
<?php endif; ?>
<form method="post" action="/login">
    <label>邮箱：<input type="email" name="email" value="<?=htmlspecialchars($email ?? '')?>"></label><br>
    <label>密码：<input type="password" name="password"></label><br>
    <button type="submit">登录</button>
</form>
<p>没有账号？<a href="/register">注册</a></p>
