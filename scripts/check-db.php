<?php
declare(strict_types=1);

/**
 * Read-only SQLite health check.
 *
 * Run:
 *   php scripts/check-db.php
 *
 * Exit codes:
 *   0 = healthy
 *   1 = check failed
 */
require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const EXPECTED_SCHEMA_VERSION = 3;

try {
    $pdo = db();

    $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') {
        throw new RuntimeException('SQLite integrity_check gagal: ' . $integrity);
    }

    $version = (int) $pdo->query('SELECT version FROM app_schema LIMIT 1')->fetchColumn();
    if ($version !== EXPECTED_SCHEMA_VERSION) {
        throw new RuntimeException(
            'Schema version tidak sesuai: ditemukan ' . $version .
            ', diharapkan ' . EXPECTED_SCHEMA_VERSION . '.'
        );
    }

    $requiredTables = ['users', 'links', 'app_settings', 'app_schema'];
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
    $tables = array_column($stmt->fetchAll(), 'name');

    foreach ($requiredTables as $table) {
        if (!in_array($table, $tables, true)) {
            throw new RuntimeException('Tabel wajib tidak ditemukan: ' . $table);
        }
    }

    fwrite(STDOUT, "Database sehat (SQLite integrity + schema v{$version}).\n");
    exit(0);
} catch (Throwable $e) {
    error_log('Database health check failed: ' . $e->getMessage());
    fwrite(STDERR, "Database check gagal: " . $e->getMessage() . "\n");
    exit(1);
}
