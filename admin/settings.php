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
    <h1><?= e(t('settings_title')) ?></h1>
    <p class="page-head-sub"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></p>
</div>

<?php render_settings_tabs('ringkasan'); ?>

<div class="settings-grid">
    <a href="users.php" class="settings-card">
        <div class="settings-card-icon">👤</div>
        <h2><?= e(t('settings_users_title')) ?></h2>
        <p><?= t('settings_users_desc', [':count' => (string) $userCount, ':admins' => (string) $adminCount]) ?></p>
    </a>
    <a href="backup.php" class="settings-card">
        <div class="settings-card-icon">💾</div>
        <h2><?= t('settings_backup_title') ?></h2>
        <p>
            <?= t('settings_backup_links', [':count' => (string) $linkCount]) ?>
            <?php if ($lastBackupAt): ?>
                <?= t('settings_backup_last', [':date' => e($lastBackupAt)]) ?>
            <?php else: ?>
                <?= t('settings_backup_none') ?>
            <?php endif; ?>
        </p>
    </a>
    <a href="appearance.php" class="settings-card">
        <div class="settings-card-icon">🎨</div>
        <h2><?= e(t('settings_appearance_title')) ?></h2>
        <p><?= e(t('settings_appearance_desc')) ?></p>
    </a>
    <a href="stats.php" class="settings-card">
        <div class="settings-card-icon">📊</div>
        <h2><?= e(t('settings_stats_title')) ?></h2>
        <p>
            <?php if (click_tracking_enabled()): ?>
                <?= t('settings_stats_active', [':count' => (string) $totalClicks]) ?>
            <?php else: ?>
                <?= e(t('settings_stats_inactive')) ?>
            <?php endif; ?>
        </p>
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
