<?php
/**
 * Favicon lokal/offline-only.
 *
 * Tidak pernah melakukan request keluar. Jika sebuah link tidak memiliki
 * favicon/icon yang disimpan sendiri, selalu gunakan ikon default lokal.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');

readfile(__DIR__ . '/assets/default-favicon.svg');
