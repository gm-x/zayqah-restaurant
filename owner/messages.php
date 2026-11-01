<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
if (post()) {
    $message = one('SELECT * FROM contact_messages WHERE id = ?', [(int)val('id')]);
    if ($message && val('action') === 'toggle') {
        q('UPDATE contact_messages SET status = ? WHERE id = ?', [$message['status'] === 'new' ? 'resolved' : 'new', $message['id']]);
        flash($message['status'] === 'new' ? 'Marked as resolved.' : 'Marked as new.');
    } elseif ($message && val('action') === 'delete') {
        q('DELETE FROM contact_messages WHERE id = ?', [$message['id']]);
        flash('Message deleted.');
    }
    go('owner/messages.php?show=' . rawurlencode(param('show')));
}

$subjects = ['order' => 'Order question', 'booking' => 'Booking or event', 'feedback' => 'Feedback', 'other' => 'Other'];
$show = param('show') === 'all' ? 'all' : 'new';
$messages = all('SELECT * FROM contact_messages ' . ($show === 'new' ? "WHERE status = 'new' " : '') . 'ORDER BY id DESC LIMIT 100');
$title = 'Messages';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><h1>Messages</h1></div>
<div class="toolbar">
    <nav class="tabs"><a href="?show=new" <?= $show === 'new' ? 'aria-current="page"' : '' ?>>Unresolved</a><a href="?show=all" <?= $show === 'all' ? 'aria-current="page"' : '' ?>>All</a></nav>
</div>
<?php if (!$messages): ?><section class="card"><div class="empty-state small"><?= icon('inbox', 28) ?><p>No messages here.</p></div></section><?php endif ?>
<div class="stack">
    <?php foreach ($messages as $m): ?>
        <article class="card message <?= $m['status'] === 'new' ? 'is-unread' : '' ?>" id="message-<?= (int)$m['id'] ?>">
            <div class="card-head">
                <div><strong><?= e($m['full_name']) ?></strong> <span class="chip"><?= e($subjects[$m['subject']] ?? $m['subject']) ?></span><br>
                    <small class="muted"><?= e(local_time($m['created_at'])) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?><?= $m['user_id'] ? ' · Registered customer' : '' ?></small></div>
                <?= $m['status'] === 'new' ? '<span class="badge badge-placed">New</span>' : '<span class="badge badge-completed">Resolved</span>' ?>
            </div>
            <p class="message-body"><?= nl2br(e($m['message'])) ?></p>
            <div class="row-gap">
                <a class="btn btn-primary btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: your message to ' . setting('name'))) ?>"><?= icon('mail', 15) ?> Reply by email</a>
                <form method="post"><?= csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $m['status'] === 'new' ? 'Mark resolved' : 'Mark as new' ?></button></form>
                <form method="post" data-confirm="Delete this message?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger-text" type="submit">Delete</button></form>
            </div>
        </article>
    <?php endforeach ?>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
