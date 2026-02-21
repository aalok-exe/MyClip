<?php
/**
 * pad/save.php — Auto-save endpoint for the live notepad.
 * Accepts regular form POST (application/x-www-form-urlencoded).
 * Returns JSON: { ok, saved_at } or { error }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: ' . APP_URL);

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

$content = $_POST['content'] ?? '';
$syntax  = $_POST['syntax']  ?? 'plain';
$syntax  = in_array($syntax, array_keys(SYNTAX_MODES)) ? $syntax : 'plain';

if (!validate_note_size($content)) {
    http_response_code(413);
    echo json_encode(['error' => 'too_large']);
    exit;
}

$pad = get_or_create_pad($me['id'], $me['username']);

db()->prepare(
    'UPDATE notes SET content = ?, syntax = ?, updated_at = NOW() WHERE id = ?'
)->execute([$content, $syntax, $pad['id']]);

echo json_encode([
    'ok'       => true,
    'saved_at' => date('H:i:s'),
]);
