<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

$errors = [];
$footerText = footer_text();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $footerText = trim($_POST['footer_text'] ?? '');

    if ($footerText === '') {
        $errors[] = t('appearance_error_empty');
    } elseif (mb_strlen($footerText) > 150) {
        $errors[] = t('appearance_error_too_long');
    }

    if (empty($errors)) {
        set_setting('footer_text', $footerText);
        set_flash('success', t('appearance_success'));
        redirect('appearance.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1><?= e(t('settings_title')) ?></h1>
</div>

<?php render_settings_tabs('tampilan'); ?>

<div class="page-head">
    <h2 style="margin:0;"><?= e(t('appearance_page_title')) ?></h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2><?= e(t('appearance_footer_title')) ?></h2>
    <p><?= e(t('appearance_footer_desc')) ?></p>
    <form method="post" action="appearance.php" class="form">
        <?= csrf_field() ?>
        <label>
            <?= e(t('appearance_label')) ?>
            <input type="text" name="footer_text" required maxlength="150" value="<?= e($footerText) ?>">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(t('btn_save')) ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
