<?php $me = current_user(); ?>
<h2>Manage Users</h2>
<p class="muted">Add a username and initial password. You'll get an email template to send to the new user. They can change their credentials later from Profile.</p>

<form method="POST">
    <div class="form-group">
        <label>Username / email</label>
        <input type="text" name="username" placeholder="jdoe or jdoe@example.com" required>
    </div>
    <div class="form-group">
        <label>Initial password</label>
        <input type="text" name="password" placeholder="At least 6 characters" required minlength="6">
    </div>
    <div class="form-group">
        <label style="font-weight: normal;">
            <input type="checkbox" name="make_admin" value="1" style="width: auto; margin-right: 0.4rem;">
            Grant admin privileges
        </label>
    </div>
    <button type="submit">Create User</button>
</form>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>Users</h3>
<table>
    <thead>
        <tr><th>Username</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <?= e($u['username']) ?>
                <?php if ((int)$u['is_admin'] === 1): ?><span class="badge badge-admin">admin</span><?php endif; ?>
                <?php if ((int)$u['id'] === (int)$me['id']): ?><span class="badge badge-active">you</span><?php endif; ?>
                <?php if ((int)$u['pending_reset'] === 1): ?><span class="badge badge-reset">reset requested</span><?php endif; ?>
            </td>
            <td>
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center;">
                    <?php if ((int)$u['pending_reset'] === 1): ?>
                    <form method="POST" action="<?= e(url_for('admin_reset_password', ['user_id' => $u['id']])) ?>" style="margin: 0; display: flex; gap: 0.3rem;">
                        <input type="text" name="password" placeholder="new password" minlength="6" required
                               style="width: 130px; padding: 0.3rem 0.4rem; font-size: 0.8rem;">
                        <button type="submit" class="btn-small">Reset</button>
                    </form>
                    <?php endif; ?>
                    <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                    <form method="POST" action="<?= e(url_for('admin_toggle_admin', ['user_id' => $u['id']])) ?>" style="margin: 0;"
                          onsubmit="return confirm('<?= (int)$u['is_admin'] === 1 ? 'Demote ' . e($u['username']) . ' to a regular user?' : 'Promote ' . e($u['username']) . ' to admin?' ?>');">
                        <button type="submit" class="btn-small">
                            <?= (int)$u['is_admin'] === 1 ? 'Demote' : 'Promote to admin' ?>
                        </button>
                    </form>
                    <form method="POST" action="<?= e(url_for('admin_delete_user', ['user_id' => $u['id']])) ?>" style="margin: 0;"
                          onsubmit="return confirm('Remove <?= e($u['username']) ?>? Their links will be deleted too.');">
                        <button type="submit" class="btn-small btn-danger">Remove</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($new_creds)): ?>
<dialog id="credsDialog" class="note-dialog">
    <div class="dlg-body">
        <h3>User created — send them this email</h3>
        <p class="muted">Copy the message below and paste it into an email to <strong><?= e($new_creds['username']) ?></strong>.</p>
        <pre id="credsBody">Hi,

You've been given access to the tou.ro Link Manager<?= $new_creds['is_admin'] ? ' as an admin' : '' ?>.

Username: <?= e($new_creds['username']) ?>

Password: <?= e($new_creds['password']) ?>

Login: <?= e($new_creds['login_url']) ?>


You can change your username or password anytime from the Profile page after logging in.</pre>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" id="credsCopyBtn" class="dlg-close">Copy</button>
            <button type="button" id="credsCloseBtn" class="dlg-close">Close</button>
        </div>
    </div>
</dialog>
<script>
(function () {
    const dlg = document.getElementById('credsDialog');
    const body = document.getElementById('credsBody');
    const copyBtn = document.getElementById('credsCopyBtn');
    const closeBtn = document.getElementById('credsCloseBtn');

    if (dlg && typeof dlg.showModal === 'function') {
        dlg.showModal();
    }

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(body.textContent);
            const original = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            setTimeout(() => { copyBtn.textContent = original; }, 1500);
        } catch (err) {
            const range = document.createRange();
            range.selectNodeContents(body);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    });

    closeBtn.addEventListener('click', () => dlg.close());
})();
</script>
<?php endif; ?>
