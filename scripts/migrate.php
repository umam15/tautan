<?php
/**
 * CLI migration for Tautan.
 *
 * Jalankan saat deployment:
 *   php scripts/migrate.php
 */
require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS app_schema (version INTEGER NOT NULL)");

$row = $pdo->query('SELECT version FROM app_schema LIMIT 1')->fetchColumn();
$version = $row === false ? 0 : (int) $row;

if ($version >= 3) {
    fwrite(STDOUT, "Database schema sudah versi 3.\n");
    exit(0);
}

$pdo->beginTransaction();
try {
    // Initial schema + compatibility migrations.
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
            tags        TEXT NOT NULL DEFAULT '',
            click_count INTEGER NOT NULL DEFAULT 0,
            last_clicked_at TEXT,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            user_id     INTEGER,
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Migrasi untuk instalasi lama: kolom `tags` baru ditambahkan di v0.2.9,
    // jadi database yang sudah ada (dibuat sebelum kolom ini ada) tidak akan
    // otomatis mendapatkannya lewat CREATE TABLE IF NOT EXISTS di atas —
    // perlu ALTER TABLE manual sekali saja kalau kolomnya belum ada.
    $existingColumns = array_column($pdo->query('PRAGMA table_info(links)')->fetchAll(), 'name');
    if (!in_array('tags', $existingColumns, true)) {
        $pdo->exec("ALTER TABLE links ADD COLUMN tags TEXT NOT NULL DEFAULT ''");
    }
    // Migrasi untuk instalasi lama: kolom `click_count` & `last_clicked_at` baru
    // ditambahkan di v0.2.15 untuk fitur Statistik Klik (opsional, nonaktif
    // secara default — lihat click_tracking_enabled() di includes/functions.php).
    if (!in_array('click_count', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN click_count INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('last_clicked_at', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN last_clicked_at TEXT');
    }

    // Catatan: tidak ada lagi akun admin default (admin/admin123) yang di-seed
    // otomatis di sini. Saat tabel users masih kosong (instalasi baru), aplikasi
    // akan mengarahkan pengguna ke setup.php untuk membuat akun admin pertama
    // dengan username & password pilihan sendiri. Lihat has_any_user() di
    // includes/functions.php.

    // Index untuk mempercepat query yang paling sering dijalankan:
    // - daftar link diurutkan lewat sort_order (index.php)
    // - daftar link tamu difilter lewat visibility (get_links())
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_sort_order ON links(sort_order)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_visibility ON links(visibility)');
    // Tidak ada index khusus untuk `tags`: nilainya comma-separated dan dicari
    // lewat LIKE (substring), bukan perbandingan kesetaraan, jadi index B-tree
    // biasa tidak banyak membantu di sini. Skala data link per user diasumsikan
    // kecil (puluhan-ratusan), jadi full scan tetap cepat.

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            `key`   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )
    ");
    $pdo->exec('DELETE FROM app_schema');
    $stmt = $pdo->prepare('INSERT INTO app_schema (version) VALUES (:version)');
    $stmt->execute([':version' => 3]);
    $pdo->commit();

    fwrite(STDOUT, "Database schema berhasil dimigrasikan ke versi 3.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Migration gagal: " . $e->getMessage() . "\n");
    exit(1);
}
