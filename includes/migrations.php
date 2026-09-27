<?php
declare(strict_types=1);

/**
 * Explicit database migration flow.
 * Called only by CLI deployment or first-run setup, never by normal requests.
 */
function migrate_database(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_schema (version INTEGER NOT NULL)");
    $row = $pdo->query('SELECT version FROM app_schema LIMIT 1')->fetchColumn();
    $version = $row === false ? 0 : (int) $row;

    if ($version >= 3) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin','user')),
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT DEFAULT '',
            icon TEXT DEFAULT '',
            visibility TEXT NOT NULL DEFAULT 'public' CHECK(visibility IN ('public','private')),
            tags TEXT NOT NULL DEFAULT '',
            click_count INTEGER NOT NULL DEFAULT 0,
            last_clicked_at TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0,
            user_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        $existingColumns = array_column($pdo->query('PRAGMA table_info(links)')->fetchAll(), 'name');
        if (!in_array('tags', $existingColumns, true)) {
            $pdo->exec("ALTER TABLE links ADD COLUMN tags TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('click_count', $existingColumns, true)) {
            $pdo->exec('ALTER TABLE links ADD COLUMN click_count INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('last_clicked_at', $existingColumns, true)) {
            $pdo->exec('ALTER TABLE links ADD COLUMN last_clicked_at TEXT');
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_sort_order ON links(sort_order)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_visibility ON links(visibility)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )");

        $pdo->exec('DELETE FROM app_schema');
        $stmt = $pdo->prepare('INSERT INTO app_schema (version) VALUES (:version)');
        $stmt->execute([':version' => 3]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
