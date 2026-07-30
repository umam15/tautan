<?php
/**
 * Fungsi-fungsi autentikasi & otorisasi
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lang.php';

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = db()->prepare('SELECT id, username, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        // user mungkin sudah dihapus tapi sesi masih ada
        session_destroy();
        return null;
    }
    $cached = $user;
    return $cached;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

/**
 * Prefix relatif ke root aplikasi ('' di root, '../' di dalam admin/ atau links/).
 * Dipakai supaya redirect() & link antar halaman tetap benar walau halaman
 * berada satu tingkat di dalam subfolder.
 */
function base_path(): string
{
    $dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    return in_array($dir, ['admin', 'links'], true) ? '../' : '';
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(base_path() . 'login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', t('access_denied'));
        redirect(base_path() . 'index.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return true;
    }
    return false;
}

function do_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * ==== CSRF ====
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die(t('csrf_expired'));
    }
}

/**
 * ==== Helper umum ====
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
