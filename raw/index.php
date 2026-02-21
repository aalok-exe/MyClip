<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

boot_session();

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{4,32}$/', $slug)) { http_response_code(404); die('Not found'); }

$note = get_note($slug);
if (!$note) { http_response_code(404); die('Note not found.'); }

$me    = current_user();
$owner = user_owns_note($note);

// Private: owner only
if (!$note['is_public'] && !$owner) { http_response_code(403); die('Private note.'); }

// Password-protected
if ($note['password_hash'] && !$owner) {
    $pw = $_GET['pw'] ?? '';
    if (!password_verify($pw, $note['password_hash'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('403 — Password required. Append ?pw=YOUR_PASSWORD to the URL.');
    }
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $note['content'];
