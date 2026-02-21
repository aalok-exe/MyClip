<?php
// ============================================================
//  PasteNest — Configuration
// ============================================================

define('DB_HOST',    'sql101.infinityfree.com');
define('DB_NAME',    'if0_41210041_pastenest_db');
define('DB_USER',    'if0_41210041');        // ← change
define('DB_PASS',    'aalok161');            // ← change
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'MyClip');
define('APP_URL',     'https://pastenest.kesug.com'); // ← change to your domain (no trailing slash)
define('APP_VERSION', '2.0.0');

define('SLUG_LENGTH',    8);
define('MAX_NOTE_SIZE',  1_000_000); // 1 MB
define('MAX_REVISIONS',  20);
define('SESSION_NAME',   'pastenest_sess');

define('SYNTAX_MODES', [
    'plain'      => 'Plain Text',
    'markdown'   => 'Markdown',
    'php'        => 'PHP',
    'javascript' => 'JavaScript',
    'python'     => 'Python',
    'html'       => 'HTML',
    'css'        => 'CSS',
    'sql'        => 'SQL',
    'bash'       => 'Bash / Shell',
    'json'       => 'JSON',
    'yaml'       => 'YAML',
    'cpp'        => 'C / C++',
    'java'       => 'Java',
    'ruby'       => 'Ruby',
    'go'         => 'Go',
    'rust'       => 'Rust',
    'xml'        => 'XML',
    'diff'       => 'Diff',
]);

// ── PDO singleton ────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2 style="font-family:sans-serif">Database connection failed.</h2><p>Check your <code>includes/config.php</code> settings.</p>');
        }
    }
    return $pdo;
}
