/**
 * Service worker minimal — cuma dipakai supaya Tautan memenuhi syarat
 * "installable" sebagai app di browser (PWA). Sengaja TIDAK meng-cache
 * halaman PHP (index.php, login.php, dst): isinya beda per user/sesi
 * (link privat, status login) jadi cache-first di situ bisa bocor/nyasar
 * antar sesi. Yang di-cache cuma aset statis (CSS, ikon) yang aman sama
 * untuk semua orang.
 */

const CACHE_NAME = 'tautan-static-v1';
const STATIC_ASSETS = [
    'assets/style.css',
    'assets/app.js',
    'assets/default-favicon.svg',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) { return key !== CACHE_NAME; })
                    .map(function (key) { return caches.delete(key); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const url = new URL(event.request.url);
    const isStaticAsset = url.pathname.includes('/assets/');

    if (event.request.method !== 'GET' || !isStaticAsset) {
        // Semua halaman PHP & permintaan non-GET: langsung ke jaringan,
        // tidak lewat cache sama sekali.
        return;
    }

    event.respondWith(
        caches.match(event.request).then(function (cached) {
            const network = fetch(event.request)
                .then(function (response) {
                    if (response && response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(event.request, clone);
                        });
                    }
                    return response;
                })
                .catch(function () {
                    return cached;
                });
            return cached || network;
        })
    );
});
