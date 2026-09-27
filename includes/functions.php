<?php
/**
 * Fungsi-fungsi terkait data links
 */

require_once __DIR__ . '/auth.php';

/**
 * Ambil daftar links sesuai status login.
 * - Tamu (belum login): hanya link dengan visibility = 'public'
 * - Sudah login: semua link (public + private)
 */
/**
 * Ambil daftar links sesuai status login, dengan pencarian opsional.
 * - Tamu (belum login): hanya link dengan visibility = 'public'
 * - Sudah login: semua link (public + private)
 * - $query: kalau diisi, filter title/description/url (case-insensitive,
 *   substring match) langsung lewat SQL — bukan cuma filter JS di client —
 *   supaya hasil pencarian tetap benar walau JS mati, dan URL
 *   `index.php?q=...` bisa langsung di-bookmark/di-share dan menampilkan
 *   hasil yang sudah terfilter dari server tanpa perlu JavaScript.
 */
function get_links(string $query = '', string $tag = ''): array
{
    $query = trim($query);
    $tag = trim($tag);

    // Query tunggal untuk kedua kasus (tamu vs login): filter visibility hanya
    // diterapkan lewat kondisi ":logged_in OR visibility = 'public'" di SQL,
    // jadi tidak ada lagi dua blok query yang isinya nyaris sama.
    $sql = "
        SELECT links.*, users.username AS creator
        FROM links
        LEFT JOIN users ON users.id = links.user_id
        WHERE (:logged_in = 1 OR visibility = 'public')
    ";
    if ($query !== '') {
        $sql .= " AND (title LIKE :q ESCAPE '\\' OR description LIKE :q ESCAPE '\\' OR url LIKE :q ESCAPE '\\')";
    }
    if ($tag !== '') {
        // `tags` disimpan sebagai daftar dipisah koma tanpa padding, mis.
        // "kerja,belajar". Supaya pencarian tag "kerja" tidak ikut menangkap
        // tag lain yang cuma mengandung substring yang sama (mis. "kerjaan"),
        // string dibungkus koma dulu (',kerja,belajar,') baru dicocokkan
        // dengan pola ',<tag>,' — jadi hasilnya persis satu tag utuh.
        // LOWER() di kedua sisi supaya pencarian tag case-insensitive.
        $sql .= " AND (',' || LOWER(tags) || ',') LIKE :tag ESCAPE '\\'";
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';

    $stmt = db()->prepare($sql);
    // PDO::PARAM_INT eksplisit: parameter yang di-bind lewat execute([...]) selalu
    // dikirim sebagai TEXT, dan SQLite tidak otomatis menyamakan TEXT '1' dengan
    // INTEGER 1 pada perbandingan (beda storage class) — jadi harus bindValue().
    $stmt->bindValue(':logged_in', is_logged_in() ? 1 : 0, PDO::PARAM_INT);
    if ($query !== '') {
        // Escape wildcard LIKE ('%', '_') di input pengguna supaya diperlakukan
        // sebagai teks literal, bukan wildcard SQL.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $stmt->bindValue(':q', '%' . $escaped . '%', PDO::PARAM_STR);
    }
    if ($tag !== '') {
        $escapedTag = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($tag));
        $stmt->bindValue(':tag', '%,' . $escapedTag . ',%', PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function can_manage_link(array $link): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    // Admin tetap dapat gerakkan/mengelola semua link; user biasa hanya link miliknya.
    return is_admin() || ((int) ($link['user_id'] ?? 0) === (int) $user['id']);
}

function get_link(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM links WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $link = $stmt->fetch();
    return $link ?: null;
}

function create_link(string $title, string $url, string $description, string $icon, string $visibility, ?int $userId, string $tags = ''): void
{
    // sort_order dihitung lewat subquery di dalam INSERT yang sama, jadi tidak
    // perlu round-trip terpisah ke database (sebelumnya: SELECT MAX() lalu INSERT).
    $stmt = db()->prepare('
        INSERT INTO links (title, url, description, icon, visibility, tags, sort_order, user_id, created_at, updated_at)
        VALUES (
            :title, :url, :description, :icon, :visibility, :tags,
            (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM links),
            :user_id, datetime("now"), datetime("now")
        )
    ');
    $stmt->execute([
        ':title'       => $title,
        ':url'         => $url,
        ':description' => $description,
        ':icon'        => $icon,
        ':visibility'  => $visibility,
        ':tags'        => $tags,
        ':user_id'     => $userId,
    ]);
}

function update_link(int $id, string $title, string $url, string $description, string $icon, string $visibility, string $tags = ''): void
{
    $stmt = db()->prepare('
        UPDATE links
        SET title = :title, url = :url, description = :description, icon = :icon,
            visibility = :visibility, tags = :tags, updated_at = datetime("now")
        WHERE id = :id
    ');
    $stmt->execute([
        ':title'       => $title,
        ':url'         => $url,
        ':description' => $description,
        ':icon'        => $icon,
        ':visibility'  => $visibility,
        ':tags'        => $tags,
        ':id'          => $id,
    ]);
}

/**
 * ==== Tag/kategori link ====
 * Tag disimpan sebagai satu kolom TEXT berisi daftar dipisah koma (mis.
 * "kerja,belajar"), bukan tabel relasi terpisah — cukup untuk skala data
 * aplikasi ini (link per user diasumsikan puluhan-ratusan) dan lebih
 * sederhana daripada tabel many-to-many. Lihat get_links() untuk cara
 * filternya di SQL.
 */

/**
 * Normalisasi input tag mentah (dari form, dipisah koma) jadi array tag unik,
 * sudah di-trim, tanpa entri kosong, dan dibatasi jumlahnya supaya tidak
 * disalahgunakan untuk menyimpan teks sangat panjang.
 */
function normalize_tags(string $raw): array
{
    $tags = [];
    $seen = [];
    foreach (explode(',', $raw) as $part) {
        $t = trim(preg_replace('/\s+/', ' ', $part));
        if ($t === '') {
            continue;
        }
        $t = mb_substr($t, 0, MAX_TAG_LENGTH);
        $key = mb_strtolower($t);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $tags[] = $t;
        if (count($tags) >= MAX_TAGS) {
            break;
        }
    }
    return $tags;
}

/**
 * Ubah array tag jadi string yang disimpan di kolom `tags` (dipisah koma,
 * tanpa spasi/padding ekstra).
 */
function tags_to_storage(array $tags): string
{
    return implode(',', $tags);
}

/**
 * Ubah string dari kolom `tags` jadi array tag untuk ditampilkan.
 */
function tags_from_storage(?string $stored): array
{
    $stored = trim((string) $stored);
    if ($stored === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $stored)), fn($t) => $t !== ''));
}

/**
 * Semua tag unik yang dipakai di link yang boleh dilihat pengguna saat ini
 * (tamu hanya dari link publik, user login dari semua link), lengkap dengan
 * jumlah link per tag — dipakai untuk render daftar filter tag di beranda.
 * Diurutkan alfabetis (case-insensitive).
 */
function get_all_tags(): array
{
    $stmt = db()->prepare("
        SELECT tags FROM links
        WHERE (:logged_in = 1 OR visibility = 'public') AND tags != ''
    ");
    $stmt->bindValue(':logged_in', is_logged_in() ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $stored) {
        foreach (tags_from_storage($stored) as $t) {
            $key = mb_strtolower($t);
            if (!isset($counts[$key])) {
                $counts[$key] = ['tag' => $t, 'count' => 0];
            }
            $counts[$key]['count']++;
        }
    }

    uasort($counts, fn($a, $b) => strcasecmp($a['tag'], $b['tag']));
    return array_values($counts);
}

/**
 * ==== Statistik Klik per Link (opsional, hormati privasi) ====
 * Fitur ini SENGAJA nonaktif secara default (opt-in oleh admin lewat
 * Pengaturan → Statistik). Saat aktif, yang disimpan cuma dua hal per link:
 * - click_count: jumlah klik (angka agregat, tidak per-pengunjung)
 * - last_clicked_at: waktu klik terakhir
 * Tidak ada IP address, user-agent, referrer, atau identitas pengunjung apa
 * pun yang direkam — jadi tidak bisa dipakai untuk melacak siapa yang klik,
 * cuma seberapa sering sebuah link dibuka. Saat nonaktif, tautan dibuka
 * langsung (tanpa lewat go.php) sehingga tidak ada request tambahan sama
 * sekali yang bisa dipakai untuk mencatat kunjungan.
 */
function click_tracking_enabled(): bool
{
    return get_setting('click_tracking_enabled', '0') === '1';
}

function set_click_tracking_enabled(bool $enabled): void
{
    set_setting('click_tracking_enabled', $enabled ? '1' : '0');
}

/**
 * Catat satu klik ke sebuah link. Dipanggil dari go.php, hanya kalau
 * click_tracking_enabled() true. Pakai UPDATE atomik (bukan baca-lalu-tulis)
 * supaya aman dari race condition kalau ada beberapa klik hampir bersamaan.
 */
function record_click(int $id): void
{
    $stmt = db()->prepare('
        UPDATE links
        SET click_count = click_count + 1, last_clicked_at = datetime("now")
        WHERE id = :id
    ');
    $stmt->execute([':id' => $id]);
}

/**
 * Reset seluruh statistik klik (dipakai tombol "Reset Statistik" di halaman
 * admin) — cara termudah bagi admin untuk "menghapus" data yang sudah
 * terlanjur terkumpul kalau berubah pikiran soal privasi.
 */
function reset_click_stats(): void
{
    db()->exec('UPDATE links SET click_count = 0, last_clicked_at = NULL');
}

/**
 * Daftar link diurutkan dari yang paling banyak diklik, dipakai di halaman
 * Statistik admin. Menghormati filter visibilitas yang sama seperti
 * get_links() (tapi di sini dipanggil dari halaman admin yang sudah pasti
 * login, jadi selalu menyertakan link privat).
 */
function get_links_by_clicks(): array
{
    return db()->query('
        SELECT * FROM links
        ORDER BY click_count DESC, id ASC
    ')->fetchAll();
}

function delete_link(int $id): void
{
    $stmt = db()->prepare('DELETE FROM links WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function reorder_links(array $orderedIds): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE links SET sort_order = :pos WHERE id = :id');
    $position = 1;
    foreach ($orderedIds as $id) {
        $stmt->execute([':pos' => $position, ':id' => (int) $id]);
        $position++;
    }
    $pdo->commit();
}

/**
 * Semua URL link yang sudah ada di database (dipakai untuk deteksi duplikat
 * saat impor bookmark, lihat links/import.php).
 */
function get_all_link_urls(): array
{
    return db()->query('SELECT url FROM links')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * ==== Impor Bookmark (Netscape Bookmark File Format) ====
 * Parser untuk file HTML hasil ekspor bookmark browser (Chrome, Firefox,
 * Edge, Safari, dll — semuanya memakai format yang sama, "Netscape Bookmark
 * File Format", walau isinya sebenarnya bukan HTML yang valid: tag <DT>
 * tidak pernah ditutup, dsb). Struktur folder (<H3>/<DL>) sengaja diabaikan
 * — semua <A> yang ditemukan diperlakukan sebagai satu daftar link datar,
 * Folder tidak dipetakan otomatis jadi tag — link hasil impor bisa diberi
 * tag manual belakangan lewat "Edit" kalau perlu.
 */
function parse_bookmark_html(string $html): array
{
    $links = [];
    $html = trim($html);
    if ($html === '') {
        return $links;
    }

    $doc = new DOMDocument();
    $prevErrorSetting = libxml_use_internal_errors(true);
    // Parser HTML DOMDocument cukup toleran terhadap markup rusak/tidak
    // ditutup (mirip cara browser mem-parsingnya), jadi tetap bisa membaca
    // format Netscape Bookmark walau bukan HTML/XML yang valid.
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrorSetting);

    foreach ($doc->getElementsByTagName('a') as $a) {
        $href = trim($a->getAttribute('href'));
        // Lewati skema selain http(s) — javascript:, mailto:, place: (folder
        // spesial Firefox), dst — karena bukan link yang bisa disimpan.
        $href = normalize_url($href);
        if ($href === '') {
            continue;
        }

        $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
        if ($title === '') {
            $title = $href;
        }

        // Browser menyematkan favicon sebagai data-URI base64 lewat atribut
        // ICON= saat ekspor — kalau ada, pakai langsung tanpa perlu diunduh
        // ulang lewat favicon.php.
        $icon = trim($a->getAttribute('icon'));
        if (!str_starts_with(strtolower($icon), 'data:image/')) {
            $icon = '';
        }

        // Atribut TAGS= (dipisah koma) dipakai Firefox & ekspor Tautan sendiri
        // (lihat stream_links_export()) untuk menyimpan tag per link. Chrome/
        // Edge/Safari tidak menulis atribut ini saat ekspor, jadi cukup umum
        // kosong — link tetap diimpor tanpa tag kalau begitu.
        $tags = tags_to_storage(tags_from_storage($a->getAttribute('tags')));

        $links[] = [
            'title' => mb_substr($title, 0, MAX_TITLE_LENGTH),
            'url'   => $href,
            'icon'  => $icon,
            'tags'  => $tags,
        ];
    }

    return $links;
}

/**
 * Normalisasi URL: tambahkan skema https:// jika belum ada
 */
function normalize_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

/**
 * Ambil URL favicon fallback untuk sebuah domain.
 *
 * Tidak langsung menunjuk ke layanan favicon Google — melainkan ke
 * favicon.php milik aplikasi sendiri, yang mengunduh & menyimpan favicon
 * ke cache lokal (data/favicons/) sekali per domain, lalu melayani semua
 * request berikutnya dari disk. Ini menghindari ketergantungan pada Google
 * di setiap render halaman (lebih cepat, tetap jalan walau Google Favicon
 * API sedang bermasalah/diblokir, dan tidak membocorkan setiap kunjungan
 * pengguna ke Google). Lihat favicon.php untuk logika cache-nya.
 */
function favicon_for(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return '';
    }
    return base_path() . 'favicon.php?host=' . urlencode($host);
}

/**
 * ==== Guard instalasi awal ====
 * Jika tabel users masih kosong (instalasi baru), paksa pengguna ke
 * setup.php untuk membuat akun admin pertama dengan username & password
 * pilihan sendiri — bukan kredensial default yang sama di setiap instalasi.
 */
function has_any_user(): bool
{
    return (int) db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
}

$__tautan_current_script = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
if ($__tautan_current_script !== 'setup.php' && !has_any_user()) {
    redirect(base_path() . 'setup.php');
}

/**
 * ==== Fungsi terkait users (khusus admin) ====
 */
function get_users(): array
{
    return db()->query('SELECT id, username, role, created_at FROM users ORDER BY id ASC')->fetchAll();
}

function get_user(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, username, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function username_exists(string $username, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM users WHERE username = :u AND id != :id');
        $stmt->execute([':u' => $username, ':id' => $excludeId]);
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
    }
    return (int) $stmt->fetch()['c'] > 0;
}

function create_user(string $username, string $password, string $role): void
{
    $stmt = db()->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (:u, :p, :r, datetime("now"))');
    $stmt->execute([
        ':u' => $username,
        ':p' => password_hash($password, PASSWORD_DEFAULT),
        ':r' => $role,
    ]);
}

function update_user(int $id, string $username, string $role, ?string $newPassword): void
{
    if ($newPassword !== null && $newPassword !== '') {
        $stmt = db()->prepare('UPDATE users SET username = :u, role = :r, password_hash = :p WHERE id = :id');
        $stmt->execute([
            ':u'  => $username,
            ':r'  => $role,
            ':p'  => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => $id,
        ]);
    } else {
        $stmt = db()->prepare('UPDATE users SET username = :u, role = :r WHERE id = :id');
        $stmt->execute([
            ':u'  => $username,
            ':r'  => $role,
            ':id' => $id,
        ]);
    }
}

function count_admins(): int
{
    return (int) db()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
}

function delete_user(int $id): void
{
    // Link milik user ini tetap ada, hanya user_id di-set NULL (lihat FK ON DELETE SET NULL)
    $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

/**
 * ==== Pengaturan aplikasi (key-value) ====
 * Dipakai untuk opsi sederhana seperti teks footer kustom.
 */
function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM app_settings WHERE `key` = :k');
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === '' ? $default : $value;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('
        INSERT INTO app_settings (`key`, value) VALUES (:k, :v)
        ON CONFLICT(`key`) DO UPDATE SET value = excluded.value
    ');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

/**
 * Teks footer yang tampil di semua halaman. Bisa diubah admin lewat
 * Pengaturan → Tampilan; kalau belum pernah diisi, pakai APP_NAME.
 */
function footer_text(): string
{
    return get_setting('footer_text', APP_NAME);
}

/**
 * ==== Halaman Pengaturan (khusus admin) ====
 * Sub-navigasi (tab) yang dipakai bersama oleh halaman-halaman di admin/
 * supaya terasa sebagai satu halaman "Pengaturan".
 */
function render_settings_tabs(string $active): void
{
    $tabs = [
        'ringkasan' => ['settings.php', '🏠 Ringkasan'],
        'users'     => ['users.php', '👤 Kelola User'],
        'backup'    => ['backup.php', '💾 Backup / Restore'],
        'tampilan'  => ['appearance.php', '🎨 Tampilan'],
        'statistik' => ['stats.php', '📊 Statistik'],
    ];
    echo '<nav class="tabs">';
    foreach ($tabs as $key => [$href, $label]) {
        $cls = $key === $active ? 'tab tab-active' : 'tab';
        echo '<a href="' . e($href) . '" class="' . $cls . '">' . e($label) . '</a>';
    }
    echo '</nav>';
}

/**
 * ==== Backup & Restore Database (khusus admin) ====
 */

/**
 * Nama file unduhan backup, contoh: tautan-backup-20260728-153000.sqlite
 */
function backup_db_filename(): string
{
    return 'tautan-backup-' . date('Ymd-His') . '.sqlite';
}

/**
 * Buat snapshot database yang konsisten (pakai VACUUM INTO) lalu kirim
 * langsung ke browser sebagai file unduhan. Fungsi ini menghentikan
 * eksekusi (exit) setelah selesai mengirim file.
 */
function stream_db_backup(): void
{
    $tmpPath = DB_DIR . '/tmp-backup-' . bin2hex(random_bytes(8)) . '.sqlite';

    try {
        // VACUUM INTO menghasilkan salinan database yang rapi & konsisten
        // tanpa mengganggu koneksi yang sedang berjalan.
        db()->exec('VACUUM INTO ' . db()->quote($tmpPath));
    } catch (Throwable $e) {
        error_log('Database backup failed: ' . $e->getMessage());
        http_response_code(500);
        die('Gagal membuat backup database. Silakan coba lagi.');
    }

    $filename = backup_db_filename();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}

/**
 * ==== Export daftar link (JSON/CSV) ====
 * Beda dengan backup_db (.sqlite, snapshot seluruh database termasuk user &
 * hash password): ini cuma daftar link yang sedang terlihat oleh user (hasil
 * get_links(), hormati filter pencarian/tag yang sedang aktif kalau ada),
 * dalam format portabel yang gampang dibaca/diimpor ke tool lain.
 */

/**
 * Nama file unduhan export, contoh: tautan-export-20260730-153000.json
 */
function export_links_filename(string $format): string
{
    $ext = $format === 'html' ? 'html' : $format;
    return 'tautan-export-' . date('Ymd-His') . '.' . $ext;
}

/**
 * Kirim daftar link sebagai file unduhan JSON, CSV, atau HTML (Netscape
 * Bookmark File Format). Fungsi ini menghentikan eksekusi (exit) setelah
 * selesai mengirim file.
 */
function stream_links_export(string $format, array $links): void
{
    $format = in_array($format, ['csv', 'html'], true) ? $format : 'json';
    $filename = export_links_filename($format);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    if ($format === 'html') {
        // Netscape Bookmark File Format — format yang sama dipakai Chrome,
        // Firefox, Edge, Safari, dkk saat "Export Bookmarks", dan format yang
        // sudah dibaca `parse_bookmark_html()` di links/import.php. Jadi file
        // ini bisa diimpor balik ke Tautan, atau diimpor langsung ke bookmark
        // manager browser mana pun.
        header('Content-Type: application/octet-stream; charset=UTF-8');
        $out = fopen('php://output', 'w');
        fwrite($out, "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n");
        fwrite($out, "<!-- Diekspor dari Tautan. Format ini kompatibel dengan Bookmark Manager di Chrome, Firefox, Edge, Safari, dsb. -->\n");
        fwrite($out, "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n");
        fwrite($out, "<TITLE>Bookmarks</TITLE>\n");
        fwrite($out, "<H1>Bookmarks</H1>\n");
        fwrite($out, "<DL><p>\n");
        foreach ($links as $link) {
            $addDate = strtotime($link['created_at'] ?? '') ?: time();
            $attrs = ' HREF="' . e($link['url']) . '" ADD_DATE="' . $addDate . '"';

            $tagList = tags_from_storage($link['tags'] ?? '');
            if (!empty($tagList)) {
                $attrs .= ' TAGS="' . e(implode(',', $tagList)) . '"';
            }

            $icon = trim($link['icon'] ?? '');
            if (str_starts_with(strtolower($icon), 'data:image/')) {
                $attrs .= ' ICON="' . e($icon) . '"';
            }

            $title = $link['title'] !== '' ? $link['title'] : $link['url'];
            fwrite($out, "    <DT><A" . $attrs . ">" . e($title) . "</A>\n");
            if (!empty($link['description'])) {
                fwrite($out, "    <DD>" . e($link['description']) . "\n");
            }
        }
        fwrite($out, "</DL><p>\n");
        fclose($out);
        exit;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        $out = fopen('php://output', 'w');
        // BOM UTF-8 supaya Excel membaca karakter non-ASCII (mis. judul
        // berbahasa Indonesia dengan diakritik) dengan benar.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Judul', 'URL', 'Deskripsi', 'Tag', 'Visibilitas', 'Dibuat']);
        foreach ($links as $link) {
            fputcsv($out, [
                $link['title'],
                $link['url'],
                $link['description'],
                implode(', ', tags_from_storage($link['tags'] ?? '')),
                $link['visibility'],
                $link['created_at'],
            ]);
        }
        fclose($out);
    } else {
        header('Content-Type: application/json; charset=UTF-8');
        $data = array_map(function (array $link): array {
            return [
                'title'       => $link['title'],
                'url'         => $link['url'],
                'description' => $link['description'],
                'tags'        => tags_from_storage($link['tags'] ?? ''),
                'visibility'  => $link['visibility'],
                'created_at'  => $link['created_at'],
            ];
        }, $links);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    exit;
}

/**
 * Cek apakah sebuah file benar-benar file database SQLite (cek magic header).
 */
function is_sqlite_file(string $path): bool
{
    if (!is_file($path) || filesize($path) > BACKUP_MAX_FILE_SIZE) {
        return false;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $header = fread($fh, 16);
    fclose($fh);
    return $header === "SQLite format 3\000";
}

/**
 * Cek apakah file SQLite punya struktur tabel yang sesuai dengan aplikasi ini
 * (minimal ada tabel users & links), supaya tidak sembarang file diterima.
 */
function validate_sqlite_schema(string $path): bool
{
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        return in_array('users', $tables, true) && in_array('links', $tables, true);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Pulihkan database dari file yang diunggah.
 * - Salinan database saat ini otomatis disimpan dulu ke data/backups/ (jaga-jaga).
 * - File baru ditimpa lewat rename() (atomik) supaya koneksi yang sedang
 *   berjalan pada request ini tidak ikut rusak/korup.
 */
function restore_db_from_upload(string $uploadedTmpPath): void
{
    if (!is_file($uploadedTmpPath) || filesize($uploadedTmpPath) > 64 * 1024 * 1024) {
        throw new RuntimeException('Ukuran backup melebihi batas 64 MB.');
    }

    if (!is_sqlite_file($uploadedTmpPath) || !validate_sqlite_schema($uploadedTmpPath)) {
        throw new RuntimeException('File backup tidak valid.');
    }

    $backupDir = DB_DIR . '/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Direktori backup tidak dapat dibuat.');
    }

    // Simpan salinan pengaman dari database yang sedang aktif sebelum ditimpa
    if (is_file(DB_PATH)) {
        $safetyCopy = $backupDir . '/before-restore-' . date('Ymd-His') . '.sqlite';
        copy(DB_PATH, $safetyCopy);
    }

    $stagingPath = DB_PATH . '.new';
    if (!copy($uploadedTmpPath, $stagingPath)) {
        throw new RuntimeException('Gagal menyalin file yang diunggah.');
    }

    if (!rename($stagingPath, DB_PATH)) {
        @unlink($stagingPath);
        throw new RuntimeException('Gagal mengganti file database.');
    }
}
