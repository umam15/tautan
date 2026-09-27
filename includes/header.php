<?php
require_once __DIR__ . '/functions.php';
$__user = current_user();
$__flashes = get_flashes();
$__scriptDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
$__is_setup_page = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'setup.php';
$__base = base_path();
$__settingsHref = $__scriptDir === 'admin' ? 'settings.php' : $__base . 'admin/settings.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self'; img-src 'self' data: http: https:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; form-action 'self';">
<title><?= e(APP_NAME) ?></title>

<!-- Set tema (terang/gelap) sebelum CSS dirender, supaya tidak ada "kedip"
     ke tema default saat memuat halaman untuk user yang sudah memilih tema
     terang lewat tombol di topbar (disimpan di localStorage). -->
<script>
(function () {
    try {
        var t = localStorage.getItem('tautan-theme');
        if (t === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch (e) {}
})();
</script>

<link rel="stylesheet" href="<?= e($__base) ?>assets/style.css">

<!-- PWA: bisa di-"Install app" lewat browser (Chrome/Edge/Safari/dsb) -->
<link rel="manifest" href="<?= e($__base) ?>manifest.json">
<meta name="theme-color" content="#0f1220" id="meta-theme-color">
<link rel="icon" href="<?= e($__base) ?>assets/icons/icon-32.png" sizes="32x32">
<link rel="icon" href="<?= e($__base) ?>assets/icons/icon-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e($__base) ?>assets/icons/icon-180.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
</head>
<body>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= e($__base) ?>sw.js');
    });
}
</script>
<header class="topbar">
    <div class="topbar-inner">
        <a href="<?= e($__base) ?>index.php" class="brand">🔗 <?= e(APP_NAME) ?></a>
        <div class="topbar-right">
            <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Ganti tema terang/gelap" title="Ganti tema terang/gelap">🌙</button>
            <?php if (!$__is_setup_page): ?>
            <nav class="nav">
                <?php if ($__user): ?>
                    <span class="nav-user">Halo, <strong><?= e($__user['username']) ?></strong> <span class="badge badge-<?= e($__user['role']) ?>"><?= e($__user['role']) ?></span></span>
                    <?php if ($__user['role'] === 'admin'): ?>
                        <a href="<?= e($__settingsHref) ?>">⚙️ Pengaturan</a>
                    <?php endif; ?>
                    <a href="<?= e($__base) ?>logout.php" onclick="return confirm('Keluar dari akun?');">Logout</a>
                <?php else: ?>
                    <a href="<?= e($__base) ?>login.php">Login</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
(function () {
    var btn = document.getElementById('theme-toggle');
    var metaColor = document.getElementById('meta-theme-color');
    var COLORS = { dark: '#0f1220', light: '#f4f5fb' };

    function current() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }
    function applyIcon() {
        var isLight = current() === 'light';
        btn.textContent = isLight ? '☀️' : '🌙';
        btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        if (metaColor) {
            metaColor.setAttribute('content', COLORS[current()]);
        }
    }

    applyIcon();
    btn.addEventListener('click', function () {
        var next = current() === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('tautan-theme', next); } catch (e) {}
        applyIcon();
    });
})();
</script>
<main class="container">
    <?php foreach ($__flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

