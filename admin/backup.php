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
        $errors[] = t('backup_error_no_file');
    } else {
        $tmpPath = $_FILES['backup_file']['tmp_name'];

        if (!is_sqlite_file($tmpPath)) {
            $errors[] = t('backup_error_invalid_sqlite');
        } elseif (!validate_sqlite_schema($tmpPath)) {
            $errors[] = t('backup_error_invalid_schema');
        } else {
            try {
                restore_db_from_upload($tmpPath);
                set_flash('success', t('backup_restore_success'));
                redirect('backup.php');
            } catch (Throwable $e) {
                $errors[] = t('backup_restore_failed', [':error' => $e->getMessage()]);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1><?= e(t('settings_title')) ?></h1>
</div>

<?php render_settings_tabs('backup'); ?>

<div class="page-head">
    <h2 style="margin:0;"><?= t('backup_page_title') ?></h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2><?= e(t('backup_download_title')) ?></h2>
    <p><?= t('backup_download_desc') ?></p>
    <div class="form-actions">
        <a href="backup.php?action=download" class="btn btn-primary"><?= e(t('backup_download_btn')) ?></a>
    </div>
</div>

<div class="form-box">
    <h2><?= e(t('backup_restore_title')) ?></h2>
    <p><?= t('backup_restore_desc') ?></p>
    <form method="post"
          action="backup.php"
          enctype="multipart/form-data"
          class="form"
          onsubmit="return confirm('<?= e(t('backup_restore_confirm')) ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore">
        <label>
            <?= e(t('backup_file_label')) ?>
            <input type="file" name="backup_file" accept=".sqlite,.db" required>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-danger"><?= e(t('backup_restore_btn')) ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
