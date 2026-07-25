document.addEventListener('DOMContentLoaded', function () {
    initSearch();
    initReorder();
});

function initSearch() {
    var input = document.getElementById('link-search');
    var grid = document.getElementById('links-grid');
    var emptyMsg = document.getElementById('search-empty');
    if (!input || !grid) {
        return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.link-card'));

    input.addEventListener('input', function () {
        var query = input.value.trim().toLowerCase();
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
