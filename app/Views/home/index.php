<h2>Notes</h2>
<p><a class="button" href="/?route=home/new">Create new note</a></p>
<?php if (empty($notes)): ?>
    <p>No notes yet.</p>
<?php else: ?>
    <ul class="notes">
    <?php foreach ($notes as $note): ?>
        <li>
            <h3><?php echo htmlspecialchars($note['title']); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($note['created_at']); ?></div>
            <div class="content"><?php echo nl2br(htmlspecialchars($note['content'])); ?></div>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
