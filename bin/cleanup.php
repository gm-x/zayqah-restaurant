<?php
// Removes expired tokens, old rate-limit records and uploads no longer referenced. Run daily from cron.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
q('DELETE FROM tokens WHERE expires_at <= ?', [now()]);
q('DELETE FROM email_changes WHERE expires_at <= ?', [now()]);
q('DELETE FROM delivery_links WHERE expires_at <= ?', [now()]);
q('DELETE FROM attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
$removed = 0;
foreach (glob(ROOT . '/storage/uploads/*.{jpg,png,webp}', GLOB_BRACE) as $file) {
    $id = pathinfo($file, PATHINFO_FILENAME);
    if (filemtime($file) < time() - 86400 && !one('SELECT id FROM files WHERE id = ?', [$id])) {
        unlink($file);
        $removed++;
    }
}
echo "Cleanup complete. Removed $removed unused upload(s).\n";
