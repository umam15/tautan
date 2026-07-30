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
        $errors[] = t('import_error_no_file');
    } elseif ($_FILES['bookmark_file']['size'] > IMPORT_MAX_FILE_SIZE) {
        $errors[] = t('import_error_too_large');
    } else {
        $html   = (string) file_get_contents($_FILES['bookmark_file']['tmp_name']);
        $parsed = parse_bookmark_html($html);

        if (empty($parsed)) {
            $errors[] = t('import_error_no_links');
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

            $message = t('import_success', [':imported' => (string) $imported]);
            if ($skipped > 0) {
                $message .= t('import_success_skipped', [':skipped' => (string) $skipped]);
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
    <h1><?= e(t('import_title')) ?></h1>
    <p>
        <?= t('import_intro') ?>
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="import.php" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>

        <label>
            <?= e(t('import_file_label')) ?>
            <input type="file" name="bookmark_file" accept=".html,.htm" required>
        </label>

        <label class="radio-group">
            <?= e(t('import_visibility_label')) ?>
            <span class="radio-options">
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="public" checked>
                    <?= e(t('link_form_vis_public')) ?>
                </label>
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="private">
                    <?= e(t('link_form_vis_private')) ?>
                </label>
            </span>
        </label>

        <label class="radio-inline">
            <input type="checkbox" name="skip_duplicates" value="1" checked>
            <?= e(t('import_skip_duplicates_label')) ?>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(t('import_submit')) ?></button>
            <a href="../index.php" class="btn"><?= e(t('btn_cancel')) ?></a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
