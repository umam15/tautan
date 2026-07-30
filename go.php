<?php
/**
 * Endpoint redirect untuk tautan (dipakai hanya saat Statistik Klik aktif).
 * Mencatat satu klik agregat (lihat record_click() di includes/functions.php,
 * tidak ada IP/user-agent/referrer yang disimpan) lalu redirect 302 ke URL
 * asli. Kalau fitur statistik nonaktif, index.php tidak pernah membuat link
 * ke file ini sama sekali — tautan dibuka langsung tanpa lewat sini.
 */
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$link = $id > 0 ? get_link($id) : null;

if (!$link) {
    http_response_code(404);
    exit(t('generic_not_found'));
}

// Tautan privat tetap hanya boleh "dibuka" lewat sini oleh yang sudah login,
// sama seperti aturan tampil di beranda (get_links()).
if ($link['visibility'] === 'private' && !is_logged_in()) {
    http_response_code(404);
    exit(t('generic_not_found'));
}

if (click_tracking_enabled()) {
    record_click($id);
}

header('Location: ' . $link['url']);
exit;
