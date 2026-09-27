<?php
declare(strict_types=1);

/**
 * CLI database migration.
 * Run during deployment:
 *   php scripts/migrate.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/migrations.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    migrate_database(db());
    fwrite(STDOUT, "Database schema migration selesai.\n");
} catch (Throwable $e) {
    error_log('Database migration failed: ' . $e->getMessage());
    fwrite(STDERR, "Migration gagal. Periksa server log untuk detail.\n");
    exit(1);
}
