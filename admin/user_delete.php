<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$me = current_user();

if ($id === (int) $me['id']) {
    set_flash('error', t('user_delete_self_error'));
    redirect('users.php');
}

$target = get_user($id);
if (!$target) {
    set_flash('error', t('user_delete_not_found'));
    redirect('users.php');
}

if ($target['role'] === 'admin' && count_admins() <= 1) {
    set_flash('error', t('user_delete_last_admin_error'));
    redirect('users.php');
}

delete_user($id);
set_flash('success', t('user_delete_success'));
redirect('users.php');
