<?php
/**
 * Konfigurasi aplikasi & koneksi database (PDO SQLite)
 */

// ==== Pengaturan dasar ====
error_reporting(E_ALL);
ini_set('display_errors', '0'); // matikan di production, aktifkan saat debug jika perlu

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'Tautan');
define('DB_DIR', __DIR__ . '/data');
define('DB_PATH', DB_DIR . '/bookmarks.sqlite');

// Pastikan folder data ada & bisa ditulis
if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
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
        init_schema($pdo);
    }

    return $pdo;
}

/**
 * Buat tabel jika belum ada + seed admin default
 */
function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role          TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin','user')),
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            url         TEXT NOT NULL,
            description TEXT DEFAULT '',
            icon        TEXT DEFAULT '',
            visibility  TEXT NOT NULL DEFAULT 'public' CHECK(visibility IN ('public','private')),
            sort_order  INTEGER NOT NULL DEFAULT 0,
            user_id     INTEGER,
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Catatan: tidak ada lagi akun admin default (admin/admin123) yang di-seed
    // otomatis di sini. Saat tabel users masih kosong (instalasi baru), aplikasi
    // akan mengarahkan pengguna ke setup.php untuk membuat akun admin pertama
    // dengan username & password pilihan sendiri. Lihat has_any_user() di
    // includes/functions.php.
}
