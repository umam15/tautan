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
        set_flash('error', t('user_form_not_found'));
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
        $errors[] = t('user_form_error_username_req');
    } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $values['username'])) {
        $errors[] = t('user_form_error_username_fmt');
    } elseif (username_exists($values['username'], $editing ? $id : null)) {
        $errors[] = t('user_form_error_username_dup');
    }

    if (!$editing && $password === '') {
        $errors[] = t('user_form_error_password_req');
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = t('user_form_error_password_len');
    }

    // Cegah menghapus role admin terakhir
    if ($editing && $user['role'] === 'admin' && $values['role'] === 'user' && count_admins() <= 1) {
        $errors[] = t('user_form_error_last_admin');
    }

    if (empty($errors)) {
        if ($editing) {
            update_user($id, $values['username'], $values['role'], $password !== '' ? $password : null);
            set_flash('success', t('user_form_updated'));
        } else {
            create_user($values['username'], $password, $values['role']);
            set_flash('success', t('user_form_created'));
        }
        redirect('users.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-box">
    <h1><?= $editing ? e(t('user_form_title_edit')) : e(t('user_form_title_add')) ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="user_form.php<?= $editing ? '?id=' . $id : '' ?>" class="form">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <label>
            <?= e(t('user_form_label_username')) ?>
            <input type="text" name="username" required value="<?= e($values['username']) ?>">
        </label>

        <label>
            <?= e(t('user_form_label_password')) ?> <?= $editing ? e(t('user_form_password_keep_hint')) : '' ?>
            <input type="password" name="password" <?= $editing ? '' : 'required' ?>>
        </label>

        <label>
            <?= e(t('user_form_label_role')) ?>
            <select name="role">
                <option value="user" <?= $values['role'] === 'user' ? 'selected' : '' ?>><?= e(t('user_form_role_user')) ?></option>
                <option value="admin" <?= $values['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('user_form_role_admin')) ?></option>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? e(t('user_form_submit_edit')) : e(t('user_form_submit_add')) ?></button>
            <a href="users.php" class="btn"><?= e(t('btn_cancel')) ?></a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
