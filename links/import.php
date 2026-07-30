<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

const IMPORT_MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB — cukup besar untuk ribuan bookmark

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $visibility     = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $skipDuplicates = !empty($_POST['skip_duplicates']);

    if (empty($_FILES['bookmark_file']) || $_FILES['bookmark_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Pilih file HTML hasil ekspor bookmark browser terlebih dahulu.';
    } elseif ($_FILES['bookmark_file']['size'] > IMPORT_MAX_FILE_SIZE) {
        $errors[] = 'Ukuran file terlalu besar (maksimum 5 MB).';
    } else {
        $html   = (string) file_get_contents($_FILES['bookmark_file']['tmp_name']);
        $parsed = parse_bookmark_html($html);

        if (empty($parsed)) {
            $errors[] = 'Tidak ada tautan yang ditemukan di file tersebut. Pastikan ini file ekspor bookmark HTML (Netscape Bookmark Format) dari browser — biasanya lewat menu "Bookmark Manager → Export Bookmarks".';
        } else {
            // Ambil semua URL yang sudah ada sekali di awal (bukan query per-link),
            // lalu dipakai sebagai lookup di memori sekaligus dipakai mencegah
            // duplikat antar-baris di dalam file yang sama.
            $existingUrls = array_flip(get_all_link_urls());
            $user = current_user();

            $imported = 0;
            $skipped  = 0;
            foreach ($parsed as $item) {
                if ($skipDuplicates && isset($existingUrls[$item['url']])) {
                    $skipped++;
                    continue;
                }
                create_link($item['title'], $item['url'], '', $item['icon'], $visibility, $user['id'], $item['tags']);
                $existingUrls[$item['url']] = true;
                $imported++;
            }

            $message = "Impor selesai: {$imported} tautan baru ditambahkan";
            if ($skipped > 0) {
                $message .= ", {$skipped} dilewati karena URL sudah ada";
            }
            $message .= '.';
            set_flash('success', $message);
            redirect('../index.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-box">
    <h1>Impor Tautan dari Bookmark Browser</h1>
    <p>
        Unggah file HTML hasil ekspor bookmark dari Chrome, Firefox, Edge, Safari, dsb
        (biasanya lewat menu <em>Bookmark Manager → Export Bookmarks</em>), atau file
        hasil "Ekspor Bookmark" dari Tautan sendiri. Semua tautan di dalamnya akan
        ditambahkan ke daftar. Tag ikut diimpor kalau file bookmark menyertakan atribut
        <code>TAGS=</code> (dipakai Firefox & ekspor Tautan — Chrome/Edge/Safari biasanya
        tidak menuliskannya). Struktur folder pada file bookmark tetap tidak dipertahankan,
        karena aplikasi ini belum mendukungnya.
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="import.php" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>

        <label>
            File Bookmark (.html)
            <input type="file" name="bookmark_file" accept=".html,.htm" required>
        </label>

        <label class="radio-group">
            Visibilitas tautan yang diimpor
            <span class="radio-options">
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="public" checked>
                    Publik (semua orang bisa lihat)
                </label>
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="private">
                    🔒 Hanya user login
                </label>
            </span>
        </label>

        <label class="radio-inline">
            <input type="checkbox" name="skip_duplicates" value="1" checked>
            Lewati tautan yang URL-nya sudah ada di daftar
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Impor Tautan</button>
            <a href="../index.php" class="btn">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
