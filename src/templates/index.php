<?php $actor_is_super = !empty($current_user['is_super']); ?>
<h2>Create a Tou.ro short link</h2>
<form id="urlForm">
    <div class="section">
        <h3>Destination</h3>
        <p class="field-hint">Where should this link send people?</p>
        <input type="text" id="url" placeholder="https://www.example.com/long-path" required>
    </div>

    <?php if ($actor_is_super): ?>
    <div class="section">
        <h3>Group</h3>
        <p class="field-hint">Which group should own this link?</p>
        <?php if (!empty($groups)): ?>
        <select id="linkGroup" required>
            <?php foreach ($groups as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <p class="field-hint">
            No groups exist yet. <a href="<?= e(url_for('admin_groups')) ?>">Create a group</a> before adding links.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h3>Choose your short link</h3>
        <label class="choice">
            <input type="radio" name="url_mode" value="custom" checked>
            <span class="choice-title">Create a custom link</span>
            <span class="choice-desc">Create an easy-to-remember link.</span>
        </label>
        <label class="choice">
            <input type="radio" name="url_mode" value="random">
            <span class="choice-title">Generate a link automatically</span>
            <span class="choice-desc"> A unique short link will be generated for you.</span>
        </label>
    </div>

    <div class="section" id="customUrlGroup">
        <h3>Your short link</h3>
        <div class="url-row">
            <span class="url-prefix"><?= e(SHORT_LINK_DOMAIN) ?>/</span>
            <input type="text" id="short_url" placeholder="admissions" autocapitalize="off" autocorrect="off"
                   spellcheck="false" style="text-transform: lowercase;" required>
        </div>
        <p class="field-hint" style="margin: 0.5rem 0 0;">Lowercase letters, numbers, dashes, and underscores only.</p>
    </div>

    <div class="section">
        <h3>Link preview</h3>
        <div class="link-preview" id="linkPreview"></div>
    </div>

    <div class="section">
        <h3>Expiration</h3>
        <label class="choice">
            <input type="radio" name="expiry_mode" value="never" checked>
            <span class="choice-title">Never expires</span>
        </label>
        <label class="choice">
            <input type="radio" name="expiry_mode" value="date">
            <span class="choice-title">Set an expiration date</span>
        </label>
        <div id="expiryGroup" style="display: none;">
            <input type="datetime-local" id="expires_at">
        </div>
    </div>

    <div class="section">
        <h3>Optional details</h3>
        <label for="notes">Notes</label>
        <textarea id="notes" rows="3" placeholder="Add a note for your team..." style="width:100%; padding:0.75rem; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-family:inherit; font-size:1rem;"></textarea>
    </div>

    <button type="submit">Create short link</button>
</form>
<div id="result" style="margin-top: 1rem; display:none; padding: 0.75rem; background:#e2f0d9; border-radius:6px;"></div>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>All Links</h3>
<table>
    <colgroup>
        <col style="width: <?= $actor_is_super ? '14%' : '19%' ?>">
        <col style="width: 14%">
        <col style="width: 7%">
        <col style="width: 8%">
        <?php if ($actor_is_super): ?><col style="width: 9%"><?php endif; ?>
        <col style="width: 8%">
        <col style="width: 8%">
        <col style="width: 9%">
        <col style="width: 9%">
        <col style="width: <?= $actor_is_super ? '14%' : '18%' ?>">
    </colgroup>
    <thead>
        <tr>
            <th>Destination</th>
            <th>Short</th>
            <th>Clicks</th>
            <th>Creator</th>
            <?php if ($actor_is_super): ?><th>Group</th><?php endif; ?>
            <th>Created</th>
            <th>Expires</th>
            <th>Last edited</th>
            <th>Notes</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($all_links)): ?>
    <?php foreach ($all_links as $link): ?>
        <?php
            [$created_date, $created_time] = stacked_date_parts($link['created_at'] ?? null);
            [$expires_date, $expires_time] = stacked_date_parts($link['expires_at'] ?? null);
            [$updated_date, $updated_time] = stacked_date_parts($link['updated_at'] ?? null);
        ?>
        <tr data-link-id="<?= e($link['id']) ?>">
            <td class="<?= !empty($link['expired']) ? 'expired' : '' ?>">
                <div class="tt" data-tooltip="<?= e($link['url']) ?>">
                    <a class="truncate" href="<?= e($link['url']) ?>" target="_blank"><?= e($link['url']) ?></a>
                </div>
            </td>
            <td>
                <div class="tt" data-tooltip="<?= e(SHORT_LINK_DOMAIN) ?>/<?= e($link['short_url']) ?>">
                    <a class="truncate short-link" data-link-id="<?= e($link['id']) ?>" href="<?= e(url_for('redirect_to_url', ['short_url' => $link['short_url']])) ?>" target="_blank"><strong><?= e(SHORT_LINK_DOMAIN) ?>/<?= e($link['short_url']) ?></strong></a>
                </div>
                <button type="button" class="copy-btn"
                        data-copy="<?= e(url_for('redirect_to_url', ['short_url' => $link['short_url']], true)) ?>">
                    &#128203; Copy
                </button>
                <?php if (!empty($link['expired'])): ?><span class="expired-badge">expired</span><?php endif; ?>
            </td>
            <td class="click-cell" data-link-id="<?= e($link['id']) ?>"><strong><?= e($link['clicks']) ?></strong></td>
            <td title="<?= e($link['creator']) ?>"><span class="truncate"><?= e($link['creator']) ?></span></td>
            <?php if ($actor_is_super): ?>
            <td title="<?= e($link['group_name'] ?? '') ?>">
                <span class="truncate"><?= $link['group_name'] !== null ? e($link['group_name']) : '<span class="muted">none</span>' ?></span>
            </td>
            <?php endif; ?>
            <td class="muted stacked-date">
                <?php if ($link['created_at']): ?>
                    <?= e($created_date) ?><br>
                    <span class="time"><?= e($created_time) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="muted stacked-date">
                <?php if ($link['expires_at']): ?>
                    <?= e($expires_date) ?><br>
                    <span class="time"><?= e($expires_time) ?></span>
                <?php else: ?>never<?php endif; ?>
            </td>
            <td class="muted stacked-date updated-cell" data-link-id="<?= e($link['id']) ?>">
                <?php if ($link['updated_at']): ?>
                    <?= e($updated_date) ?><br>
                    <span class="time"><?= e($updated_time) ?></span>
                    <?php if ($link['updated_by']): ?><br><span class="time">by <?= e($link['updated_by']) ?></span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="note-cell" data-link-id="<?= e($link['id']) ?>" data-short-url="<?= e($link['short_url']) ?>" data-note="<?= e($link['notes']) ?>">
                <?php if ($link['notes']): ?>
                <div class="tt" data-tooltip="<?= e($link['notes']) ?>" style="display: inline-block;">
                    <button type="button" class="note-btn note-open">&#128221; Note</button>
                </div>
                <?php else: ?>
                <button type="button" class="note-btn note-btn-empty note-open">+ Add note</button>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($link['can_delete'])): ?>
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                    <a href="<?= e(url_for('edit_link', ['link_id' => $link['id']])) ?>">
                        <button type="button" class="btn-small">Update</button>
                    </a>
                    <form method="POST" action="<?= e(url_for('delete_link', ['link_id' => $link['id']])) ?>"
                          onsubmit="return confirm('Delete <?= e(SHORT_LINK_DOMAIN) ?>/<?= e($link['short_url']) ?>? This cannot be undone.');"
                          style="margin: 0;">
                        <button type="submit" class="btn-small btn-danger">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="<?= $actor_is_super ? 10 : 9 ?>" class="muted">No links yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<dialog class="note-dialog" id="noteDialog">
    <div class="dlg-body">
        <h3 id="noteTitle">Note</h3>
        <pre id="noteContent"></pre>
        <textarea id="noteEditor" rows="6"
                  style="display:none; width:100%; padding:0.75rem; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-family:inherit; font-size:0.95rem; margin-bottom:1rem;"></textarea>
        <div id="noteViewButtons" style="display: flex; gap: 0.5rem;">
            <button type="button" class="dlg-close" id="noteEditBtn">Edit</button>
            <button type="button" class="dlg-close" id="noteCloseBtn">Close</button>
        </div>
        <div id="noteEditButtons" style="display: none; gap: 0.5rem;">
            <button type="button" class="dlg-close" id="noteSaveBtn">Save</button>
            <button type="button" class="dlg-close" id="noteCancelBtn">Cancel</button>
        </div>
    </div>
</dialog>

<script>
    const SHORT_LINK_DOMAIN = <?= json_encode(SHORT_LINK_DOMAIN) ?>;

    const shortUrlInput = document.getElementById('short_url');
    const customUrlGroup = document.getElementById('customUrlGroup');
    const linkPreview = document.getElementById('linkPreview');
    const expiresInput = document.getElementById('expires_at');
    const expiryGroup = document.getElementById('expiryGroup');

    function currentMode() {
        const checked = document.querySelector('input[name="url_mode"]:checked');
        return checked ? checked.value : 'custom';
    }

    function renderPreview() {
        const random = currentMode() === 'random';
        const typed = shortUrlInput.value.trim().toLowerCase();
        linkPreview.textContent = SHORT_LINK_DOMAIN + '/';
        if (!random && typed) {
            linkPreview.appendChild(document.createTextNode(typed));
            return;
        }
        const hint = document.createElement('span');
        hint.className = 'placeholder';
        hint.textContent = random ? 'a unique 6-character link' : 'your-custom-link';
        linkPreview.appendChild(hint);
    }

    function applyMode() {
        const random = currentMode() === 'random';
        customUrlGroup.style.display = random ? 'none' : '';
        shortUrlInput.required = !random;
        renderPreview();
    }

    function applyExpiryMode() {
        const checked = document.querySelector('input[name="expiry_mode"]:checked');
        const useDate = checked && checked.value === 'date';
        expiryGroup.style.display = useDate ? '' : 'none';
        expiresInput.required = useDate;
        if (!useDate) expiresInput.value = '';
    }

    document.querySelectorAll('input[name="url_mode"]').forEach(radio => {
        radio.addEventListener('change', applyMode);
    });
    document.querySelectorAll('input[name="expiry_mode"]').forEach(radio => {
        radio.addEventListener('change', applyExpiryMode);
    });
    applyMode();
    applyExpiryMode();

    shortUrlInput.addEventListener('input', () => {
        const lowered = shortUrlInput.value.toLowerCase();
        if (shortUrlInput.value !== lowered) {
            const pos = shortUrlInput.selectionStart;
            shortUrlInput.value = lowered;
            shortUrlInput.setSelectionRange(pos, pos);
        }
        renderPreview();
    });

    document.getElementById('urlForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = document.getElementById('url').value;
        const mode = currentMode();
        const short_url = mode === 'random' ? '' : shortUrlInput.value.trim().toLowerCase();
        const expires_at = expiresInput.value;
        const notes = document.getElementById('notes').value;
        const groupSelect = document.getElementById('linkGroup');
        const group_id = groupSelect ? groupSelect.value : null;
        const resultDiv = document.getElementById('result');

        const response = await fetch('/admin/shorten.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, short_url, mode, expires_at, notes, group_id })
        });
        const data = await response.json();

        resultDiv.style.display = 'block';
        if (response.ok) {
            resultDiv.style.background = '#e2f0d9';
            resultDiv.innerHTML = `Success! <a href="${data.short_link}" target="_blank">${data.short_link}</a> — reload to see it in the table.`;
        } else {
            resultDiv.style.background = '#f8d7da';
            resultDiv.innerHTML = data.error;
        }
    });

    (function setMinExpiresAt() {
        const pad = n => String(n).padStart(2, '0');
        const d = new Date();
        const localNow = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        const input = document.getElementById('expires_at');
        if (input) input.min = localNow;
    })();

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.copy-btn');
        if (!btn) return;
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            const original = btn.innerHTML;
            btn.classList.add('copied');
            btn.textContent = 'Copied!';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = original;
            }, 1200);
        } catch (err) {
            btn.textContent = 'Copy failed';
        }
    });

    const noteDialog = document.getElementById('noteDialog');
    const noteTitle = document.getElementById('noteTitle');
    const noteContent = document.getElementById('noteContent');
    const noteEditor = document.getElementById('noteEditor');
    const noteViewButtons = document.getElementById('noteViewButtons');
    const noteEditButtons = document.getElementById('noteEditButtons');
    const noteEditBtn = document.getElementById('noteEditBtn');
    const noteCloseBtn = document.getElementById('noteCloseBtn');
    const noteSaveBtn = document.getElementById('noteSaveBtn');
    const noteCancelBtn = document.getElementById('noteCancelBtn');
    let activeCell = null;

    function setViewMode() {
        noteContent.style.display = '';
        noteEditor.style.display = 'none';
        noteViewButtons.style.display = 'flex';
        noteEditButtons.style.display = 'none';
    }

    function setEditMode() {
        noteContent.style.display = 'none';
        noteEditor.style.display = '';
        noteViewButtons.style.display = 'none';
        noteEditButtons.style.display = 'flex';
        noteEditor.value = activeCell ? (activeCell.dataset.note || '') : '';
        noteEditor.focus();
    }

    document.addEventListener('click', (e) => {
        const opener = e.target.closest('.note-open');
        if (!opener) return;
        activeCell = opener.closest('.note-cell');
        const note = activeCell.dataset.note || '';
        noteTitle.textContent = 'Note for ' + SHORT_LINK_DOMAIN + '/' + activeCell.dataset.shortUrl;
        noteContent.textContent = note;
        if (note) setViewMode(); else setEditMode();
        noteDialog.showModal();
    });

    noteEditBtn.addEventListener('click', setEditMode);
    noteCloseBtn.addEventListener('click', () => noteDialog.close());
    noteCancelBtn.addEventListener('click', () => {
        if (activeCell && activeCell.dataset.note) {
            setViewMode();
        } else {
            noteDialog.close();
        }
    });

    noteSaveBtn.addEventListener('click', async () => {
        if (!activeCell) return;
        const linkId = activeCell.dataset.linkId;
        const newNote = noteEditor.value;
        noteSaveBtn.disabled = true;
        try {
            const resp = await fetch(`/admin/notes.php?id=${encodeURIComponent(linkId)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notes: newNote }),
            });
            const data = await resp.json();
            if (!resp.ok) {
                alert(data.error || 'Failed to save note.');
                return;
            }
            activeCell.dataset.note = data.notes;
            const linkIdAttr = activeCell.dataset.linkId;
            if (data.notes) {
                activeCell.innerHTML =
                    '<div class="tt" data-tooltip="' + escapeAttr(data.notes) + '" style="display: inline-block;">' +
                        '<button type="button" class="note-btn note-open">&#128221; Note</button>' +
                    '</div>';
            } else {
                activeCell.innerHTML =
                    '<button type="button" class="note-btn note-btn-empty note-open">+ Add note</button>';
            }
            const updatedCell = document.querySelector('.updated-cell[data-link-id="' + linkIdAttr + '"]');
            if (updatedCell && data.updated_at) {
                const parts = data.updated_at.split(' ');
                const time = parts[1] ? parts[1].slice(0, 5) : '';
                updatedCell.innerHTML =
                    parts[0] + '<br><span class="time">' + time + '</span>' +
                    (data.updated_by ? '<br><span class="time">by ' + escapeText(data.updated_by) + '</span>' : '');
            }
            noteDialog.close();
        } catch (err) {
            alert('Network error saving note.');
        } finally {
            noteSaveBtn.disabled = false;
        }
    });

    function escapeAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeText(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function updateClickCell(linkId, value) {
        const cell = document.querySelector('.click-cell[data-link-id="' + linkId + '"]');
        if (!cell) return;
        const strong = cell.querySelector('strong');
        if (strong) strong.textContent = value;
    }

    function bumpLinkCell(link) {
        const linkId = link.dataset.linkId;
        const cell = document.querySelector('.click-cell[data-link-id="' + linkId + '"] strong');
        if (cell) {
            const current = parseInt(cell.textContent, 10) || 0;
            cell.textContent = current + 1;
        }
    }

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a.short-link');
        if (link) bumpLinkCell(link);
    });
    document.addEventListener('auxclick', (e) => {
        const link = e.target.closest('a.short-link');
        if (link) bumpLinkCell(link);
    });

    async function syncClicks() {
        try {
            const resp = await fetch('/admin/clicks.php', { credentials: 'same-origin' });
            if (!resp.ok) return;
            const data = await resp.json();
            Object.entries(data).forEach(([id, count]) => updateClickCell(id, count));
        } catch (err) { /* ignore transient errors */ }
    }
    setInterval(syncClicks, 3000);
    window.addEventListener('focus', syncClicks);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncClicks();
    });
</script>
