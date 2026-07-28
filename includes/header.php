<?php
require_once __DIR__ . '/functions.php';
$__user = current_user();
$__flashes = get_flashes();
$__is_setup_page = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'setup.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">🔗 <?= e(APP_NAME) ?></a>
        <?php if (!$__is_setup_page): ?>
        <nav class="nav">
            <?php if ($__user): ?>
                <span class="nav-user">Halo, <strong><?= e($__user['username']) ?></strong> <span class="badge badge-<?= e($__user['role']) ?>"><?= e($__user['role']) ?></span></span>
                <?php if ($__user['role'] === 'admin'): ?>
                    <a href="settings.php">⚙️ Pengaturan</a>
                <?php endif; ?>
                <a href="logout.php" onclick="return confirm('Keluar dari akun?');">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="container">
    <?php foreach ($__flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
