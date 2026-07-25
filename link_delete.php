<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0 && get_link($id)) {
    delete_link($id);
    set_flash('success', 'Tautan berhasil dihapus.');
} else {
    set_flash('error', 'Tautan tidak ditemukan.');
}

redirect('index.php');
