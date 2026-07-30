<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

// Unduh backup (GET) — kirim file langsung, jangan render halaman HTML
if (($_GET['action'] ?? '') === 'download') {
    stream_db_backup();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    verify_csrf();

    if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Pilih file backup (.sqlite) yang valid untuk dipulihkan.';
    } else {
        $tmpPath = $_FILES['backup_file']['tmp_name'];

        if (!is_sqlite_file($tmpPath)) {
            $errors[] = 'File yang diunggah bukan file database SQLite yang valid.';
        } elseif (!validate_sqlite_schema($tmpPath)) {
            $errors[] = 'Struktur tabel di file tersebut tidak sesuai dengan aplikasi ini (users/links tidak ditemukan).';
        } else {
            try {
                restore_db_from_upload($tmpPath);
                set_flash('success', 'Database berhasil dipulihkan dari file backup. Jika Anda logout otomatis, silakan login kembali.');
                redirect('backup.php');
            } catch (Throwable $e) {
                $errors[] = 'Gagal memulihkan database: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>⚙️ Pengaturan</h1>
</div>

<?php render_settings_tabs('backup'); ?>

<div class="page-head">
    <h2 style="margin:0;">Backup &amp; Restore Database</h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2>Unduh Backup</h2>
    <p>Unduh salinan database saat ini (semua user &amp; tautan) sebagai satu file <code>.sqlite</code> yang bisa disimpan sebagai cadangan.</p>
    <div class="form-actions">
        <a href="backup.php?action=download" class="btn btn-primary">⬇️ Unduh Backup Sekarang</a>
    </div>
</div>

<div class="form-box">
    <h2>Pulihkan dari Backup</h2>
    <p>⚠️ Memulihkan database akan <strong>menimpa seluruh data saat ini</strong> (user &amp; tautan) dengan isi file backup yang diunggah. Salinan database saat ini akan disimpan otomatis terlebih dahulu sebagai jaga-jaga.</p>
    <form method="post"
          action="backup.php"
          enctype="multipart/form-data"
          class="form"
          onsubmit="return confirm('Yakin ingin memulihkan database dari file ini? Semua data saat ini akan ditimpa.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore">
        <label>
            File Backup (.sqlite)
            <input type="file" name="backup_file" accept=".sqlite,.db" required>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-danger">Pulihkan Database</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
