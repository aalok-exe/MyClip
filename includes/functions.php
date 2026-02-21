<?php
require_once __DIR__ . '/config.php';

// ══════════════════════════════════════════════════════════════
//  SESSION
// ══════════════════════════════════════════════════════════════

function boot_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function current_user(): ?array {
    boot_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        redirect(url('auth/login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_admin(): void {
    require_login();
    if ((current_user()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:40px">403 — Admins only.</p>');
    }
}

function is_admin(): bool {
    return (current_user()['role'] ?? '') === 'admin';
}

// ══════════════════════════════════════════════════════════════
//  URL HELPERS
// ══════════════════════════════════════════════════════════════

function url(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Build the public URL for a note.
 * Pretty format: domain.com/username/note-slug  (rewritten by .htaccess)
 * Short, clean, and verbally shareable.
 */
function note_url(array $note): string {
    $u = rawurlencode($note['username'] ?? '');
    $n = rawurlencode(!empty($note['note_slug']) ? $note['note_slug'] : $note['slug']);
    return url($u . '/' . $n);
}

function profile_url(string $username): string {
    return url(rawurlencode($username));
}

function raw_url(string $slug): string {
    return url('raw/index.php?slug=' . rawurlencode($slug));
}

function redirect(string $target): never {
    header('Location: ' . $target);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  OUTPUT / FLASH
// ══════════════════════════════════════════════════════════════

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function flash_set(string $type, string $msg): void {
    boot_session();
    $_SESSION['flash'] = compact('type', 'msg');
}

function flash_get(): ?array {
    boot_session();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ══════════════════════════════════════════════════════════════
//  SLUG HELPERS
// ══════════════════════════════════════════════════════════════

function safe_slug(): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len   = strlen($chars) - 1;
    do {
        $slug = '';
        for ($i = 0; $i < SLUG_LENGTH; $i++) {
            $slug .= $chars[random_int(0, $len)];
        }
        $st = db()->prepare('SELECT id FROM notes WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
    } while ($st->fetch());
    return $slug;
}

function title_to_slug(string $title): string {
    $s = mb_strtolower(trim($title), 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-') ?: 'untitled';
}

function unique_note_slug(int $user_id, string $base, ?int $exclude_id = null): string {
    $base = substr($base, 0, 200);
    $slug = $base;
    $i    = 1;
    while (true) {
        $sql = 'SELECT id FROM notes WHERE user_id = ? AND note_slug = ?';
        if ($exclude_id !== null) {
            $sql .= ' AND id != ' . (int)$exclude_id;
        }
        $sql .= ' LIMIT 1';
        $st = db()->prepare($sql);
        $st->execute([$user_id, $slug]);
        if (!$st->fetch()) break;
        $slug = substr($base, 0, 195) . '-' . (++$i);
    }
    return $slug;
}

// ══════════════════════════════════════════════════════════════
//  PAD — per-user live notepad
// ══════════════════════════════════════════════════════════════

/**
 * Return the user's pad note, creating it if it doesn't exist yet.
 * The pad is a regular note with note_slug = '__pad__'.
 * It is always public and never password-protected.
 */
function get_or_create_pad(int $user_id, string $username): array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
         WHERE n.user_id = ? AND n.note_slug = ? LIMIT 1'
    );
    $st->execute([$user_id, '__pad__']);
    $pad = $st->fetch();

    if (!$pad) {
        $internal_slug = safe_slug();
        db()->prepare(
            'INSERT INTO notes (user_id, slug, note_slug, title, content, syntax, is_public)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        )->execute([$user_id, $internal_slug, '__pad__', null, '', 'plain']);

        $st->execute([$user_id, '__pad__']);
        $pad = $st->fetch();
    }

    return $pad;
}

// ══════════════════════════════════════════════════════════════
//  NOTE CRUD
// ══════════════════════════════════════════════════════════════

function create_note(int $user_id, array $data): array {
    $internal_slug = safe_slug();
    $syntax        = in_array($data['syntax'] ?? 'plain', array_keys(SYNTAX_MODES)) ? $data['syntax'] : 'plain';
    $pw            = !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;
    $tags          = sanitize_tags($data['tags'] ?? '');
    $is_public     = empty($data['is_public']) ? 0 : 1;
    $title         = trim(substr((string)($data['title'] ?? ''), 0, 255)) ?: null;

    $base_note_slug = title_to_slug($title ?? $internal_slug);
    $note_slug      = unique_note_slug($user_id, $base_note_slug);

    db()->prepare(
        'INSERT INTO notes (user_id, slug, note_slug, title, content, syntax, is_public, password_hash, tags)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$user_id, $internal_slug, $note_slug, $title, $data['content'], $syntax, $is_public, $pw, $tags]);

    $note = get_note($internal_slug);
    if (!$note) throw new \RuntimeException('Note insert succeeded but fetch failed.');
    return $note;
}

function get_note(string $slug): ?array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id WHERE n.slug = ? LIMIT 1'
    );
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function get_note_by_id(int $id): ?array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id WHERE n.id = ? LIMIT 1'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function get_note_by_user_slug(string $username, string $note_slug): ?array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
         WHERE u.username = ? AND n.note_slug = ? LIMIT 1'
    );
    $st->execute([$username, $note_slug]);
    return $st->fetch() ?: null;
}

/**
 * Update note content + metadata.
 *
 * password field rules:
 *   false        → do NOT touch password column
 *   '' (empty)   → clear password (set NULL)
 *   'somestring' → hash and store
 */
function update_note(int $note_id, string $new_content, string $old_content, array $fields = []): void {
    save_revision($note_id, $old_content);

    $set    = ['content = ?', 'updated_at = NOW()'];
    $params = [$new_content];

    if (array_key_exists('title', $fields)) {
        $new_title = trim(substr((string)$fields['title'], 0, 255));
        $set[]     = 'title = ?';
        $params[]  = $new_title ?: null;

        $row = db()->prepare('SELECT user_id FROM notes WHERE id = ? LIMIT 1');
        $row->execute([$note_id]);
        $r = $row->fetch();
        if ($r) {
            $base     = title_to_slug($new_title ?: 'untitled');
            $newSlug  = unique_note_slug((int)$r['user_id'], $base, $note_id);
            $set[]    = 'note_slug = ?';
            $params[] = $newSlug;
        }
    }

    if (array_key_exists('syntax', $fields)) {
        $set[]    = 'syntax = ?';
        $params[] = in_array($fields['syntax'], array_keys(SYNTAX_MODES)) ? $fields['syntax'] : 'plain';
    }

    if (array_key_exists('tags', $fields)) {
        $set[]    = 'tags = ?';
        $params[] = sanitize_tags((string)$fields['tags']);
    }

    if (array_key_exists('is_public', $fields)) {
        $set[]    = 'is_public = ?';
        $params[] = empty($fields['is_public']) ? 0 : 1;
    }

    // Only touch password if the caller explicitly passes the key
    if (array_key_exists('password', $fields) && $fields['password'] !== false) {
        $set[]    = 'password_hash = ?';
        $params[] = ($fields['password'] !== '')
                    ? password_hash($fields['password'], PASSWORD_BCRYPT)
                    : null;
    }

    $params[] = $note_id;
    db()->prepare('UPDATE notes SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
}

function delete_note(int $id): void {
    db()->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
}

function increment_views(int $note_id): void {
    boot_session();
    $key = 'viewed_' . $note_id;
    if (!isset($_SESSION[$key])) {
        db()->prepare('UPDATE notes SET views = views + 1 WHERE id = ?')->execute([$note_id]);
        $_SESSION[$key] = true;
    }
}

function user_owns_note(array $note): bool {
    $u = current_user();
    if (!$u) return false;
    return ((int)$note['user_id'] === (int)$u['id']) || ($u['role'] === 'admin');
}

// ══════════════════════════════════════════════════════════════
//  REVISIONS
// ══════════════════════════════════════════════════════════════

function save_revision(int $note_id, string $content): void {
    db()->prepare('INSERT INTO revisions (note_id, content) VALUES (?, ?)')->execute([$note_id, $content]);

    // Prune oldest revisions beyond MAX_REVISIONS
    $st = db()->prepare(
        'SELECT id FROM revisions WHERE note_id = ? ORDER BY created_at DESC LIMIT 999 OFFSET ' . (int)MAX_REVISIONS
    );
    $st->execute([$note_id]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN, 0);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("DELETE FROM revisions WHERE id IN ($ph)")->execute($ids);
    }
}

function get_revisions(int $note_id): array {
    $st = db()->prepare('SELECT id, created_at FROM revisions WHERE note_id = ? ORDER BY created_at DESC');
    $st->execute([$note_id]);
    return $st->fetchAll();
}

function get_revision(int $rev_id, int $note_id): ?string {
    $st = db()->prepare('SELECT content FROM revisions WHERE id = ? AND note_id = ? LIMIT 1');
    $st->execute([$rev_id, $note_id]);
    $r = $st->fetch();
    return $r ? $r['content'] : null;
}

// ══════════════════════════════════════════════════════════════
//  USERS
// ══════════════════════════════════════════════════════════════

function get_user_by_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function get_user_by_username(string $username): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    return $st->fetch() ?: null;
}

/** All notes (public + private) for owner view. JOINs username. */
function get_user_notes(int $user_id, int $limit = 200, int $offset = 0): array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
         WHERE n.user_id = ? ORDER BY n.updated_at DESC LIMIT ? OFFSET ?'
    );
    $st->execute([$user_id, $limit, $offset]);
    return $st->fetchAll();
}

/** Public notes only for visitor view. JOINs username. */
function get_user_public_notes(int $user_id): array {
    $st = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
         WHERE n.user_id = ? AND n.is_public = 1 ORDER BY n.updated_at DESC'
    );
    $st->execute([$user_id]);
    return $st->fetchAll();
}

function count_user_notes(int $user_id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM notes WHERE user_id = ?');
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

// ══════════════════════════════════════════════════════════════
//  TAGS / MISC
// ══════════════════════════════════════════════════════════════

function sanitize_tags(string $raw): string {
    $tags = array_map('trim', explode(',', $raw));
    $tags = array_filter($tags, fn($t) => strlen($t) > 0 && strlen($t) <= 30);
    return implode(',', array_slice(array_unique($tags), 0, 10));
}

function tags_array(?string $s): array {
    if (empty($s)) return [];
    return array_values(array_filter(array_map('trim', explode(',', $s))));
}

function validate_note_size(string $c): bool {
    return strlen($c) <= MAX_NOTE_SIZE;
}

function time_ago(string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false || $ts === 0) return '—';
    $diff = time() - $ts;
    if ($diff < 0)      return date('M j, Y', $ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return round($diff / 60) . 'm ago';
    if ($diff < 86400)  return round($diff / 3600) . 'h ago';
    if ($diff < 604800) return round($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}
