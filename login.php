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
        $error = 'Username dan password wajib diisi.';
    } elseif (attempt_login($username, $password)) {
        set_flash('success', 'Berhasil login. Selamat datang kembali!');
        redirect('index.php');
    } else {
        $error = 'Username atau password salah.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-box">
    <h1>Login</h1>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" class="form">
        <?= csrf_field() ?>
        <label>
            Username
            <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <p class="hint">Belum punya akun? Hubungi admin untuk dibuatkan user.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
