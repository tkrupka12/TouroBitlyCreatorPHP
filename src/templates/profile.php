<h2>Profile</h2>

<h3 style="margin-top: 1.5rem;">Change your name</h3>
<form method="POST" action="<?= e(url_for('profile_change_name')) ?>">
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="users_name" value="<?= e($current_user['users_name']) ?>"
               placeholder="First and last name" required>
    </div>
    <button type="submit">Save name</button>
</form>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>Change email</h3>
<p class="muted">Your email is also the address you log in with.</p>
<form method="POST" action="<?= e(url_for('profile_change_email')) ?>">
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="username_email" value="<?= e($current_user['username_email']) ?>" required>
    </div>
    <button type="submit">Save email</button>
</form>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>Change password</h3>
<p class="muted">Enter your current password to confirm the change.</p>
<form method="POST" action="<?= e(url_for('profile_change_password')) ?>">
    <div class="form-group">
        <label>Current password</label>
        <input type="password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="form-group">
        <label>New password <span class="muted" style="font-weight: normal;">(at least 6 characters)</span></label>
        <input type="password" name="new_password" autocomplete="new-password" minlength="6" required>
    </div>
    <div class="form-group">
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" autocomplete="new-password" minlength="6" required>
    </div>
    <button type="submit">Change password</button>
</form>
