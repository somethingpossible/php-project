<?php
// app/views/appointment/index.php
<h1>预约</h1>
<form method="post" action="/appointment/book">
    <label>姓名：<input name="name"></label><br>
    <label>电话：<input name="phone"></label><br>
    <button type="submit">预约</button>
</form>
<?php if (!empty($items)): ?>
    <ul>
        <?php foreach ($items as $it): ?>
            <li><?=e($it['name'])?> - <?=e($it['phone'])?> (<?=e($it['created_at'])?>)</li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
