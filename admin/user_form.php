<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$user = null;

if ($editing) {
    $user = get_user($id);
    if (!$user) {
        set_flash('error', 'User tidak ditemukan.');
        redirect('users.php');
    }
}

$errors = [];
$values = [
    'username' => $user['username'] ?? '',
    'role'     => $user['role'] ?? 'user',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['username'] = trim($_POST['username'] ?? '');
    $values['role']     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $password            = (string) ($_POST['password'] ?? '');

    if ($values['username'] === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $values['username'])) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, garis bawah/strip (3-32 karakter).';
    } elseif (username_exists($values['username'], $editing ? $id : null)) {
        $errors[] = 'Username sudah digunakan.';
    }

    if (!$editing && $password === '') {
        $errors[] = 'Password wajib diisi untuk user baru.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    // Cegah menghapus role admin terakhir
    if ($editing && $user['role'] === 'admin' && $values['role'] === 'user' && count_admins() <= 1) {
        $errors[] = 'Tidak bisa mengubah role admin terakhir menjadi user biasa.';
    }

    if (empty($errors)) {
        if ($editing) {
            update_user($id, $values['username'], $values['role'], $password !== '' ? $password : null);
            set_flash('success', 'User berhasil diperbarui.');
        } else {
            create_user($values['username'], $password, $values['role']);
            set_flash('success', 'User berhasil ditambahkan.');
        }
        redirect('users.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-box">
    <h1><?= $editing ? 'Edit User' : 'Tambah User' ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="user_form.php<?= $editing ? '?id=' . $id : '' ?>" class="form">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <label>
            Username
            <input type="text" name="username" required value="<?= e($values['username']) ?>">
        </label>

        <label>
            Password <?= $editing ? '(kosongkan jika tidak ingin mengubah)' : '' ?>
            <input type="password" name="password" <?= $editing ? '' : 'required' ?>>
        </label>

        <label>
            Role
            <select name="role">
                <option value="user" <?= $values['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $values['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah User' ?></button>
            <a href="users.php" class="btn">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
