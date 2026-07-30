<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

// Satu query gabungan (subquery) untuk 3 angka ringkasan, daripada 3 query terpisah.
$stats = db()->query("
    SELECT
        (SELECT COUNT(*) FROM users)                    AS user_count,
        (SELECT COUNT(*) FROM users WHERE role='admin')  AS admin_count,
        (SELECT COUNT(*) FROM links)                     AS link_count,
        (SELECT COALESCE(SUM(click_count), 0) FROM links) AS total_clicks
")->fetch();
$userCount   = (int) $stats['user_count'];
$adminCount  = (int) $stats['admin_count'];
$linkCount   = (int) $stats['link_count'];
$totalClicks = (int) $stats['total_clicks'];

$backupDir = DB_DIR . '/backups';
$lastBackupAt = null;
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sqlite') ?: [];
    if ($files) {
        $latest = max(array_map('filemtime', $files));
        $lastBackupAt = date('d M Y H:i', $latest);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>⚙️ Pengaturan</h1>
    <p class="page-head-sub"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></p>
</div>

<?php render_settings_tabs('ringkasan'); ?>

<div class="settings-grid">
    <a href="users.php" class="settings-card">
        <div class="settings-card-icon">👤</div>
        <h2>Kelola User</h2>
        <p><?= (int) $userCount ?> user terdaftar &middot; <?= (int) $adminCount ?> admin</p>
    </a>
    <a href="backup.php" class="settings-card">
        <div class="settings-card-icon">💾</div>
        <h2>Backup &amp; Restore</h2>
        <p>
            <?= (int) $linkCount ?> tautan tersimpan
            <?php if ($lastBackupAt): ?>
                &middot; salinan pengaman terakhir: <?= e($lastBackupAt) ?>
            <?php else: ?>
                &middot; belum ada salinan pengaman otomatis
            <?php endif; ?>
        </p>
    </a>
    <a href="appearance.php" class="settings-card">
        <div class="settings-card-icon">🎨</div>
        <h2>Tampilan</h2>
        <p>Ubah teks footer aplikasi</p>
    </a>
    <a href="stats.php" class="settings-card">
        <div class="settings-card-icon">📊</div>
        <h2>Statistik</h2>
        <p>
            <?php if (click_tracking_enabled()): ?>
                Aktif &middot; <?= $totalClicks ?> klik tercatat
            <?php else: ?>
                Nonaktif (opsional, hormati privasi)
            <?php endif; ?>
        </p>
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
