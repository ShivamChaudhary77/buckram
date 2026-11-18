<?php
/**
 * Dashboard security: one password (hashed, stored outside git), sessions,
 * CSRF tokens, login rate limit and the one-time setup link.
 *
 * Files (inside the content folder, never in git):
 *   .auth.json                      {"hash": "...", "changed": "..."}  (chmod 600)
 *   _security/login-attempts.json   failed logins per IP (hashed)
 *   _security/setup-token.json      one-time setup link (hashed token + expiry)
 */

const PASSWORD_MIN_LENGTH = 10;
const LOGIN_MAX_FAILURES = 5;
const LOGIN_LOCK_SECONDS = 900;      // 15 minutes
const SESSION_IDLE_SECONDS = 7200;   // 2 hours
const SETUP_TOKEN_SECONDS = 86400;   // 24 hours

/* ------------------------------------------------------------ password */

function auth_file(): string
{
    return content_path('.auth.json');
}

function password_is_set(): bool
{
    $data = json_read(auth_file(), []);
    return is_string($data['hash'] ?? null) && $data['hash'] !== '';
}

/** Returns a problem in plain words, or null when the password is acceptable. */
function password_problem(string $password, string $confirm): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'The password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return null;
}

function password_set(string $password): void
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    json_write(auth_file(), ['hash' => password_hash($password, $algo), 'changed' => now_iso()]);
    @chmod(auth_file(), 0600);
    @unlink(content_path('_security', 'setup-token.json'));
}

function password_check(string $password): bool
{
    $data = json_read(auth_file(), []);
    $hash = (string) ($data['hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    if (password_needs_rehash($hash, $algo)) {
        password_set($password);
    }
    return true;
}

/* ------------------------------------------------------ one-time setup */

/** Create a setup token (returned once, stored hashed). */
function setup_token_create(): string
{
    $token = bin2hex(random_bytes(24));
    json_write(content_path('_security', 'setup-token.json'), [
        'hash'    => hash('sha256', $token),
        'expires' => time() + SETUP_TOKEN_SECONDS,
    ]);
    @chmod(content_path('_security', 'setup-token.json'), 0600);
    return $token;
}

function setup_token_valid(string $token): bool
{
    if ($token === '' || password_is_set()) {
        return false;
    }
    $data = json_read(content_path('_security', 'setup-token.json'), []);
    return isset($data['hash'], $data['expires'])
        && $data['expires'] >= time()
        && hash_equals((string) $data['hash'], hash('sha256', $token));
}

/* ------------------------------------------------------------- sessions */

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        $_SESSION = $_SESSION ?? []; // tests and CLI: an in-memory session is enough
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('jb_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $dir = content_path('_security', 'sessions');
    ensure_dir($dir);
    session_save_path($dir);
    session_start();
}

function admin_logged_in(): bool
{
    admin_session_start();
    if (empty($_SESSION['admin'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['seen'] ?? 0) > SESSION_IDLE_SECONDS) {
        admin_logout();
        return false;
    }
    $_SESSION['seen'] = time();
    return true;
}

function admin_login(): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/* ----------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    admin_session_start();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------------------------------------------------------- rate limit */

function login_attempts_file(): string
{
    return content_path('_security', 'login-attempts.json');
}

function client_ip_key(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
}

/** Seconds until this visitor may try again (0 = allowed). */
function login_locked_for(): int
{
    $all = json_read(login_attempts_file(), []);
    $me = $all[client_ip_key()] ?? null;
    if (!$me || empty($me['lockedUntil'])) {
        return 0;
    }
    return max(0, (int) $me['lockedUntil'] - time());
}

function login_record_failure(): void
{
    $all = json_read(login_attempts_file(), []);
    $now = time();
    // Forget old entries so the file stays small.
    foreach ($all as $k => $v) {
        if (($v['last'] ?? 0) < $now - 86400) {
            unset($all[$k]);
        }
    }
    $key = client_ip_key();
    $me = $all[$key] ?? ['count' => 0];
    if (!empty($me['lockedUntil']) && $me['lockedUntil'] <= $now) {
        $me = ['count' => 0];
    }
    $me['count'] = (int) $me['count'] + 1;
    $me['last'] = $now;
    if ($me['count'] >= LOGIN_MAX_FAILURES) {
        $me['lockedUntil'] = $now + LOGIN_LOCK_SECONDS;
    }
    $all[$key] = $me;
    json_write(login_attempts_file(), $all);
}

function login_clear_failures(): void
{
    $all = json_read(login_attempts_file(), []);
    unset($all[client_ip_key()]);
    json_write(login_attempts_file(), $all);
}
