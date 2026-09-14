<h2>Login</h2>
<form method="POST">
    <div class="form-group">
        <label>Email</label>
        <!-- Deliberately type=text: accounts created before the email switch log in with a plain name. -->
        <input type="text" name="username_email" id="loginUsername" inputmode="email"
               autocomplete="username" required>
        <div id="usernameStatus" class="muted" style="font-size: 0.85rem; margin-top: 0.3rem; min-height: 1.1em;"></div>
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <div class="form-group">
        <label style="font-weight: normal;">
            <input type="checkbox" name="remember_me" value="1" style="width: auto; margin-right: 0.4rem;">
            Keep me logged in
        </label>
    </div>
    <button type="submit">Log In</button>
</form>
<div class="auth-links">
    <p><a href="<?= e(url_for('forgot_password')) ?>">Forgot password?</a></p>
    <p class="muted">Need access? Ask an admin to create an account for you.</p>
</div>

<script>
(function () {
    const input = document.getElementById('loginUsername');
    const status = document.getElementById('usernameStatus');
    let timer = null;
    let lastQuery = '';

    async function check(value) {
        try {
            const resp = await fetch('/admin/login/check.php?u=' + encodeURIComponent(value));
            if (!resp.ok) return;
            const data = await resp.json();
            if (input.value.trim() !== value) return;
            if (data.exists) {
                status.textContent = '✓ Account found';
                status.style.color = '#3c763d';
            } else {
                status.textContent = '✗ No account with that email';
                status.style.color = '#a94442';
            }
        } catch (err) { /* ignore */ }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const val = input.value.trim();
        if (!val) { status.textContent = ''; return; }
        if (val === lastQuery) return;
        lastQuery = val;
        status.style.color = '#777';
        status.textContent = 'Checking…';
        timer = setTimeout(() => check(val), 350);
    });
})();
</script>
