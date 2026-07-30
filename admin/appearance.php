<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_admin();

$errors = [];
$footerText = footer_text();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $footerText = trim($_POST['footer_text'] ?? '');

    if ($footerText === '') {
        $errors[] = 'Teks footer tidak boleh kosong.';
    } elseif (mb_strlen($footerText) > 150) {
        $errors[] = 'Teks footer maksimal 150 karakter.';
    }

    if (empty($errors)) {
        set_setting('footer_text', $footerText);
        set_flash('success', 'Teks footer berhasil disimpan.');
        redirect('appearance.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>⚙️ Pengaturan</h1>
</div>

<?php render_settings_tabs('tampilan'); ?>

<div class="page-head">
    <h2 style="margin:0;">Tampilan</h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2>Teks Footer</h2>
    <p>Teks ini tampil di bagian bawah setiap halaman, menggantikan nama aplikasi default.</p>
    <form method="post" action="appearance.php" class="form">
        <?= csrf_field() ?>
        <label>
            Teks Footer
            <input type="text" name="footer_text" required maxlength="150" value="<?= e($footerText) ?>">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
