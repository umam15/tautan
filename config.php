<?php
/**
 * Konfigurasi aplikasi & koneksi database (PDO SQLite)
 */

// ==== Pengaturan dasar ====
error_reporting(E_ALL);
ini_set('display_errors', '0'); // matikan di production, aktifkan saat debug jika perlu

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($https) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

define('APP_NAME', 'Tautan');
define('APP_VERSION', '0.2.15');
define('DB_DIR', __DIR__ . '/data');
define('DB_PATH', DB_DIR . '/bookmarks.sqlite');
// Cache lokal favicon (lihat favicon.php) — disimpan di dalam data/ supaya
// otomatis ikut terlindungi oleh data/.htaccess (Require all denied) dan
// tidak bisa diakses langsung lewat URL, harus lewat favicon.php.
define('FAVICON_DIR', DB_DIR . '/favicons');

define('IMPORT_MAX_FILE_SIZE', 5 * 1024 * 1024);
define('BACKUP_MAX_FILE_SIZE', 64 * 1024 * 1024);
define('MAX_TAGS', 20);
define('MAX_TAG_LENGTH', 40);
define('MAX_TITLE_LENGTH', 255);

// Pastikan folder data ada & bisa ditulis
if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
}
if (!is_dir(FAVICON_DIR)) {
    mkdir(FAVICON_DIR, 0775, true);
}

/**
 * Ambil koneksi PDO (singleton sederhana)
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }

    return $pdo;
}

