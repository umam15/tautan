document.addEventListener('DOMContentLoaded', function () {
    initSearch();
    initReorder();
    initDropdowns();
});

function initSearch() {
    var input = document.getElementById('link-search');
    var grid = document.getElementById('links-grid');
    var emptyMsg = document.getElementById('search-empty');
    if (!input) {
        return;
    }

    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.link-card')) : [];

    // Sinkronkan ?q= di address bar tiap kali user mengetik, tanpa reload
    // (history.replaceState), supaya URL yang di-copy/share/bookmark selalu
    // mencerminkan pencarian yang sedang diketik — bukan cuma setelah tombol
    // Enter ditekan. Saat URL itu dibuka lagi (fresh load), index.php sudah
    // memfilter hasil lewat query string ?q= di server (lihat get_links()).
    function syncUrl(value) {
        var url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('q', value);
        } else {
            url.searchParams.delete('q');
        }
        window.history.replaceState(null, '', url.toString());
    }

    input.addEventListener('input', function () {
        var query = input.value.trim().toLowerCase();
        syncUrl(input.value.trim());

        // Filter tambahan di client (kalau grid ada di halaman ini) supaya
        // hasil terasa instan sebelum form di-submit / halaman reload.
        if (!cards.length) {
            return;
        }
        var visibleCount = 0;
        cards.forEach(function (card) {
            var haystack = card.dataset.search || '';
            var match = query === '' || haystack.indexOf(query) !== -1;
            card.hidden = !match;
            if (match) visibleCount++;
        });
        if (emptyMsg) {
            emptyMsg.hidden = visibleCount !== 0;
        }
    });

    // Shortcut "/" untuk fokus ke kolom pencarian
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/') return;

        var active = document.activeElement;
        var isTyping = active && (
            active.tagName === 'INPUT' ||
            active.tagName === 'TEXTAREA' ||
            active.isContentEditable
        );
        if (isTyping) return;

        e.preventDefault();
        input.focus();
        input.select();
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            input.dispatchEvent(new Event('input'));
            input.blur();
        }
    });
}

/**
 * Tombol "⬇️ Ekspor" di beranda pakai <details class="dropdown"> (native,
 * tetap bisa dibuka lewat klik tanpa JS). Enhancement ini cuma menutupnya
 * otomatis kalau klik di luar area dropdown atau tekan Escape — supaya tidak
 * "nyangkut" terbuka.
 */
function initDropdowns() {
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.dropdown[open]').forEach(function (d) {
            if (!d.contains(e.target)) {
                d.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('details.dropdown[open]').forEach(function (d) {
            d.removeAttribute('open');
        });
    });
}

function initReorder() {
    var grid = document.getElementById('links-grid');
    if (!grid || grid.dataset.canEdit !== '1' || typeof Sortable === 'undefined') {
        return;
    }

    var reorderUrl = grid.dataset.reorderUrl;
    var csrfInput = document.querySelector('input[name="csrf_token"]');
    var csrfToken = csrfInput ? csrfInput.value : '';

    Sortable.create(grid, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            var order = Array.prototype.map.call(
                grid.querySelectorAll('.link-card'),
                function (el) { return el.dataset.id; }
            );

            fetch(reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order, csrf_token: csrfToken })
            }).catch(function (err) {
                console.error('Gagal menyimpan urutan link:', err);
            });
        }
    });
}
