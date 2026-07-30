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
            ? 'Statistik klik diaktifkan. Klik baru mulai dicatat sejak sekarang.'
            : 'Statistik klik dinonaktifkan. Tautan kembali dibuka langsung tanpa dicatat.');
        redirect('stats.php');
    } elseif ($action === 'reset') {
        reset_click_stats();
        set_flash('success', 'Semua data statistik klik berhasil dihapus.');
        redirect('stats.php');
    } else {
        $errors[] = 'Aksi tidak dikenali.';
    }
}

$enabled = click_tracking_enabled();
$links = get_links_by_clicks();
$totalClicks = array_sum(array_column($links, 'click_count'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>⚙️ Pengaturan</h1>
</div>

<?php render_settings_tabs('statistik'); ?>

<div class="page-head">
    <h2 style="margin:0;">📊 Statistik Klik</h2>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-box">
    <h2>Aktifkan Statistik Klik</h2>
    <p>
        Fitur ini <strong>opsional</strong> dan nonaktif secara default. Saat aktif, tautan
        di beranda dibuka lewat redirect internal (<code>go.php</code>) yang hanya mencatat
        <strong>jumlah klik</strong> dan <strong>waktu klik terakhir</strong> per tautan —
        tidak ada alamat IP, user-agent, referrer, atau identitas pengunjung apa pun yang
        disimpan. Saat nonaktif, tautan dibuka langsung seperti biasa tanpa request tambahan
        sama sekali.
    </p>
    <form method="post" action="stats.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <label style="flex-direction: row; align-items: center; gap: 8px;">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            Catat statistik klik per tautan
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>

<div class="form-box">
    <h2>Data Tersimpan</h2>
    <p>
        Total klik tercatat: <strong><?= (int) $totalClicks ?></strong>
        <?php if (!$enabled && $totalClicks > 0): ?>
            (statistik sedang nonaktif — angka di atas adalah data lama sebelum dinonaktifkan)
        <?php endif; ?>
    </p>
    <form method="post" action="stats.php" onsubmit="return confirm('Hapus semua data statistik klik (jumlah klik & waktu klik terakhir di semua tautan)? Tindakan ini tidak bisa dibatalkan.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn btn-small btn-danger">🗑️ Reset Statistik</button>
    </form>
</div>

<?php if (empty($links)): ?>
    <p class="empty-state">Belum ada tautan.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Tautan</th>
                <th>Visibilitas</th>
                <th>Klik</th>
                <th>Klik Terakhir</th>
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
                        <?= $link['visibility'] === 'private' ? '🔒 Privat' : '🌐 Publik' ?>
                    </td>
                    <td><?= (int) $link['click_count'] ?></td>
                    <td><?= $link['last_clicked_at'] ? e($link['last_clicked_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
