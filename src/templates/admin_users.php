<?php
$actor_is_super = !empty($current_user['is_super']);
$role_choices = $actor_is_super
    ? [ROLE_USER, ROLE_GROUP_ADMIN, ROLE_SUPER_ADMIN]
    : [ROLE_USER, ROLE_GROUP_ADMIN];
?>
<h2>Manage Users</h2>
<p class="muted">
    Add the new user's name, email and initial password. You'll get an email template to send them. They can change
    their own details later from Profile.
    <?php if (!$actor_is_super): ?>
        New users join <strong><?= e($current_group_name ?? 'your group') ?></strong>.
    <?php endif; ?>
</p>
<form method="POST">
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="users_name" placeholder="First and last name" required>
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="username_email" placeholder="jdoe@example.com" required>
        <p class="field-hint" style="margin-top: 0.4rem;">This is the address they log in with.</p>
    </div>
    <div class="form-group">
        <label>Initial password</label>
        <input type="text" name="password" placeholder="At least 6 characters" required minlength="6">
    </div>
    <div class="form-group">
        <label>Role</label>
        <select name="role" id="createRole">
            <?php foreach ($role_choices as $role_option): ?>
                <option value="<?= e($role_option) ?>"><?= e(role_label($role_option)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($actor_is_super): ?>
        <div class="form-group" id="createGroup">
            <label>Group</label>
            <?php if (!empty($groups)): ?>
                <select name="group_id" >
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <p class="field-hint" style="margin-top: 0.4rem;">
                    No groups exist yet. <a href="<?= e(url_for('admin_groups')) ?>">Create a group</a> before adding users.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <button type="submit">Create User</button>
</form>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>Users</h3>
<table>
    <colgroup>
        <col style="width: 32%">
        <col style="width: 16%">
        <col style="width: 52%">
    </colgroup>
    <thead>
        <tr>
            <th>Name &amp; email</th>
            <th>Group</th>
            <th>Role &amp; actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <?php
            $row_id = (int) $u['id'];
            $row_role = $u['role'] ?: ROLE_USER;
            $row_name = $u['users_name'] ?? '';
            $is_self = $row_id === (int) $current_user['id'];
            $row_is_super = $row_role === ROLE_SUPER_ADMIN;
            // A group admin may only touch plain members of their own group.
            $can_manage_row = $actor_is_super || (!$row_is_super && !$is_self);
            ?>
            <tr>
                <td>
                    <?= $row_name !== '' ? e($row_name) : '<span class="muted">no name on file</span>' ?>
                    <?php if ($row_is_super): ?><span class="badge badge-admin">super admin</span>
                    <?php elseif ($row_role === ROLE_GROUP_ADMIN): ?><span class="badge badge-admin">group admin</span>
                    <?php endif; ?>
                    <?php if ($is_self): ?><span class="badge badge-active">you</span><?php endif; ?>
                    <?php if ($u['pending_reset']): ?><span class="badge badge-reset">reset requested</span><?php endif; ?>
                    <div class="muted" style="font-size: 0.8rem;"><?= e($u['username_email']) ?></div>
                </td>
                <td><?= $u['group_name'] !== null ? e($u['group_name']) : '<span class="muted">all groups</span>' ?></td>
                <td>
                    <div style="display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center;">
                        <?php if ($u['pending_reset'] && $can_manage_row): ?>
                            <form method="POST" action="<?= e(url_for('admin_reset_password', ['user_id' => $u['id']])) ?>"
                                style="margin: 0; display: flex; gap: 0.3rem;">
                                <input type="text" name="password" placeholder="new password" minlength="6" required
                                    style="width: 130px; padding: 0.3rem 0.4rem; font-size: 0.8rem;">
                                <button type="submit" class="btn-small">Reset</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$is_self && $can_manage_row): ?>
                            <form method="POST" action="<?= e(url_for('admin_set_role', ['user_id' => $u['id']])) ?>"
                                style="margin: 0; display: flex; gap: 0.3rem; align-items: center;">
                                <select name="role" class="row-role" style="width: auto; padding: 0.3rem 0.4rem; font-size: 0.8rem;">
                                    <?php foreach ($role_choices as $role_option): ?>
                                        <option value="<?= e($role_option) ?>" <?= $role_option === $row_role ? 'selected' : '' ?>>
                                            <?= e(role_label($role_option)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($actor_is_super && !empty($groups)): ?>
                                    <select name="group_id" class="row-group" style="width: auto; padding: 0.3rem 0.4rem; font-size: 0.8rem;">
                                        <?php foreach ($groups as $g): ?>
                                            <option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === (int) $u['group_id'] ? 'selected' : '' ?>>
                                                <?= e($g['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <button type="submit" class="btn-small">Save</button>
                            </form>
                            <button type="button" class="btn-small btn-danger remove-user-btn"
                                    data-user-id="<?= $row_id ?>"
                                    data-user-name="<?= e($row_name !== '' ? $row_name : $u['username_email']) ?>"
                                    data-action="<?= e(url_for('admin_delete_user', ['user_id' => $u['id']])) ?>">
                                Remove
                            </button>
                            <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($actor_is_super): ?>
    <div class="auth-links">
        <p><a href="<?= e(url_for('admin_groups')) ?>">Manage groups</a></p>
    </div>
<?php endif; ?>

<?php
$transfer_options = [];
foreach ($users as $opt) {
    $transfer_options[] = [
        'id' => (int) $opt['id'],
        'label' => ($opt['users_name'] ?? '') !== '' ? $opt['users_name'] : $opt['username_email'],
    ];
}
?>
<dialog id="removeUserDialog" class="note-dialog">
    <form id="removeUserForm" method="POST" class="dlg-body">
        <h3>Remove <span id="removeUserName"></span></h3>
        <p class="muted">What should happen to their short links?</p>

        <label class="choice" id="removeTransferChoice">
            <input type="radio" name="link_action" value="transfer" checked>
            <span class="choice-title">Transfer their links to someone else</span>
            <span class="choice-desc">The short URLs stay live. Creator becomes the person you pick.</span>
        </label>
        <div class="form-group" id="removeTransferGroup" style="margin-left: 1.7rem;">
            <select name="transfer_to" id="removeTransferTo"></select>
        </div>

        <label class="choice">
            <input type="radio" name="link_action" value="delete">
            <span class="choice-title">Delete all of their links</span>
            <span class="choice-desc">Those short URLs will stop working.</span>
        </label>

        <div style="display: flex; gap: 0.5rem; margin-top: 1.2rem;">
            <button type="submit" class="btn-danger">Remove user</button>
            <button type="button" class="dlg-close" id="removeUserCancel">Cancel</button>
        </div>
    </form>
</dialog>
<script>
(function () {
    const dialog = document.getElementById('removeUserDialog');
    const form = document.getElementById('removeUserForm');
    const nameEl = document.getElementById('removeUserName');
    const select = document.getElementById('removeTransferTo');
    const transferGroup = document.getElementById('removeTransferGroup');
    const transferChoice = document.getElementById('removeTransferChoice');
    const people = <?= json_encode($transfer_options, JSON_UNESCAPED_UNICODE) ?>;

    function selectedAction() {
        const checked = form.querySelector('input[name="link_action"]:checked');
        return checked ? checked.value : 'delete';
    }

    function syncTransferUi() {
        const transferring = selectedAction() === 'transfer';
        transferGroup.style.display = transferring ? '' : 'none';
        select.required = transferring && select.options.length > 0;
        select.disabled = !transferring;
    }

    function fillPeople(exceptId) {
        select.innerHTML = '';
        people.filter((p) => p.id !== exceptId).forEach((p) => {
            const opt = document.createElement('option');
            opt.value = String(p.id);
            opt.textContent = p.label;
            select.appendChild(opt);
        });
        const canTransfer = select.options.length > 0;
        transferChoice.style.display = canTransfer ? '' : 'none';
        const deleteRadio = form.querySelector('input[name="link_action"][value="delete"]');
        const transferRadio = form.querySelector('input[name="link_action"][value="transfer"]');
        if (!canTransfer) {
            deleteRadio.checked = true;
        } else {
            transferRadio.checked = true;
        }
        syncTransferUi();
    }

    document.querySelectorAll('.remove-user-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const userId = Number(btn.dataset.userId);
            nameEl.textContent = btn.dataset.userName || 'this user';
            form.action = btn.dataset.action;
            fillPeople(userId);
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
    });

    form.querySelectorAll('input[name="link_action"]').forEach((radio) => {
        radio.addEventListener('change', syncTransferUi);
    });

    document.getElementById('removeUserCancel').addEventListener('click', () => dialog.close());
})();
</script>


<?php if (!empty($new_creds)): ?>
    <dialog id="credsDialog" class="note-dialog">
        <div class="dlg-body">
            <h3>User created — send them this email</h3>
            <p class="muted">Copy the message below and paste it into an email to
                <strong><?= e($new_creds['username_email']) ?></strong>.</p>
            <pre id="credsBody">Hi<?= !empty($new_creds['users_name']) ? ' ' . e($new_creds['users_name']) : '' ?>,

    You've been given access to the <?= e(SHORT_LINK_DOMAIN) ?> Link Manager as a <?= e(role_label($new_creds['role'] ?? ROLE_USER)) ?><?= !empty($new_creds['group_name']) ? ' in ' . e($new_creds['group_name']) : '' ?>.

    Email: <?= e($new_creds['username_email']) ?>

    Password: <?= e($new_creds['password']) ?>

    Login: <?= e($new_creds['login_url']) ?>


    You can change your email or password anytime from the Profile page after logging in.</pre>
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
<script>
(function () {
    const SUPER = <?= json_encode(ROLE_SUPER_ADMIN) ?>;

    function syncGroup(roleSelect, groupEl) {
        if (!roleSelect || !groupEl) return;
        const show = roleSelect.value !== SUPER;
        groupEl.style.display = show ? '' : 'none';
        if ('disabled' in groupEl) {
            groupEl.disabled = !show;
        }
        groupEl.querySelectorAll('select').forEach((sel) => {
            sel.disabled = !show;
        });
    }

    const createRole = document.getElementById('createRole');
    const createGroup = document.getElementById('createGroup');
    if (createRole) {
        createRole.addEventListener('change', () => syncGroup(createRole, createGroup));
        syncGroup(createRole, createGroup);
    }

    document.querySelectorAll('.row-role').forEach((roleSelect) => {
        const groupSelect = roleSelect.form.querySelector('.row-group');
        roleSelect.addEventListener('change', () => syncGroup(roleSelect, groupSelect));
        syncGroup(roleSelect, groupSelect);
    });
})();
</script>