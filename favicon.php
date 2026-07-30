<?php
/**
 * Endpoint favicon dengan cache lokal (data/favicons/).
 *
 * Dipanggil lewat favicon_for() di includes/functions.php sebagai <img src="...">
 * di index.php. Tujuannya: jangan selalu bergantung ke layanan favicon Google
 * pada setiap render halaman.
 *
 * Alur:
 * 1. Kalau favicon untuk host ini sudah pernah diunduh & masih "segar"
 *    (belum lewat TTL), langsung dilayani dari file lokal di data/favicons/.
 * 2. Kalau belum ada / sudah basi, unduh sekali dari Google, simpan ke disk
 *    (rename atomik supaya request lain yang baca file lama tidak korup),
 *    lalu layani.
 * 3. Kalau unduhan gagal (domain tidak valid, Google timeout, dll), simpan
 *    penanda "negative cache" (*.miss) supaya kegagalan yang sama tidak
 *    diulang-ulang di setiap request — cukup layani ikon default lokal.
 *
 * File ini sengaja hanya require config.php (bukan includes/functions.php),
 * supaya tidak ikut kena guard "belum ada user -> redirect ke setup.php"
 * dan tidak perlu buka koneksi database sama sekali untuk melayani gambar.
 */

require_once __DIR__ . '/config.php';

const FAVICON_TTL_SECONDS = 30 * 24 * 60 * 60; // 30 hari cache favicon yang berhasil diunduh
const FAVICON_MISS_TTL_SECONDS = 24 * 60 * 60; // 1 hari sebelum mencoba ulang domain yang gagal

$host = strtolower(trim($_GET['host'] ?? ''));

// Validasi ketat: hanya izinkan karakter yang wajar untuk hostname, supaya
// parameter ini tidak bisa disalahgunakan untuk path traversal atau memaksa
// server melakukan request ke tujuan aneh-aneh (mis. IP internal).
$hostValid = $host !== ''
    && strlen($host) <= 253
    && preg_match('/^[a-z0-9]([a-z0-9-]{0,62}\.)+[a-z]{2,}$/', $host) === 1;

if (!$hostValid) {
    serve_default_icon();
    exit;
}

$cacheKey  = sha1($host);
$cachePath = FAVICON_DIR . '/' . $cacheKey;
$metaPath  = $cachePath . '.type'; // isi: Content-Type hasil unduhan
$missPath  = $cachePath . '.miss'; // penanda negative-cache

// 1) Sudah pernah gagal & belum lewat TTL negative-cache -> jangan coba lagi, langsung ikon default
if (is_file($missPath) && (time() - filemtime($missPath)) < FAVICON_MISS_TTL_SECONDS) {
    serve_default_icon();
    exit;
}

// 2) Sudah ada di cache & masih segar -> layani langsung dari disk
if (is_file($cachePath) && is_file($metaPath) && (time() - filemtime($cachePath)) < FAVICON_TTL_SECONDS) {
    serve_cached_file($cachePath, (string) file_get_contents($metaPath));
    exit;
}

// 3) Belum ada cache (atau sudah basi) -> unduh sekali dari Google, simpan ke disk lokal
$downloaded = download_favicon($host);

if ($downloaded === null) {
    @touch($missPath);
    serve_default_icon();
    exit;
}

[$bytes, $contentType] = $downloaded;

// Tulis ke file sementara dulu lalu rename (atomik), supaya request lain yang
// kebetulan sedang membaca $cachePath tidak pernah melihat file setengah-tulis.
$tmpPath = $cachePath . '.tmp-' . bin2hex(random_bytes(6));
if (file_put_contents($tmpPath, $bytes) === false || !rename($tmpPath, $cachePath)) {
    @unlink($tmpPath);
    serve_default_icon();
    exit;
}
file_put_contents($metaPath, $contentType);
@unlink($missPath);

serve_cached_file($cachePath, $contentType);
exit;

/**
 * Unduh favicon untuk sebuah host dari layanan favicon Google.
 * Return [bytes, content-type] kalau berhasil, atau null kalau gagal.
 */
function download_favicon(string $host): ?array
{
    $url = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);

    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => 4,
            'ignore_errors' => true,
            'header'        => "User-Agent: Tautan-Favicon-Cache/1.0\r\n",
        ],
    ]);

    $bytes = @file_get_contents($url, false, $context);
    if ($bytes === false || $bytes === '') {
        return null;
    }

    // ignore_errors=true bikin file_get_contents tetap mengembalikan body
    // walau status HTTP-nya error (mis. 404/500) — cek status line-nya sendiri
    // supaya halaman error tidak ikut ke-cache seolah-olah itu favicon asli.
    $statusLine = $http_response_header[0] ?? '';
    if (!preg_match('#^HTTP/\S+\s+2\d\d#', $statusLine)) {
        return null;
    }

    $contentType = 'image/png';
    foreach ($http_response_header ?? [] as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $contentType = trim(substr($header, strlen('Content-Type:')));
            break;
        }
    }

    return [$bytes, $contentType];
}

function serve_cached_file(string $path, string $contentType): void
{
    header('Content-Type: ' . ($contentType !== '' ? $contentType : 'image/png'));
    header('Cache-Control: public, max-age=604800, immutable'); // 7 hari di sisi browser
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
}

function serve_default_icon(): void
{
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    readfile(__DIR__ . '/assets/default-favicon.svg');
}
