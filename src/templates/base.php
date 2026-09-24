<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(SHORT_LINK_DOMAIN) ?> Link Manager</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
        }
        nav {
            background: #333;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        nav a { color: white; text-decoration: none; margin-left: 1rem; }
        .container {
            max-width: 960px;
            margin: 3rem auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #555; }
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
            background: white;
        }
        button {
            width: 100%;
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background-color: #004085; }
        .flashes { color: #d9534f; margin-bottom: 1rem; list-style: none; padding: 0; }
        .muted { color: #777; font-size: 0.9rem; }
        .auth-links { text-align: center; margin-top: 1rem; font-size: 0.9rem; }
        .auth-links a { color: #0056b3; text-decoration: none; }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .badge-admin { background: #f0ad4e; color: white; }
        .badge-group { background: #e8ecf7; color: #33447a; }
        .badge-pending { background: #ccc; color: #333; }
        .badge-active { background: #5cb85c; color: white; }
        .badge-reset { background: #d9534f; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; table-layout: fixed; }
        th, td { text-align: left; padding: 0.6rem 0.5rem; border-bottom: 1px solid #eee; vertical-align: top; word-wrap: break-word; }
        th { font-size: 0.75rem; text-transform: uppercase; color: #666; letter-spacing: 0.03em; }
        td.expired { color: #999; }
        td .expired-badge { display: inline-block; padding: 0.1rem 0.4rem; background: #f8d7da; color: #a94442; border-radius: 4px; font-size: 0.75rem; margin-left: 0.4rem; }
        .btn-small { width: auto; padding: 0.35rem 0.7rem; font-size: 0.8rem; }
        .truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stacked-date { font-size: 0.8rem; line-height: 1.25; }
        .stacked-date .time { color: #999; }
        .note-btn {
            width: auto; background: #eef; color: #335;
            border: 1px solid #ccd; border-radius: 4px;
            padding: 0.25rem 0.55rem; font-size: 0.8rem; cursor: pointer;
            white-space: nowrap; font-weight: 600;
        }
        .note-btn:hover { background: #dde; }
        .note-btn-empty {
            background: transparent; color: #888; border-style: dashed;
        }
        .note-btn-empty:hover { background: #f4f4f4; color: #555; }
        .copy-btn {
            width: auto; background: transparent; color: #0056b3;
            border: 1px solid #cde; border-radius: 4px;
            padding: 0.15rem 0.4rem; font-size: 0.75rem; cursor: pointer;
            margin-top: 0.3rem; font-weight: 600;
        }
        .copy-btn:hover { background: #eef; }
        .copy-btn.copied { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .tt { position: relative; display: block; }
        .tt:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            top: 100%; left: 0;
            z-index: 20;
            background: #222; color: white;
            padding: 0.45rem 0.7rem; border-radius: 6px;
            font-size: 0.8rem; font-weight: normal;
            white-space: normal; max-width: 420px; min-width: 160px;
            word-break: break-all;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            margin-top: 4px;
            pointer-events: none;
        }
        dialog.note-dialog {
            border: none; border-radius: 10px; padding: 0;
            max-width: 480px; width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        dialog.note-dialog::backdrop { background: rgba(0,0,0,0.4); }
        dialog.note-dialog .dlg-body { padding: 1.5rem; }
        dialog.note-dialog h3 { margin-top: 0; }
        dialog.note-dialog pre {
            white-space: pre-wrap; word-wrap: break-word;
            background: #f7f7f7; padding: 1rem; border-radius: 6px;
            font-family: inherit; font-size: 0.95rem; margin: 0 0 1rem;
        }
        dialog.note-dialog .dlg-close {
            width: auto; padding: 0.4rem 1rem; font-size: 0.85rem;
        }
        .btn-danger {
            width: auto;
            background: #d9534f;
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
        }
        .btn-danger:hover { background: #c9302c; }
        .section { margin-bottom: 1.25rem; }
        .section > h3 { margin: 0 0 0.2rem; font-size: 1rem; color: #333; }
        .field-hint { color: #777; font-size: 0.85rem; margin: 0 0 0.7rem; }
        .choice {
            display: block; position: relative;
            font-weight: normal; padding-left: 1.7rem; margin-bottom: 0.7rem;
            cursor: pointer;
        }
        .choice input[type="radio"] {
            width: auto; position: absolute; left: 0; top: 0.15rem; margin: 0;
        }
        .choice-title { display: block; font-weight: 600; color: #333; }
        .choice-desc { display: block; color: #777; font-size: 0.85rem; margin-top: 0.1rem; }
        .url-row { display: flex; align-items: stretch; }
        .url-prefix {
            display: flex; align-items: center; white-space: nowrap;
            padding: 0 0.7rem; background: #f4f7f6; color: #555; font-size: 0.95rem;
            border: 1px solid #ccc; border-right: none; border-radius: 6px 0 0 6px;
        }
        .url-row input { border-radius: 0 6px 6px 0; }
        .link-preview {
            background: #f7f7f7; border-radius: 6px; padding: 0.8rem 1rem;
            font-weight: 600; color: #0056b3; word-break: break-all;
        }
        .link-preview .placeholder { color: #999; font-weight: normal; }
    </style>
</head>
<body>
<nav>
    <div><strong><?= e(SHORT_LINK_DOMAIN) ?></strong> Link Manager</div>
    <div>
        <?php if ($current_user): ?>
        <?php $manages_a_group = $current_user['is_super'] || $current_user['is_group_admin']; ?>
        <span>Logged in as: <strong><?= e($current_user['display_name']) ?></strong><?php
            if ($current_user['is_super']): ?><span class="badge badge-admin">super admin</span><?php
            elseif ($current_user['is_group_admin']): ?><span class="badge badge-admin">group admin</span><?php
            endif; ?><?php
            if ($current_group_name !== null): ?><span class="badge badge-group"><?= e($current_group_name) ?></span><?php
            elseif ($current_user['is_super']): ?><span class="badge badge-group">all groups</span><?php
            endif; ?></span>
        <a href="<?= e(url_for('index')) ?>">Create Custom Link</a>
        <?php if ($manages_a_group): ?>
        <a href="<?= e(url_for('admin_expired')) ?>">Expired Links</a>
        <a href="<?= e(url_for('admin_users')) ?>">Manage Users</a>
        <?php endif; ?>
        <?php if ($current_user['is_super']): ?>
        <a href="<?= e(url_for('admin_groups')) ?>">Groups</a>
        <?php endif; ?>
        <a href="<?= e(url_for('profile')) ?>">Profile</a>
        <a href="<?= e(url_for('logout')) ?>">Logout</a>
        <?php else: ?>
        <a href="<?= e(url_for('login')) ?>">Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <?php if (!empty($flashes)): ?>
    <ul class="flashes">
        <?php foreach ($flashes as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?= $content ?>
</div>

<?php if ($current_user && empty($remember_me)): ?>
<dialog id="sessionTimeoutDialog" class="note-dialog">
    <div class="dlg-body">
        <h3>Session about to expire</h3>
        <p>You will be signed out in <strong id="sessionCountdown">—</strong> if you don't stay active.</p>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" id="sessionStayBtn" class="dlg-close">Stay signed in</button>
            <a href="<?= e(url_for('logout')) ?>" style="text-decoration: none;">
                <button type="button" class="dlg-close btn-danger">Log out now</button>
            </a>
        </div>
    </div>
</dialog>
<script>
(function () {
    const LIFETIME_MS = <?= (int) $session_lifetime_seconds ?> * 1000;
    const WARNING_MS = Math.min(120000, Math.max(30000, Math.floor(LIFETIME_MS / 4)));
    const dialog = document.getElementById('sessionTimeoutDialog');
    const countdown = document.getElementById('sessionCountdown');
    const stayBtn = document.getElementById('sessionStayBtn');

    let expiryTime = Date.now() + LIFETIME_MS;
    let warned = false;

    stayBtn.addEventListener('click', async () => {
        try {
            const resp = await fetch('/admin/session.php', { credentials: 'same-origin' });
            if (!resp.ok || resp.redirected) {
                window.location.href = '<?= e(url_for('login', ['reason' => 'timeout'])) ?>';
                return;
            }
        } catch (err) {
            window.location.href = '<?= e(url_for('login', ['reason' => 'timeout'])) ?>';
            return;
        }
        dialog.close();
        expiryTime = Date.now() + LIFETIME_MS;
        warned = false;
    });

    function fmt(secs) {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return m > 0 ? `${m}m ${s}s` : `${s}s`;
    }

    setInterval(() => {
        const now = Date.now();
        if (now >= expiryTime) {
            window.location.href = '<?= e(url_for('login', ['reason' => 'timeout'])) ?>';
            return;
        }
        if (now >= expiryTime - WARNING_MS) {
            if (!warned) {
                warned = true;
                if (dialog.showModal) dialog.showModal();
            }
            countdown.textContent = fmt(Math.max(0, Math.floor((expiryTime - now) / 1000)));
        }
    }, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
