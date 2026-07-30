<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        set_click_tracking_enabled(!empty($_POST['enabled']));
        set_flash('success', click_tracking_enabled()
            ? t('stats_toggle_on')
            : t('stats_toggle_off'));
        redirect('stats.php');
    } elseif ($action === 'reset') {
        reset_click_stats();
        set_flash('success', t('stats_reset_success'));
        redirect('stats.php');
    } else {
        $errors[] = t('stats_action_unknown');
    }
}

$enabled = click_tracking_enabled();
$links = get_links_by_clicks();
$totalClicks = array_sum(array_column($links, 'click_count'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1><?= e(t('settings_title')) ?></h1>
</div>

<?php render_settings_tabs('statistik'); ?>

<div class="page-head">
    <h2 style="margin:0;"><?= e(t('stats_page_title')) ?></h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2><?= e(t('stats_enable_title')) ?></h2>
    <p>
        <?= t('stats_enable_desc') ?>
    </p>
    <form method="post" action="stats.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <label style="flex-direction: row; align-items: center; gap: 8px;">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <?= e(t('stats_checkbox_label')) ?>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(t('btn_save')) ?></button>
        </div>
    </form>
</div>

<div class="form-box">
    <h2><?= e(t('stats_data_title')) ?></h2>
    <p>
        <?= e(t('stats_total_clicks')) ?> <strong><?= (int) $totalClicks ?></strong>
        <?php if (!$enabled && $totalClicks > 0): ?>
            <?= e(t('stats_total_clicks_note')) ?>
        <?php endif; ?>
    </p>
    <form method="post" action="stats.php" onsubmit="return confirm('<?= e(t('stats_reset_confirm')) ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn btn-small btn-danger"><?= e(t('stats_reset_btn')) ?></button>
    </form>
</div>

<?php if (empty($links)): ?>
    <p class="empty-state"><?= e(t('stats_empty')) ?></p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th><?= e(t('stats_col_link')) ?></th>
                <th><?= e(t('stats_col_visibility')) ?></th>
                <th><?= e(t('stats_col_clicks')) ?></th>
                <th><?= e(t('stats_col_last_click')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($links as $link): ?>
                <tr>
                    <td>
                        <strong><?= e($link['title']) ?></strong><br>
                        <span class="link-url"><?= e($link['url']) ?></span>
                    </td>
                    <td>
                        <?= $link['visibility'] === 'private' ? e(t('stats_visibility_private')) : e(t('stats_visibility_public')) ?>
                    </td>
                    <td><?= (int) $link['click_count'] ?></td>
                    <td><?= $link['last_clicked_at'] ? e($link['last_clicked_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
