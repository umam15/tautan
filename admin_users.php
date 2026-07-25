<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_admin();

$users = get_users();
$me = current_user();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Kelola User</h1>
    <a href="admin_user_form.php" class="btn btn-primary">+ Tambah User</a>
</div>

<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Dibuat</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int) $u['id'] ?></td>
                <td><?= e($u['username']) ?> <?php if ($u['id'] == $me['id']): ?><em>(Anda)</em><?php endif; ?></td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                <td><?= e($u['created_at']) ?></td>
                <td class="table-actions">
                    <a href="admin_user_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-small">Edit</a>
                    <?php if ($u['id'] != $me['id']): ?>
                        <form method="post" action="admin_user_delete.php" onsubmit="return confirm('Hapus user <?= e($u['username']) ?>? Tautan yang pernah dibuat user ini tidak akan ikut terhapus.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
