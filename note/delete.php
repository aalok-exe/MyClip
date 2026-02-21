<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
require_login();

$slug = trim($_GET['slug'] ?? '');
$note = $slug ? get_note($slug) : null;

if (!$note || !user_owns_note($note)) {
    flash_set('error', 'Note not found or access denied.');
    redirect(profile_url(current_user()['username']));
}

$owner_username = $note['username'];
delete_note($note['id']);
flash_set('success', 'Note deleted.');
redirect(profile_url($owner_username));
