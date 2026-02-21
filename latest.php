<?php
/**
 * latest.php — returns the slug of the most recent public note as JSON.
 * Used by index.php's live poller to detect when a newer note has been published.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$st = db()->prepare(
    'SELECT slug, updated_at FROM notes
     WHERE is_public = 1 AND password_hash IS NULL
     ORDER BY updated_at DESC LIMIT 1'
);
$st->execute();
$row = $st->fetch();

echo json_encode($row ?: ['slug' => null, 'updated_at' => null]);
