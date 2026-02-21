<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
require_login();

$slug   = trim($_POST['slug']   ?? '');
$rev_id = (int)($_POST['rev_id'] ?? 0);

$note = $slug ? get_note($slug) : null;

if (!$note || !user_owns_note($note)) {
    flash_set('error', 'Access denied.');
    redirect(url('auth/login.php'));
}

$content = $rev_id ? get_revision($rev_id, $note['id']) : null;
if ($content === null) {
    flash_set('error', 'Revision not found.');
    redirect(note_url($note));   // note is an array — correct
}

update_note($note['id'], $content, $note['content']);

// Re-fetch so note_url gets updated note_slug if title changed
$note = get_note($note['slug']);
flash_set('success', 'Revision restored.');
redirect(note_url($note));
