<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$link = null;

if ($editing) {
    $link = get_link($id);
    if (!$link) {
        set_flash('error', 'Tautan tidak ditemukan.');
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
        $errors[] = 'Judul wajib diisi.';
    }
    if ($values['url'] === '' || !filter_var($values['url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL tidak valid.';
    }
    if ($values['icon'] !== '' && !preg_match('#^data:image/(?:png|jpeg|gif|webp|svg\\+xml);base64,[A-Za-z0-9+/=]+$#i', $values['icon'])) {
        $errors[] = 'Ikon harus berupa data:image lokal (base64), bukan URL eksternal.';
    }

    if (empty($errors)) {
        $tagsForStorage = tags_to_storage($normalizedTags);
        if ($editing) {
            update_link($id, $values['title'], $values['url'], $values['description'], $values['icon'], $values['visibility'], $tagsForStorage);
            set_flash('success', 'Tautan berhasil diperbarui.');
        } else {
            $user = current_user();
            create_link($values['title'], $values['url'], $values['description'], $values['icon'], $values['visibility'], $user['id'], $tagsForStorage);
            set_flash('success', 'Tautan berhasil ditambahkan.');
        }
        redirect('../index.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-box">
    <h1><?= $editing ? 'Edit Tautan' : 'Tambah Tautan' ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="form.php<?= $editing ? '?id=' . $id : '' ?>" class="form">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <label>
            Judul
            <input type="text" name="title" required value="<?= e($values['title']) ?>" placeholder="Contoh: Dokumentasi PHP">
        </label>

        <label>
            URL
            <input type="text" name="url" required value="<?= e($values['url']) ?>" placeholder="https://contoh.com">
        </label>

        <label>
            Deskripsi (opsional)
            <textarea name="description" rows="3" placeholder="Keterangan singkat tentang link ini"><?= e($values['description']) ?></textarea>
        </label>

        <label>
            Ikon lokal (opsional)
            <input type="text" name="icon" value="<?= e($values['icon']) ?>" placeholder="data:image/png;base64,...">
            <small class="field-hint">Kosongkan untuk memakai ikon default lokal.</small>
        </label>

        <label>
            Tag/Kategori (opsional)
            <input type="text" name="tags" value="<?= e($values['tags']) ?>" placeholder="kerja, belajar, referensi">
            <small class="field-hint">Pisahkan dengan koma. Dipakai untuk filter tampilan di beranda.</small>
        </label>

        <label class="radio-group">
            Visibilitas
            <span class="radio-options">
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="public" <?= $values['visibility'] === 'public' ? 'checked' : '' ?>>
                    Publik (semua orang bisa lihat)
                </label>
                <label class="radio-inline">
                    <input type="radio" name="visibility" value="private" <?= $values['visibility'] === 'private' ? 'checked' : '' ?>>
                    🔒 Hanya user login
                </label>
            </span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Tautan' ?></button>
            <a href="../index.php" class="btn">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
