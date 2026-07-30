<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = t('login_error_required');
    } elseif (attempt_login($username, $password)) {
        set_flash('success', t('login_success'));
        redirect('index.php');
    } else {
        $error = t('login_error_invalid');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-box">
    <h1><?= e(t('login_title')) ?></h1>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" class="form">
        <?= csrf_field() ?>
        <label>
            <?= e(t('login_username_label')) ?>
            <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </label>
        <label>
            <?= e(t('login_password_label')) ?>
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block"><?= e(t('login_submit')) ?></button>
    </form>
    <p class="hint"><?= e(t('login_hint')) ?></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
