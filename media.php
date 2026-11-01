<?php
// Serves uploaded images (dish photos, homepage slides, logo) from protected storage.
require __DIR__ . '/app/bootstrap.php';

$id = param('id');
$file = preg_match('/^[a-f0-9]{48}$/', $id) ? one('SELECT * FROM files WHERE id = ?', [$id]) : null;
$path = $file ? ROOT . '/storage/uploads/' . basename($file['path']) : '';
if (!$file || !is_file($path) || !in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header_remove('Cache-Control');
header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=604800, immutable'); // file ids are random and never reused
readfile($path);
