<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$link = null;

if ($editing) {
    $link = get_link($id);
    if (!$link) {
        set_flash('error', t('generic_not_found'));
        redirect('../index.php');
    }
}

$errors = [];
$values = [
    'title'       => $link['title'] ?? '',
    'url'         => $link['url'] ?? '',
    'description' => $link['description'] ?? '',
    'icon'        => $link['icon'] ?? '',
    'visibility'  => $link['visibility'] ?? 'public',
    'tags'        => isset($link['tags']) ? implode(', ', tags_from_storage($link['tags'])) : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['title']       = trim($_POST['title'] ?? '');
    $values['url']         = normalize_url($_POST['url'] ?? '');
    $values['description'] = trim($_POST['description'] ?? '');
    $values['icon']        = trim($_POST['icon'] ?? '');
    $values['visibility']  = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $values['tags']        = trim($_POST['tags'] ?? '');
    $normalizedTags         = normalize_tags($values['tags']);

    if ($values['title'] === '') {
        $errors[] = t('link_form_error_title');
    }
    if ($values['url'] === '' || !filter_var($values['url'], FILTER_VALIDATE_URL)) {
        $errors[] = t('link_form_error_url');
    }
    if ($values['icon'] !== '' && !filter_var($values['icon'], FILTER_VALIDATE_URL)) {
        $errors[] = t('link_form_error_icon');
    }

    if (empty($errors)) {
        $tagsForStorage = tags_to_storage($normalizedTags);
        if ($editing) {
            update_link($id, $values['title'], $values['url'], $values['description'], $values['icon'], $values['visibility'], $tagsForStorage);
            set_flash('success', t('link_form_updated'));
        } else {
            $user = current_user();
            create_link($values['title'], $values['url'], $values['description'], $values['icon'], $values['visibility'], $user['id'], $tagsForStorage);
            set_flash('success', t('link_form_created'));
        }
        redirect('../index.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-box">
    <h1><?= $editing ? e(t('link_form_title_edit')) : e(t('link_form_title_add')) ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="form.php<?= $editing ? '?id=' . $id : '' ?>" class="form">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <label>
            <?= e(t('link_form_label_title')) ?>
            <input type="text" name="title" required value="<?= e($values['title']) ?>" placeholder="<?= e(t('link_form_title_ph')) ?>">
        </label>

        <label>
            <?= e(t('link_form_label_url')) ?>
            <input type="text" name="url" required value="<?= e($values['url']) ?>" placeholder="<?= e(t('link_form_url_ph')) ?>">
        </label>

        <label>
            <?= e(t('link_form_label_desc')) ?>
            <textarea name="description" rows="3" placeholder="<?= e(t('link_form_desc_ph')) ?>"><?= e($values['description']) ?></textarea>
        </label>

        <label>
            <?= e(t('link_form_label_icon')) ?>
            <input type="text" name="icon" value="<?= e($values['icon']) ?>" placeholder="<?= e(t('link_form_icon_ph')) ?>">
            <small class="field-hint"><?= e(t('link_form_icon_hint')) ?></small>
        </label>

        <label>
            <?= e(t('link_form_label_tags')) ?>
            <input type="text" name="tags" value="<?= e($values['tags']) ?>" placeholder="<?= e(t('link_form_tags_ph')) ?>">
            <small class="field-hint"><?= e(t('link_form_tags_hint')) ?></small>
        </label>

        <label class="radio-group">
            <?= e(t('link_form_visibility')) ?>
            <span class="radio-options">
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="public" <?= $values['visibility'] === 'public' ? 'checked' : '' ?>>
                    <?= e(t('link_form_vis_public')) ?>
                </label>
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="private" <?= $values['visibility'] === 'private' ? 'checked' : '' ?>>
                    <?= e(t('link_form_vis_private')) ?>
                </label>
            </span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? e(t('link_form_submit_edit')) : e(t('link_form_submit_add')) ?></button>
            <a href="../index.php" class="btn"><?= e(t('btn_cancel')) ?></a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
