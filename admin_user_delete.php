<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin_users.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$me = current_user();

if ($id === (int) $me['id']) {
    set_flash('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
    redirect('admin_users.php');
}

$target = get_user($id);
if (!$target) {
    set_flash('error', 'User tidak ditemukan.');
    redirect('admin_users.php');
}

if ($target['role'] === 'admin' && count_admins() <= 1) {
    set_flash('error', 'Tidak bisa menghapus admin terakhir.');
    redirect('admin_users.php');
}

delete_user($id);
set_flash('success', 'User berhasil dihapus.');
redirect('admin_users.php');
