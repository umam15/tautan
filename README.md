# Tautan

Aplikasi manajemen bookmark/tautan sederhana berbasis PHP + PDO SQLite.

## Fitur

- Data disimpan di SQLite lewat PDO (`data/bookmarks.sqlite`, dibuat otomatis).
- **Setup awal otomatis**: instalasi baru diarahkan ke halaman Setup untuk membuat akun admin pertama — tidak ada kredensial default.
- **Admin**: kelola user (tambah/edit/hapus, tidak bisa hapus diri sendiri/admin terakhir), backup & restore database, ubah teks footer.
- **User login**: buat/sunting/hapus link, atur urutan lewat drag-and-drop.
- **Tag & filter**: multi-tag per link, filter beranda berdasarkan tag dan/atau pencarian teks (`?tag=...`, `?q=...`).
- **Ekspor** daftar link (JSON/CSV) dan **impor** massal dari file HTML bookmark browser (Chrome/Firefox/Edge/Safari).
- **Installable app** (PWA): manifest + service worker minimal (hanya cache aset statis, bukan halaman PHP).
- **Dark mode**, **multi-bahasa (ID/EN)**, dan mode **tamu** (link publik tetap terlihat tanpa login).
- **Cache favicon lokal** per domain di `data/favicons/` (tidak hotlink ke layanan luar).
- **Statistik klik per link** (opsional, off by default): hanya mencatat jumlah & waktu klik, tanpa IP/user-agent.
- Proteksi dasar: password di-hash, prepared statement, CSRF, output di-escape.

## Struktur Folder

```
tautan/
├── config.php        # Koneksi PDO SQLite + skema tabel otomatis
├── includes/          # Auth, helper, lang (id.php, en.php)
├── admin/             # Halaman Pengaturan (khusus admin): users, backup, appearance, stats
├── links/             # Aksi link (khusus user login): form, import, delete, export, reorder
├── index.php, login.php, logout.php, setup.php
├── switch_lang.php, favicon.php, go.php
├── manifest.json, sw.js   # PWA
├── assets/            # CSS, JS (SortableJS via CDN), ikon
└── data/              # bookmarks.sqlite (auto), backups/, favicons/ — jangan di-commit
```

## Kebutuhan Sistem

- **PHP 8.0+** dengan ekstensi: `pdo`, `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`
- Web server: Apache (`mod_rewrite`/`.htaccess` aktif) atau Nginx
- Folder `data/` harus bisa ditulis oleh user web server

Alternatif: gunakan **Docker** (lihat di bawah) supaya tidak perlu setup PHP/SQLite manual.

## Instalasi

1. Salin folder `tautan/` ke server PHP yang memenuhi kebutuhan sistem di atas.
2. Pastikan `data/` bisa ditulis: `chmod 775 data`.
3. Akses `index.php` — karena belum ada user, otomatis diarahkan ke halaman **Setup**.

## Menjalankan dengan Docker

```bash
docker compose up -d --build
```

Buka `http://localhost:8080`. Data disimpan di volume Docker `tautan_data`, tetap ada walau container dihapus/dibuat ulang.

## Deploy ke Shared Hosting (non-Docker)

Tidak butuh database server terpisah, cocok untuk hosting yang mendukung PHP + `pdo_sqlite` (default di kebanyakan cPanel/DirectAdmin modern).

1. **Aktifkan PHP 8.0+** dan pastikan ekstensi `pdo`, `pdo_sqlite` (atau `sqlite3`), `mbstring` dicentang di **Select PHP Version**.
2. **Upload** isi folder `tautan/` ke `public_html/` (zip lalu extract via File Manager lebih cepat daripada FTP satu-satu).
3. **Set permission** `data/`: `chmod -R 775 data` (biasanya 755 sudah cukup di shared hosting).
4. **Proteksi `data/`**: `data/.htaccess` (`Require all denied`) sudah memblokir akses langsung — cek dengan membuka `https://domainanda.com/data/bookmarks.sqlite`, harus muncul 403.
5. Akses domain untuk menjalankan **Setup**.

**Masalah umum**: 500 error → cek ekstensi `pdo_sqlite`/`mbstring`; *"readonly database"* → cek permission `data/` & kuota disk; `.htaccess` tidak berpengaruh → pastikan `AllowOverride All` aktif di hosting.

## Backup & Restore

Menu ⚙️ Pengaturan → Backup/Restore (admin):
- **Unduh Backup** — snapshot `.sqlite` (via `VACUUM INTO`).
- **Pulihkan dari Backup** — upload `.sqlite` (divalidasi dulu); database aktif otomatis disalin ke `data/backups/before-restore-<tanggal>.sqlite` sebelum ditimpa.

⚠️ Restore menimpa seluruh data.

## Catatan Keamanan

- Tidak ada kredensial default — akun admin pertama dibuat sendiri lewat Setup.
- `data/` diblokir lewat `.htaccess` (Apache). Di Nginx, tambahkan: `location /data/ { deny all; }`.
- Tidak ada pendaftaran akun terbuka — admin membuat user baru lewat Kelola User.
