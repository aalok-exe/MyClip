<?php
/**
 * pad/snapshot.php — Save current pad as a permanent named note.
 * Accepts regular form POST.
 * Returns JSON: { ok, url } or { error }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

boot_session();
$me = current_user();

if (!$me) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'post_only']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$pad   = get_or_create_pad($me['id'], $me['username']);

if (empty(trim($pad['content']))) {
    echo json_encode(['error' => 'Pad is empty — nothing to save.']);
    exit;
}

$saved = create_note($me['id'], [
    'content'  => $pad['content'],
    'title'    => $title ?: date('M j, Y g:i a'),
    'syntax'   => $pad['syntax'],
    'tags'     => '',
    'is_public'=> 1,
    'password' => '',
]);

echo json_encode([
    'ok'  => true,
    'url' => note_url($saved),
]);
