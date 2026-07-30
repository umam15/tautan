# Changelog

## v0.2.15

- **Statistik klik per link (opsional, hormati privasi)**: menu baru ⚙️ Pengaturan → 📊 Statistik (khusus admin) untuk mengaktifkan/menonaktifkan pencatatan klik per tautan. Nonaktif secara default. Saat aktif, tautan di beranda dibuka lewat endpoint redirect baru `go.php?id=...` yang mencatat **hanya** jumlah klik (`click_count`) dan waktu klik terakhir (`last_clicked_at`) per tautan — tidak ada IP address, user-agent, referrer, atau data pengunjung lain yang disimpan. Saat nonaktif, tautan tetap dibuka langsung seperti sebelumnya (tanpa request tambahan apa pun). Halaman Statistik menampilkan tabel semua tautan diurutkan dari yang paling banyak diklik, plus tombol "Reset Statistik" untuk menghapus seluruh data kapan saja. Badge jumlah klik (👆) ditampilkan di beranda, hanya untuk user yang sudah login, dan hanya kalau fitur ini aktif. Instalasi lama otomatis dapat kolom `click_count`/`last_clicked_at` lewat migrasi `ALTER TABLE` sekali jalan di `config.php`, sama seperti migrasi `tags` sebelumnya.

## v0.2.14

- Perbaikan: impor bookmark (`links/import.php`) sekarang ikut membawa tag lewat atribut `TAGS=` pada file HTML — sebelumnya `parse_bookmark_html()` cuma membaca `href`, judul, dan `ICON=`, jadi tag apa pun di file bookmark selalu hilang saat diimpor (termasuk saat re-impor file hasil "Ekspor Bookmark" dari Tautan sendiri, yang sejak v0.2.11 memang menulis atribut ini). Firefox & ekspor Tautan menulis `TAGS=`; Chrome/Edge/Safari umumnya tidak, jadi link dari file itu tetap diimpor tanpa tag seperti biasa.

## v0.2.13

- Dark mode / toggle tema: tombol 🌙/☀️ baru di topbar (semua halaman, termasuk Login & Setup) untuk beralih antara tema gelap (default) dan terang. Pilihan disimpan di `localStorage` per browser, diterapkan lewat atribut `data-theme` di `<html>`, dan dibaca ulang lewat skrip kecil di `<head>` sebelum CSS dirender supaya tidak ada "kedip" ke tema default saat memuat ulang halaman. Semua warna di `assets/style.css` sudah lewat CSS custom property, jadi tema terang otomatis konsisten di seluruh halaman (kartu tautan, tombol, badge, tabel, dsb) tanpa perlu stylesheet terpisah.

## v0.2.12

- Bisa di-install sebagai aplikasi di browser (PWA): tambah `manifest.json` (nama, ikon, `display: standalone`, warna tema) dan `sw.js` (service worker minimal), dirujuk lewat `<link rel="manifest">` & registrasi di `includes/header.php` sehingga berlaku di semua halaman. Ikon app baru di `assets/icons/` (berbagai ukuran + varian maskable) dipakai sebagai favicon situs sekaligus ikon PWA/`apple-touch-icon`. Service worker sengaja cuma meng-cache aset statis (CSS/JS/ikon) lewat strategi stale-while-revalidate — semua halaman PHP (termasuk yang berisi link privat/status login) tetap selalu diminta langsung dari server, tidak pernah lewat cache, supaya tidak ada risiko konten antar-sesi tertukar.

## v0.2.11

- Tambah format Bookmark Browser (HTML) ke `links/export.php`, di samping JSON/CSV yang sudah ada — dipilih lewat menu "⬇️ Ekspor" di beranda. Memakai "Netscape Bookmark File Format" yang sama dengan hasil ekspor Chrome/Firefox/Edge/Safari (format yang sama juga sudah dibaca `parse_bookmark_html()` di `links/import.php`), jadi file ini bisa diimpor balik ke Tautan atau langsung ke bookmark manager browser mana pun. Tag disertakan lewat atribut `TAGS=`, dan favicon yang tersimpan sebagai data-URI disertakan lewat atribut `ICON=` kalau ada. Filter pencarian (`?q=`) dan tag (`?tag=`) yang aktif di beranda ikut terbawa, sama seperti format JSON/CSV.

## v0.2.10

- Ekspor daftar link ke JSON atau CSV lewat halaman baru `links/export.php`, diakses lewat tombol "⬇️ Ekspor" di beranda. Terpisah dari backup `.sqlite` penuh (`admin/backup.php`, khusus admin, isinya seluruh database termasuk user & hash password) — ekspor ini cuma daftar link (judul, URL, deskripsi, tag, visibilitas, tanggal dibuat) dalam format portabel, bisa diakses semua user yang login. Kalau lagi memakai filter pencarian (`?q=`) atau tag (`?tag=`) di beranda, tombol ekspor otomatis membawa filter yang sama supaya yang diunduh adalah daftar yang sedang ditampilkan.

## v0.2.9

- Tag/kategori link: kolom baru `tags` di tabel `links` (dipisah koma, mis. "kerja, belajar"), diisi lewat field baru di `links/form.php`. Instalasi lama otomatis dapat kolom ini lewat migrasi `ALTER TABLE` sekali jalan di `config.php`, tanpa perlu setup ulang.
- Beranda (`index.php`) sekarang menampilkan bar filter berisi semua tag yang ada (dengan jumlah link per tag) — klik salah satu untuk memfilter daftar lewat `index.php?tag=...`, mirip pola pencarian `?q=...` yang sudah ada: bisa di-bookmark/di-share dan tetap benar walau JavaScript mati. Filter tag & pencarian teks bisa dikombinasikan.
- Setiap kartu link menampilkan badge tag-nya, masing-masing bisa diklik untuk langsung memfilter ke tag tersebut.

## v0.2.8

- Impor tautan dari file HTML bookmark hasil ekspor browser (Chrome, Firefox, Edge, Safari — semuanya pakai format yang sama, "Netscape Bookmark File Format"): halaman baru `links/import.php`, diakses lewat tombol "⬆️ Impor Bookmark" di beranda. Struktur folder pada file bookmark diabaikan (didatakan sebagai satu daftar link), favicon yang disematkan browser saat ekspor (data-URI base64) dipakai langsung, URL yang sudah ada di daftar bisa dilewati otomatis, dan visibilitas (publik/privat) untuk seluruh tautan yang diimpor bisa dipilih sebelum diproses.

## v0.2.7

- Cache favicon lokal: ikon domain (fallback saat link tidak diisi `icon` sendiri) sekarang diunduh sekali per domain lewat endpoint baru `favicon.php` dan disimpan di `data/favicons/`, bukan hotlink langsung ke layanan favicon Google pada setiap render halaman. Domain yang gagal diambil ikonnya juga di-negative-cache (1 hari) supaya tidak diulang-ulang tiap request, dan jatuh ke ikon default lokal (`assets/default-favicon.svg`).

## v0.2.6

- Pencarian link sekarang lewat query string `index.php?q=...`, difilter langsung di server (`get_links()`), bukan cuma di client lewat JS. Hasilnya bisa di-bookmark & di-share, dan tetap benar walau JavaScript mati.
- URL disinkronkan otomatis (`history.replaceState`, tanpa reload) tiap kali mengetik di kolom pencarian, jadi URL yang di-copy dari address bar selalu mencerminkan pencarian yang sedang diketik.

## v0.2.5

- Optimalkan kode: gabungkan query `get_links()` (tamu vs login) jadi satu query terparameter, hilangkan round-trip `next_sort_order()` terpisah saat tambah link, gabungkan 3 query hitung di halaman Pengaturan jadi satu, tambah index database (`sort_order`, `visibility`) untuk mempercepat query daftar link.
- Tambahkan SQLite ke bagian **Kebutuhan Sistem** di README (ekstensi `pdo_sqlite` & `sqlite3`).
- Tambahkan `TODO.md` untuk catatan rencana pengembangan & masukkan ke `.gitignore`.
- Tambahkan proyek Docker: `Dockerfile`, `docker-compose.yml`, `.dockerignore`, plus panduan menjalankan lewat Docker di README.

