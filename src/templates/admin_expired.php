<?php $actor_is_super = !empty($current_user['is_super']); ?>
<h2>Expired Links</h2>
<p class="muted">
    Admins only. These links are hidden from All Links and their short URLs now show the expired page. Give one a future expiration date from Update to bring it back.
    <?php if ($actor_is_super): ?>
    You are seeing every group.
    <?php else: ?>
    You are seeing <strong><?= e($current_group_name ?? 'your group') ?></strong>.
    <?php endif; ?>
</p>

<table>
    <colgroup>
        <col style="width: <?= $actor_is_super ? '14%' : '19%' ?>">
        <col style="width: 13%">
        <col style="width: 6%">
        <col style="width: 8%">
        <col style="width: 8%">
        <?php if ($actor_is_super): ?><col style="width: 8%"><?php endif; ?>
        <col style="width: 8%">
        <col style="width: 8%">
        <col style="width: <?= $actor_is_super ? '12%' : '14%' ?>">
        <col style="width: <?= $actor_is_super ? '15%' : '16%' ?>">
    </colgroup>
    <thead>
        <tr>
            <th>Destination</th>
            <th>Short</th>
            <th>Clicks</th>
            <th>Last clicked</th>
            <th>Creator</th>
            <?php if ($actor_is_super): ?><th>Group</th><?php endif; ?>
            <th>Created</th>
            <th>Expired</th>
            <th>Notes</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($expired_links)): ?>
    <?php foreach ($expired_links as $link): ?>
        <?php
            [$created_date, $created_time] = stacked_date_parts($link['created_at'] ?? null);
            [$expires_date, $expires_time] = stacked_date_parts($link['expires_at'] ?? null);
            [$clicked_date, $clicked_time] = stacked_date_parts($link['last_clicked_at'] ?? null);
            $label = short_link_label((string) $link['short_url']);
        ?>
        <tr data-link-id="<?= e($link['id']) ?>">
            <td class="expired">
                <div class="tt" data-tooltip="<?= e($link['url']) ?>">
                    <a class="truncate" href="<?= e($link['url']) ?>" target="_blank"><?= e($link['url']) ?></a>
                </div>
            </td>
            <td class="expired">
                <div class="tt" data-tooltip="<?= e($label) ?>">
                    <span class="truncate"><strong><?= e($label) ?></strong></span>
                </div>
                <span class="expired-badge">expired</span>
            </td>
            <td><strong><?= e($link['clicks']) ?></strong></td>
            <td class="muted stacked-date">
                <?php if (!empty($link['last_clicked_at'])): ?>
                    <?= e($clicked_date) ?><br>
                    <span class="time"><?= e($clicked_time) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
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
                <?= e($expires_date) ?><br>
                <span class="time"><?= e($expires_time) ?></span>
            </td>
            <td>
                <?php if ($link['notes']): ?>
                <div class="tt" data-tooltip="<?= e($link['notes']) ?>">
                    <span class="truncate muted"><?= e($link['notes']) ?></span>
                </div>
                <?php else: ?>
                <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                    <a href="<?= e(url_for('edit_link', ['link_id' => $link['id']])) ?>">
                        <button type="button" class="btn-small">Update</button>
                    </a>
                    <?php if (empty($link['is_root'])): ?>
                    <form method="POST" action="<?= e(url_for('delete_link', ['link_id' => $link['id']])) ?>"
                          onsubmit="return confirm('Delete <?= e($label) ?>? This cannot be undone.');"
                          style="margin: 0;">
                        <input type="hidden" name="redirect_to" value="expired">
                        <button type="submit" class="btn-small btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="<?= $actor_is_super ? 10 : 9 ?>" class="muted">No expired links.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="auth-links">
    <p><a href="<?= e(url_for('index')) ?>">&larr; Back to all links</a></p>
</div>
