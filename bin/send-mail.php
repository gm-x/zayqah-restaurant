<?php
// Delivers queued email. Add --retry-failed to retry earlier failures.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
if (in_array('--retry-failed', $argv, true)) q("UPDATE outbox SET status = 'pending' WHERE status = 'failed'");
send_outbox();
echo "Mail queue processed.\n";
