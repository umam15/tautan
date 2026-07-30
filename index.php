<?php
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$activeTag = trim($_GET['tag'] ?? '');
$links = get_links($q, $activeTag);
$loggedIn = is_logged_in();
$allTags = get_all_tags();
// Statistik klik nonaktif secara default (opt-in lewat Pengaturan → Statistik).
// Kalau aktif, tautan dibuka lewat go.php (mencatat klik lalu redirect);
// kalau nonaktif, tautan dibuka langsung seperti biasa — tanpa request
// tambahan apa pun yang bisa dipakai untuk mencatat kunjungan.
$clickTrackingOn = click_tracking_enabled();

/**
 * Bangun URL index.php dengan kombinasi ?q= dan ?tag= tertentu, dipakai untuk
 * link chip filter tag & supaya pencarian teks tetap "menempel" ke tag yang
 * lagi aktif (dan sebaliknya).
 */
function tag_filter_url(string $tag, string $q): string
{
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($tag !== '') {
        $params['tag'] = $tag;
    }
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

/**
 * URL unduh ekspor (links/export.php) sesuai format, ikut membawa filter
 * pencarian/tag yang sedang aktif supaya yang diekspor adalah daftar yang
 * sedang ditampilkan.
 */
function export_url(string $format, string $q, string $tag): string
{
    $params = ['format' => $format];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($tag !== '') {
        $params['tag'] = $tag;
    }
    return 'links/export.php?' . http_build_query($params);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Daftar Tautan</h1>
    <?php if ($loggedIn): ?>
        <div class="page-head-actions">
            <a href="links/import.php" class="btn">⬆️ Impor Bookmark</a>
            <details class="dropdown">
                <summary class="btn">⬇️ Ekspor</summary>
                <div class="dropdown-menu">
                    <a href="<?= e(export_url('json', $q, $activeTag)) ?>">Ekspor JSON</a>
                    <a href="<?= e(export_url('csv', $q, $activeTag)) ?>">Ekspor CSV</a>
                    <a href="<?= e(export_url('html', $q, $activeTag)) ?>">Ekspor Bookmark</a>
                </div>
            </details>
            <a href="links/form.php" class="btn btn-primary">+ Tambah Tautan</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($allTags)): ?>
    <div class="tag-filter-bar">
        <a href="<?= e(tag_filter_url('', $q)) ?>" class="tab <?= $activeTag === '' ? 'tab-active' : '' ?>">Semua</a>
        <?php foreach ($allTags as $t): ?>
            <a href="<?= e(tag_filter_url($t['tag'], $q)) ?>"
               class="tab <?= mb_strtolower($activeTag) === mb_strtolower($t['tag']) ? 'tab-active' : '' ?>">
                🏷️ <?= e($t['tag']) ?> <span class="tag-count"><?= (int) $t['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($links) || $q !== '' || $activeTag !== ''): ?>
    <form method="get" action="index.php" class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" id="link-search" name="q" placeholder="Cari tautan..." autocomplete="off" value="<?= e($q) ?>">
        <?php if ($activeTag !== ''): ?>
            <input type="hidden" name="tag" value="<?= e($activeTag) ?>">
        <?php endif; ?>
        <kbd class="search-kbd">/</kbd>
    </form>
<?php endif; ?>

<?php if (empty($links) && $q === '' && $activeTag === ''): ?>
    <p class="empty-state">Belum ada tautan untuk ditampilkan.</p>
<?php elseif (empty($links) && $activeTag !== ''): ?>
    <p class="empty-state">
        Tidak ada tautan dengan tag "<?= e($activeTag) ?>"<?= $q !== '' ? ' yang cocok dengan "' . e($q) . '"' : '' ?>.
        <a href="<?= e(tag_filter_url('', '')) ?>">Hapus filter</a>
    </p>
<?php elseif (empty($links)): ?>
    <p class="empty-state">
        Tidak ada tautan yang cocok dengan "<?= e($q) ?>".
        <a href="index.php">Hapus pencarian</a>
    </p>
<?php else: ?>
    <p id="search-empty" class="empty-state" hidden>Tidak ada tautan yang cocok.</p>
    <div id="links-grid" class="links-grid" data-reorder-url="links/reorder.php" data-can-edit="<?= $loggedIn ? '1' : '0' ?>">
        <?php foreach ($links as $link): ?>
            <?php $linkTags = tags_from_storage($link['tags'] ?? ''); ?>
            <div class="link-card <?= $link['visibility'] === 'private' ? 'link-private' : '' ?>"
                 data-id="<?= (int) $link['id'] ?>"
                 data-search="<?= e(mb_strtolower($link['title'] . ' ' . $link['description'] . ' ' . $link['url'] . ' ' . implode(' ', $linkTags))) ?>">
                <?php if ($loggedIn): ?>
                    <span class="drag-handle" title="Geser untuk mengubah urutan">⠿</span>
                <?php endif; ?>

                <div class="link-body">
                    <a class="link-open" href="<?= e($clickTrackingOn ? 'go.php?id=' . (int) $link['id'] : $link['url']) ?>" target="_blank" rel="noopener noreferrer">
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
                                <?php if ($clickTrackingOn && $loggedIn): ?>
                                    <span class="click-badge" title="Jumlah klik (hanya terlihat oleh user login)">👆 <?= (int) ($link['click_count'] ?? 0) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($link['description'])): ?>
                                <span class="link-desc"><?= e($link['description']) ?></span>
                            <?php endif; ?>
                            <span class="link-url"><?= e($link['url']) ?></span>
                        </span>
                    </a>

                    <?php if (!empty($linkTags)): ?>
                        <span class="tag-badges">
                            <?php foreach ($linkTags as $t): ?>
                                <a href="<?= e(tag_filter_url($t, '')) ?>" class="tag-badge">🏷️ <?= e($t) ?></a>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($loggedIn): ?>
                    <div class="link-actions">
                        <a href="links/form.php?id=<?= (int) $link['id'] ?>" class="btn btn-small">Edit</a>
                        <form method="post" action="links/delete.php" onsubmit="return confirm('Hapus tautan ini?');">
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
