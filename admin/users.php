<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

$users = get_users();
$me = current_user();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1><?= e(t('settings_title')) ?></h1>
</div>

<?php render_settings_tabs('users'); ?>

<div class="page-head">
    <h2 style="margin:0;"><?= e(t('users_title')) ?></h2>
    <a href="user_form.php" class="btn btn-primary"><?= e(t('users_add_btn')) ?></a>
</div>

<table class="table">
    <thead>
        <tr>
            <th><?= e(t('users_col_id')) ?></th>
            <th><?= e(t('users_col_username')) ?></th>
            <th><?= e(t('users_col_role')) ?></th>
            <th><?= e(t('users_col_created')) ?></th>
            <th><?= e(t('users_col_actions')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int) $u['id'] ?></td>
                <td><?= e($u['username']) ?> <?php if ($u['id'] == $me['id']): ?><em><?= e(t('users_you_suffix')) ?></em><?php endif; ?></td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                <td><?= e($u['created_at']) ?></td>
                <td class="table-actions">
                    <a href="user_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-small"><?= e(t('btn_edit')) ?></a>
                    <?php if ($u['id'] != $me['id']): ?>
                        <form method="post" action="user_delete.php" onsubmit="return confirm('<?= e(t('users_delete_confirm', [':username' => $u['username']])) ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger"><?= e(t('btn_delete')) ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
