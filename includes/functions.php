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
function get_links(): array
{
    if (is_logged_in()) {
        $stmt = db()->query('
            SELECT links.*, users.username AS creator
            FROM links
            LEFT JOIN users ON users.id = links.user_id
            ORDER BY sort_order ASC, id ASC
        ');
    } else {
        $stmt = db()->query("
            SELECT links.*, users.username AS creator
            FROM links
            LEFT JOIN users ON users.id = links.user_id
            WHERE visibility = 'public'
            ORDER BY sort_order ASC, id ASC
        ");
    }
    return $stmt->fetchAll();
}

function get_link(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM links WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $link = $stmt->fetch();
    return $link ?: null;
}

function next_sort_order(): int
{
    $max = db()->query('SELECT MAX(sort_order) AS m FROM links')->fetch()['m'];
    return $max === null ? 1 : ((int) $max + 1);
}

function create_link(string $title, string $url, string $description, string $icon, string $visibility, ?int $userId): void
{
    $stmt = db()->prepare('
        INSERT INTO links (title, url, description, icon, visibility, sort_order, user_id, created_at, updated_at)
        VALUES (:title, :url, :description, :icon, :visibility, :sort_order, :user_id, datetime("now"), datetime("now"))
    ');
    $stmt->execute([
        ':title'       => $title,
        ':url'         => $url,
        ':description' => $description,
        ':icon'        => $icon,
        ':visibility'  => $visibility,
        ':sort_order'  => next_sort_order(),
        ':user_id'     => $userId,
    ]);
}

function update_link(int $id, string $title, string $url, string $description, string $icon, string $visibility): void
{
    $stmt = db()->prepare('
        UPDATE links
        SET title = :title, url = :url, description = :description, icon = :icon,
            visibility = :visibility, updated_at = datetime("now")
        WHERE id = :id
    ');
    $stmt->execute([
        ':title'       => $title,
        ':url'         => $url,
        ':description' => $description,
        ':icon'        => $icon,
        ':visibility'  => $visibility,
        ':id'          => $id,
    ]);
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
 * Ambil favicon sederhana dari domain URL (Google favicon service) untuk fallback ikon
 */
function favicon_for(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return '';
    }
    return 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
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
    redirect('setup.php');
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
 * ==== Halaman Pengaturan (khusus admin) ====
 * Sub-navigasi (tab) yang dipakai bersama oleh settings.php, admin_users.php
 * dan admin_backup.php supaya terasa sebagai satu halaman "Pengaturan".
 */
function render_settings_tabs(string $active): void
{
    $tabs = [
        'ringkasan' => ['settings.php', '🏠 Ringkasan'],
        'users'     => ['admin_users.php', '👤 Kelola User'],
        'backup'    => ['admin_backup.php', '💾 Backup / Restore'],
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
        http_response_code(500);
        die('Gagal membuat backup database: ' . e($e->getMessage()));
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
 * Cek apakah sebuah file benar-benar file database SQLite (cek magic header).
 */
function is_sqlite_file(string $path): bool
{
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
    $backupDir = DB_DIR . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
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
