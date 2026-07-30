<?php
/**
 * ==== Sistem multi-bahasa (i18n): Indonesia (default) / English ====
 *
 * Bahasa aktif ditentukan lewat cookie `tautan_lang` (bukan session, supaya
 * tetap berlaku untuk tamu yang belum/tidak login, dan bertahan antar
 * kunjungan). Cookie diset lewat lang.php di root, dipicu tombol "ID"/"EN"
 * di topbar (lihat includes/header.php). Kalau belum pernah memilih,
 * default ke 'id'.
 *
 * String UI disimpan sebagai array asosiatif per bahasa di file terpisah
 * (includes/lang/id.php & includes/lang/en.php) — bukan di database — jadi
 * gampang ditinjau/ditambah lewat editor teks biasa, dan menambah bahasa
 * baru cukup dengan menambah satu file lagi + satu baris di
 * SUPPORTED_LANGUAGES di bawah.
 */

define('SUPPORTED_LANGUAGES', [
    'id' => 'Indonesia',
    'en' => 'English',
]);

const DEFAULT_LANGUAGE = 'id';

/**
 * Bahasa yang sedang aktif untuk request ini.
 */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $cookieLang = (string) ($_COOKIE['tautan_lang'] ?? '');
    $lang = array_key_exists($cookieLang, SUPPORTED_LANGUAGES) ? $cookieLang : DEFAULT_LANGUAGE;
    return $lang;
}

/**
 * Muat & cache (per request) array string untuk satu bahasa.
 */
function load_lang_strings(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $path = __DIR__ . '/lang/' . basename($lang) . '.php';
    $cache[$lang] = is_file($path) ? (require $path) : [];
    return $cache[$lang];
}

/**
 * Ambil string UI sesuai bahasa aktif.
 *
 * Fallback berlapis kalau sebuah key belum ada di bahasa aktif: pakai versi
 * Bahasa Indonesia (bahasa default aplikasi, selalu lengkap), lalu kalau
 * masih tidak ada juga, tampilkan key itu sendiri — supaya tidak pernah ada
 * halaman yang tampil kosong hanya karena satu string lupa diterjemahkan.
 *
 * $params dipakai untuk mengganti placeholder ':nama' di dalam string lewat
 * strtr(), mis. t('setup_success', [':app' => APP_NAME]) untuk string
 * "Akun admin berhasil dibuat. Selamat datang di :app!".
 *
 * Beberapa string sengaja mengandung tag HTML statis (mis. <strong>, <code>)
 * untuk penekanan — nilainya TIDAK di-escape otomatis di sini, jadi dipanggil
 * langsung lewat <?= t(...) ?> (bukan e(t(...))) di template. Ini aman karena
 * isinya teks tetap dari file bahasa, bukan input pengguna.
 */
function t(string $key, array $params = []): string
{
    $lang = current_lang();
    $strings = load_lang_strings($lang);
    $value = $strings[$key] ?? (load_lang_strings(DEFAULT_LANGUAGE)[$key] ?? $key);

    if (!empty($params)) {
        $value = strtr($value, $params);
    }
    return $value;
}
