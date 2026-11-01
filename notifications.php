<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user(false);
if (post()) {
    if (val('action') === 'read-all') {
        q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $u['id']]);
        go('notifications.php');
    }
    $n = one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int)val('id'), $u['id']]);
    if ($n) {
        q('UPDATE notifications SET read_at = COALESCE(read_at, ?) WHERE id = ?', [now(), $n['id']]);
        go($n['path']);
    }
    go('notifications.php');
}
$rows = all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$u['id']]);
$title = 'Notifications';

require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow">
        <div class="row-between">
            <h1>Notifications</h1>
            <?php if ($rows): ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="read-all"><button class="btn btn-ghost btn-sm" type="submit">Mark all as read</button></form><?php endif ?>
        </div>
        <?php if (!$rows): ?>
            <div class="empty-state"><?= icon('bell', 36) ?><h2>You're all caught up</h2><p>Order updates will appear here.</p></div>
        <?php endif ?>
        <div class="list">
            <?php foreach ($rows as $n): ?>
                <form method="post" class="card list-row notification <?= $n['read_at'] ? '' : 'is-unread' ?>">
                    <?= csrf() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <div><strong><?= e($n['title']) ?></strong><small><?= e($n['body']) ?> · <?= e(local_time($n['created_at'])) ?></small></div>
                    <button class="btn btn-ghost btn-sm" type="submit">Open <?= icon('arrow', 14) ?></button>
                </form>
            <?php endforeach ?>
        </div>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
