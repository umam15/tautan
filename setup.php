<?php
require_once __DIR__ . '/includes/functions.php';

// Kalau sudah pernah ada user (instalasi sudah di-setup), halaman ini tidak boleh diakses lagi
if (has_any_user()) {
    redirect('login.php');
}

$errors = [];
$values = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['username'] = trim($_POST['username'] ?? '');
    $password            = (string) ($_POST['password'] ?? '');
    $passwordConfirm     = (string) ($_POST['password_confirm'] ?? '');

    if ($values['username'] === '') {
        $errors[] = t('setup_error_username_req');
    } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $values['username'])) {
        $errors[] = t('setup_error_username_format');
    }

    if ($password === '') {
        $errors[] = t('setup_error_password_req');
    } elseif (strlen($password) < 6) {
        $errors[] = t('setup_error_password_length');
    } elseif ($password !== $passwordConfirm) {
        $errors[] = t('setup_error_password_match');
    }

    // Jaga-jaga terhadap kondisi balapan (mis. dua tab dibuka bersamaan saat instalasi)
    if (empty($errors) && has_any_user()) {
        redirect('login.php');
    }

    if (empty($errors)) {
        create_user($values['username'], $password, 'admin');
        attempt_login($values['username'], $password);
        set_flash('success', t('setup_success', [':app' => APP_NAME]));
        redirect('index.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-box">
    <h1><?= e(t('setup_title')) ?></h1>
    <p class="field-hint" style="margin-bottom:16px;">
        <?= t('setup_intro', [':app' => e(APP_NAME)]) ?>
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="setup.php" class="form">
        <?= csrf_field() ?>
        <label>
            <?= e(t('setup_username_label')) ?>
            <input type="text" name="username" required autofocus minlength="3" maxlength="32"
                   pattern="[a-zA-Z0-9_.\-]{3,32}" value="<?= e($values['username']) ?>">
        </label>
        <label>
            <?= e(t('setup_password_label')) ?>
            <input type="password" name="password" required minlength="6">
        </label>
        <label>
            <?= e(t('setup_password_confirm_label')) ?>
            <input type="password" name="password_confirm" required minlength="6">
        </label>
        <button type="submit" class="btn btn-primary btn-block"><?= t('setup_submit') ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
