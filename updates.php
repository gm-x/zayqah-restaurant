<?php
// JSON feed for the notification bell; polled by assets/js/app.js.
require __DIR__ . '/app/bootstrap.php';

$u = user();
if (!$u) json_out(['error' => 'Sign in to see updates.'], 401);

$latest = all('SELECT id, title, body, path FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$u['id']]);
json_out([
    'unread' => (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$u['id']]),
    'latest' => array_map(fn($n) => ['id' => (int)$n['id'], 'title' => $n['title'], 'body' => $n['body'], 'href' => url($n['path'])], $latest),
]);
