<?php
/**
 * Long-poll endpoint for shared note live sync.
 * Called by view.php every N seconds to check if the note changed.
 *
 * GET params:
 *   slug        internal note slug
 *   since       unix timestamp the client last received content
 *
 * Returns JSON:
 *   { changed: false }
 *   { changed: true, content: "...", updated_at: "...", views: 123, syntax: "php" }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$slug  = trim($_GET['slug']  ?? '');
$since = (int)($_GET['since'] ?? 0);

if (!$slug) {
    echo json_encode(['error' => 'missing slug']);
    exit;
}

$st = db()->prepare(
    'SELECT content, syntax, views, updated_at FROM notes WHERE slug = ? LIMIT 1'
);
$st->execute([$slug]);
$note = $st->fetch();

if (!$note) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$updated_ts = strtotime($note['updated_at']);

if ($updated_ts > $since) {
    echo json_encode([
        'changed'    => true,
        'content'    => $note['content'],
        'syntax'     => $note['syntax'],
        'views'      => (int)$note['views'],
        'updated_at' => $note['updated_at'],
        'updated_ts' => $updated_ts,
    ]);
} else {
    echo json_encode(['changed' => false]);
}
