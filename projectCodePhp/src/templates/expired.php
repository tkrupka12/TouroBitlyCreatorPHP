<h2>This link has expired</h2>
<p><strong><?= e(short_label((string)$slug)) ?></strong> is no longer available.</p>
<?php if (!empty($expires_at)): ?>
<p class="muted">Expired on <?= e($expires_at) ?>.</p>
<?php endif; ?>
<div class="auth-links">
    <p><a href="<?= e(url_for('index')) ?>">&larr; Back to your links</a></p>
</div>
