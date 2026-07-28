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
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $values['username'])) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, garis bawah/strip (3-32 karakter).';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi password tidak sama dengan password.';
    }

    // Jaga-jaga terhadap kondisi balapan (mis. dua tab dibuka bersamaan saat instalasi)
    if (empty($errors) && has_any_user()) {
        redirect('login.php');
    }

    if (empty($errors)) {
        create_user($values['username'], $password, 'admin');
        attempt_login($values['username'], $password);
        set_flash('success', 'Akun admin berhasil dibuat. Selamat datang di ' . APP_NAME . '!');
        redirect('index.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-box">
    <h1>🚀 Setup Awal</h1>
    <p class="field-hint" style="margin-bottom:16px;">
        Ini pertama kalinya <?= e(APP_NAME) ?> dijalankan &mdash; belum ada akun sama sekali.
        Buat akun <strong>admin</strong> pertama untuk mulai mengelola tautan &amp; user lain.
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="setup.php" class="form">
        <?= csrf_field() ?>
        <label>
            Username Admin
            <input type="text" name="username" required autofocus minlength="3" maxlength="32"
                   pattern="[a-zA-Z0-9_.\-]{3,32}" value="<?= e($values['username']) ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required minlength="6">
        </label>
        <label>
            Konfirmasi Password
            <input type="password" name="password_confirm" required minlength="6">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Buat Akun Admin &amp; Mulai</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
