<?php
/**
 * Export daftar link (JSON/CSV/HTML) — terpisah dari backup database penuh
 * (admin/backup.php, yang isinya .sqlite mentah termasuk user & hash
 * password). Endpoint ini cuma mengirim link yang terlihat oleh user yang
 * sedang login, dalam format portabel untuk dibuka di tool lain (spreadsheet,
 * skrip, dsb) — bukan untuk restore ke aplikasi ini.
 *
 * Format HTML memakai Netscape Bookmark File Format, sama seperti yang
 * dipakai browser (Chrome, Firefox, Edge, Safari, dsb) saat "Export
 * Bookmarks" — bisa diimpor balik ke Tautan lewat links/import.php, atau
 * langsung ke Bookmark Manager browser mana pun.
 *
 * Filter pencarian (?q=) & tag (?tag=) yang sedang aktif di beranda ikut
 * dipakai kalau dikirim lewat query string, jadi tombol ekspor di index.php
 * bisa mengekspor "hasil yang sedang ditampilkan" saja, bukan selalu semua
 * link.
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

$allowedFormats = ['json', 'csv', 'html'];
$format = in_array($_GET['format'] ?? '', $allowedFormats, true) ? $_GET['format'] : 'json';
$q = trim($_GET['q'] ?? '');
$tag = trim($_GET['tag'] ?? '');

$links = get_links($q, $tag);

stream_links_export($format, $links);
