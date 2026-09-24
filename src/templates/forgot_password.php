<h2>Forgot Password</h2>
<p class="muted">Enter your email. An administrator will be notified and can reset your password for you.</p>
<form method="POST">
    <div class="form-group">
        <label>Email</label>
        <input type="text" name="username_email" inputmode="email" required>
    </div>
    <button type="submit">Request Reset</button>
</form>
<div class="auth-links">
    <p><a href="<?= e(url_for('login')) ?>">Back to login</a></p>
</div>
