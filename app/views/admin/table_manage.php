<?php
// app/views/admin/table_manage.php
<h1>表管理</h1>
<form method="post" action="/admin/table_manage/repair">
    <label>表名：<input name="table"></label>
    <button type="submit">修复</button>
</form>
<?php if (!empty($tables)): ?>
    <ul>
        <?php foreach ($tables as $t): ?>
            <li><?=e(implode(' | ', $t))?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
