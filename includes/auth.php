<?php
/**
 * Fungsi-fungsi autentikasi & otorisasi
 */

require_once __DIR__ . '/../config.php';

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
        set_flash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect(base_path() . 'index.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    // Rate limit berbasis session: murah, tidak butuh tabel/dependency baru.
    // 5 kegagalan dalam 60 detik mengunci percobaan selama 60 detik.
    $now = time();
    $window = 60;
    $maxFailures = 5;
    $lockout = 60;

    $state = $_SESSION['login_rate_limit'] ?? ['failures' => [], 'locked_until' => 0];
    $failures = array_values(array_filter(
        $state['failures'] ?? [],
        static fn ($timestamp) => is_int($timestamp) && $timestamp > $now - $window
    ));

    if (($state['locked_until'] ?? 0) > $now) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($_SESSION['login_rate_limit']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    $failures[] = $now;
    if (count($failures) >= $maxFailures) {
        $_SESSION['login_rate_limit'] = [
            'failures' => [],
            'locked_until' => $now + $lockout,
        ];
    } else {
        $_SESSION['login_rate_limit'] = [
            'failures' => $failures,
            'locked_until' => 0,
        ];
    }

    // Dummy hash menjaga timing lookup user yang tidak ada tetap lebih konsisten.
    if (!$user) {
        password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2q5jQ2fQfQq8JjQm2');
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
        die('Sesi Anda telah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
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
