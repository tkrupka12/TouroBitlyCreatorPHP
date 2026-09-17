<?php
function stacked_date_parts(?string $ts, int $time_len = 5): array {
    if (!$ts) return [null, null];
    $parts = explode(' ', $ts, 2);
    $date = $parts[0] ?? '';
    $time = isset($parts[1]) ? substr($parts[1], 0, $time_len) : '';
    return [$date, $time];
}
?>
<h2>Create Custom Link</h2>
<form id="urlForm">
    <div class="form-group">
        <label>Destination URL</label>
        <input type="text" id="url" placeholder="https://example.com/long-path" required>
    </div>
    <div class="form-group">
        <label>Custom Slash Suffix</label>
        <input type="text" id="slug" placeholder="my-custom-slug" required>
    </div>
    <div class="form-group">
        <label>Expires at <span class="muted">(optional — leave blank for never)</span></label>
        <input type="datetime-local" id="expires_at">
    </div>
    <div class="form-group">
        <label>Notes <span class="muted">(optional)</span></label>
        <textarea id="notes" rows="3" placeholder="What is this link for?" style="width:100%; padding:0.75rem; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-family:inherit; font-size:1rem;"></textarea>
    </div>
    <button type="submit">Generate Link</button>
</form>
<div id="result" style="margin-top: 1rem; display:none; padding: 0.75rem; background:#e2f0d9; border-radius:6px;"></div>

<hr style="margin: 2rem 0; border:0; border-top:1px solid #ddd;">

<h3>All Links</h3>
<table>
    <colgroup>
        <col style="width: 18%">
        <col style="width: 12%">
        <col style="width: 5%">
        <col style="width: 9%">
        <col style="width: 8%">
        <col style="width: 8%">
        <col style="width: 9%">
        <col style="width: 9%">
        <col style="width: 9%">
        <col style="width: 13%">
    </colgroup>
    <thead>
        <tr>
            <th>Destination</th>
            <th>Short</th>
            <th>Clicks</th>
            <th>Last clicked</th>
            <th>Creator</th>
            <th>Created</th>
            <th>Expires</th>
            <th>Last edited</th>
            <th>Notes</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($all_links)): foreach ($all_links as $link):
        [$c_date, $c_time] = stacked_date_parts($link['created_at']);
        [$e_date, $e_time] = stacked_date_parts($link['expires_at']);
        [$u_date, $u_time] = stacked_date_parts($link['updated_at']);
        [$clicked_date, $clicked_time] = stacked_date_parts($link['last_clicked_at'] ?? null, 8);
    ?>
        <tr data-link-id="<?= (int)$link['id'] ?>">
            <td class="<?= $link['expired'] ? 'expired' : '' ?>">
                <div class="tt" data-tooltip="<?= e($link['url']) ?>">
                    <a class="truncate" href="<?= e($link['url']) ?>" target="_blank"><?= e($link['url']) ?></a>
                </div>
            </td>
            <td>
                <div class="tt" data-tooltip="<?= e(short_label($link['slug'])) ?>">
                    <a class="truncate short-link" data-link-id="<?= (int)$link['id'] ?>" href="<?= e(short_url($link['slug'])) ?>" target="_blank"><strong><?= e(short_label($link['slug'])) ?></strong></a>
                </div>
                <button type="button" class="copy-btn"
                        data-copy="<?= e(short_url($link['slug'])) ?>">
                    &#128203; Copy
                </button>
                <?php if ($link['expired']): ?><span class="expired-badge">expired</span><?php endif; ?>
            </td>
            <td class="click-cell" data-link-id="<?= (int)$link['id'] ?>"><strong><?= (int)$link['clicks'] ?></strong></td>
            <td class="muted stacked-date last-clicked-cell" data-link-id="<?= (int)$link['id'] ?>">
                <?php if ($clicked_date): ?>
                    <?= e($clicked_date) ?><br>
                    <span class="time"><?= e($clicked_time) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="truncate" title="<?= e($link['creator']) ?>"><?= e($link['creator']) ?></td>
            <td class="muted stacked-date">
                <?php if ($c_date): ?>
                    <?= e($c_date) ?><br>
                    <span class="time"><?= e($c_time) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="muted stacked-date">
                <?php if ($e_date): ?>
                    <?= e($e_date) ?><br>
                    <span class="time"><?= e($e_time) ?></span>
                <?php else: ?>never<?php endif; ?>
            </td>
            <td class="muted stacked-date updated-cell" data-link-id="<?= (int)$link['id'] ?>">
                <?php if ($u_date): ?>
                    <?= e($u_date) ?><br>
                    <span class="time"><?= e($u_time) ?></span>
                    <?php if (!empty($link['updated_by'])): ?><br><span class="time">by <?= e($link['updated_by']) ?></span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="note-cell" data-link-id="<?= (int)$link['id'] ?>" data-slug="<?= e($link['slug']) ?>" data-note="<?= e($link['notes']) ?>">
                <?php if (!empty($link['notes'])): ?>
                <div class="tt" data-tooltip="<?= e($link['notes']) ?>" style="display: inline-block;">
                    <button type="button" class="note-btn note-open">&#128221; Note</button>
                </div>
                <?php else: ?>
                <button type="button" class="note-btn note-btn-empty note-open">+ Add note</button>
                <?php endif; ?>
            </td>
            <td>
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                    <?php if (empty($link['is_root'])): ?>
                    <a href="<?= e(url_for('edit_link', ['link_id' => $link['id']])) ?>">
                        <button type="button" class="btn-small">Update</button>
                    </a>
                    <?php endif; ?>
                    <?php if ($link['can_delete']): ?>
                    <form method="POST" action="<?= e(url_for('delete_link', ['link_id' => $link['id']])) ?>"
                          onsubmit="return confirm('Delete <?= e(short_label($link['slug'])) ?>? This cannot be undone.');"
                          style="margin: 0;">
                        <button type="submit" class="btn-small btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; else: ?>
        <tr><td colspan="10" class="muted">No links yet.</td></tr>
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
    document.getElementById('urlForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = document.getElementById('url').value;
        const slug = document.getElementById('slug').value;
        const expires_at = document.getElementById('expires_at').value;
        const notes = document.getElementById('notes').value;
        const resultDiv = document.getElementById('result');

        const response = await fetch('/shorten', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, slug, expires_at, notes })
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
        const slug = activeCell.dataset.slug || '';
        noteTitle.textContent = slug ? ('Note for tou.ro/' + slug) : 'Note for tou.ro';
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
            const resp = await fetch(`/links/${linkId}/notes`, {
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
            const slug = activeCell.dataset.slug;
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

    function stackedTimestampHtml(ts) {
        if (!ts) return '—';
        const parts = String(ts).split(' ');
        const time = parts[1] ? parts[1].slice(0, 8) : '';
        return parts[0] + '<br><span class="time">' + time + '</span>';
    }

    function updateLastClickedCell(linkId, ts) {
        const cell = document.querySelector('.last-clicked-cell[data-link-id="' + linkId + '"]');
        if (!cell) return;
        cell.innerHTML = stackedTimestampHtml(ts);
    }

    function localNowStamp() {
        const pad = n => String(n).padStart(2, '0');
        const d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function bumpLinkCell(link) {
        const linkId = link.dataset.linkId;
        const cell = document.querySelector('.click-cell[data-link-id="' + linkId + '"] strong');
        if (cell) {
            const current = parseInt(cell.textContent, 10) || 0;
            cell.textContent = current + 1;
        }
        updateLastClickedCell(linkId, localNowStamp());
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
            const resp = await fetch('/links/clicks', { credentials: 'same-origin' });
            if (!resp.ok) return;
            const data = await resp.json();
            Object.entries(data).forEach(([id, info]) => {
                const count = (info && typeof info === 'object') ? info.clicks : info;
                updateClickCell(id, count);
                if (info && typeof info === 'object') {
                    updateLastClickedCell(id, info.last_clicked_at);
                }
            });
        } catch (err) { /* ignore transient errors */ }
    }
    setInterval(syncClicks, 3000);
    window.addEventListener('focus', syncClicks);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncClicks();
    });
</script>
