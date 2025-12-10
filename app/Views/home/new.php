<h2>Create Note</h2>
<form method="post" action="/?route=home/new">
    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required>
    </div>
    <div class="form-group">
        <label>Content</label>
        <textarea name="content" rows="6"></textarea>
    </div>
    <div class="form-actions">
        <button type="submit">Create</button>
        <a class="button muted" href="/?route=home/index">Cancel</a>
    </div>
</form>
