<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_admin();

$userCount  = count(get_users());
$adminCount = count_admins();
$linkCount  = (int) db()->query('SELECT COUNT(*) AS c FROM links')->fetch()['c'];

$backupDir = DB_DIR . '/backups';
$lastBackupAt = null;
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sqlite') ?: [];
    if ($files) {
        $latest = max(array_map('filemtime', $files));
        $lastBackupAt = date('d M Y H:i', $latest);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>⚙️ Pengaturan</h1>
</div>

<?php render_settings_tabs('ringkasan'); ?>

<div class="settings-grid">
    <a href="admin_users.php" class="settings-card">
        <div class="settings-card-icon">👤</div>
        <h2>Kelola User</h2>
        <p><?= (int) $userCount ?> user terdaftar &middot; <?= (int) $adminCount ?> admin</p>
    </a>
    <a href="admin_backup.php" class="settings-card">
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
