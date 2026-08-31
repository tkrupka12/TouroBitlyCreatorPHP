<h2>Edit Link</h2>
<p class="muted">Short link: <strong>tou.ro/<?= e($link['slug']) ?></strong> (custom short URL cannot be changed)</p>

<form method="POST">
    <div class="form-group">
        <label>Destination URL</label>
        <input type="text" name="url" value="<?= e($link['url']) ?>" required>
    </div>
    <div class="form-group">
        <label>Expires at <span class="muted">(optional)</span></label>
        <input type="datetime-local" name="expires_at"
               value="<?= !empty($link['expires_at']) ? e(str_replace(' ', 'T', $link['expires_at'])) : '' ?>">
        <?php if (!empty($link['expires_at'])): ?>
        <label style="font-weight: normal; margin-top: 0.5rem;">
            <input type="checkbox" name="clear_expiry" value="1" style="width:auto;"> Remove expiration
        </label>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label>Notes <span class="muted">(optional)</span></label>
        <textarea name="notes" rows="4" style="width:100%; padding:0.75rem; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-family:inherit; font-size:1rem;"><?= e($link['notes']) ?></textarea>
    </div>
    <button type="submit">Save Changes</button>
</form>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">
<p class="muted">
    Created: <?= e($link['created_at'] ?: '—') ?><br>
    Last edited: <?= e($link['updated_at'] ?: '—') ?>
</p>
<div class="auth-links">
    <p><a href="<?= e(url_for('index')) ?>">&larr; Back to links</a></p>
</div>
<script>
    (function setMinExpiresAt() {
        const pad = n => String(n).padStart(2, '0');
        const d = new Date();
        const localNow = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        const input = document.querySelector('input[name="expires_at"]');
        if (input) input.min = localNow;
    })();
</script>
