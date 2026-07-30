<?php
/**
 * Endpoint untuk mengganti bahasa UI (dipicu tombol "ID"/"EN" di topbar,
 * lihat includes/header.php). Cuma set cookie `tautan_lang` lalu redirect
 * balik ke halaman asal.
 *
 * Sengaja hanya require config.php + includes/lang.php (bukan
 * includes/functions.php), supaya tidak ikut kena guard "belum ada user ->
 * redirect ke setup.php" — ganti bahasa harus tetap bisa dipakai dari
 * halaman Setup maupun Login sebelum ada user sama sekali.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/lang.php';

$lang = (string) ($_GET['lang'] ?? '');
if (array_key_exists($lang, SUPPORTED_LANGUAGES)) {
    setcookie('tautan_lang', $lang, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
}

// Redirect balik ke halaman asal. Validasi ketat: hanya izinkan path lokal
// yang diawali satu garis miring (bukan skema penuh atau "//" yang bisa
// dipakai untuk redirect ke domain lain / open redirect).
$redirect = (string) ($_GET['redirect'] ?? '');
$isLocalPath = $redirect !== ''
    && $redirect[0] === '/'
    && !str_starts_with($redirect, '//')
    && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $redirect);

header('Location: ' . ($isLocalPath ? $redirect : '/'));
exit;
