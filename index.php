<?php
require_once __DIR__ . '/includes/functions.php';

$links = get_links();
$loggedIn = is_logged_in();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Daftar Tautan</h1>
    <?php if ($loggedIn): ?>
        <a href="link_form.php" class="btn btn-primary">+ Tambah Tautan</a>
    <?php endif; ?>
</div>

<?php if (!empty($links)): ?>
    <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" id="link-search" placeholder="Cari tautan..." autocomplete="off">
        <kbd class="search-kbd">/</kbd>
    </div>
<?php endif; ?>

<?php if (empty($links)): ?>
    <p class="empty-state">Belum ada tautan untuk ditampilkan.</p>
<?php else: ?>
    <p id="search-empty" class="empty-state" hidden>Tidak ada tautan yang cocok.</p>
    <div id="links-grid" class="links-grid" data-reorder-url="link_reorder.php" data-can-edit="<?= $loggedIn ? '1' : '0' ?>">
        <?php foreach ($links as $link): ?>
            <div class="link-card <?= $link['visibility'] === 'private' ? 'link-private' : '' ?>"
                 data-id="<?= (int) $link['id'] ?>"
                 data-search="<?= e(mb_strtolower($link['title'] . ' ' . $link['description'] . ' ' . $link['url'])) ?>">
                <?php if ($loggedIn): ?>
                    <span class="drag-handle" title="Geser untuk mengubah urutan">⠿</span>
                <?php endif; ?>

                <a class="link-open" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="link-icon">
                        <?php if (!empty($link['icon'])): ?>
                            <img src="<?= e($link['icon']) ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                        <?php else: ?>
                            <img src="<?= e(favicon_for($link['url'])) ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                        <?php endif; ?>
                    </span>
                    <span class="link-info">
                        <span class="link-title">
                            <?= e($link['title']) ?>
                            <?php if ($link['visibility'] === 'private'): ?>
                                <span class="lock" title="Hanya terlihat oleh user login">🔒</span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($link['description'])): ?>
                            <span class="link-desc"><?= e($link['description']) ?></span>
                        <?php endif; ?>
                        <span class="link-url"><?= e($link['url']) ?></span>
                    </span>
                </a>

                <?php if ($loggedIn): ?>
                    <div class="link-actions">
                        <a href="link_form.php?id=<?= (int) $link['id'] ?>" class="btn btn-small">Edit</a>
                        <form method="post" action="link_delete.php" onsubmit="return confirm('Hapus tautan ini?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger">Hapus</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($loggedIn): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<?php endif; ?>
<script src="assets/app.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
