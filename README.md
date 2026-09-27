# Tautan

Aplikasi manajemen bookmark/tautan sederhana berbasis PHP + PDO SQLite.

## Fitur

- Data disimpan di SQLite (auto-generate di `data/bookmarks.sqlite`), tanpa kredensial default — akun admin pertama dibuat lewat halaman **Setup**.
- **Admin**: kelola user, backup/restore database (`.sqlite`, backup otomatis sebelum restore), ubah teks footer, statistik klik opsional (hanya jumlah & waktu klik, tanpa IP/user-agent).
- **User login**: tambah/edit/hapus link, drag-and-drop urutan, tag/kategori dengan filter & pencarian, ekspor daftar link (JSON/CSV), impor bookmark dari file HTML browser.
- **Tamu**: bisa lihat link publik tanpa login.
- Bisa diinstall sebagai PWA (manifest + service worker), dark mode, cache favicon lokal.
- Tidak memakai webfont/CDN font: UI menggunakan system fonts yang sudah tersedia di perangkat.
- Keamanan dasar: password hash, prepared statement, proteksi CSRF, output escape (XSS-safe).

## Struktur Folder

```
tautan/
├── config.php       # Koneksi PDO SQLite + skema tabel otomatis
├── includes/        # Auth, helper, header/footer bersama
├── admin/           # Halaman Pengaturan (khusus admin)
├── links/           # Aksi terkait link (khusus user login)
├── index.php        # Beranda (daftar link)
├── login.php / logout.php / setup.php
├── favicon.php      # Cache favicon lokal
├── go.php           # Redirect tautan + pencatat klik (jika Statistik aktif)
├── manifest.json / sw.js   # PWA
├── assets/          # CSS, JS, ikon
└── data/            # bookmarks.sqlite (auto), backups/, favicons/ — jangan di-commit
```

## Kebutuhan Sistem

- PHP 8.0+ dengan ekstensi: `pdo`, `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`
- SQLite 3 (via PDO, tanpa server database terpisah)
- Apache (`mod_rewrite`/`.htaccess`) atau Nginx
- Folder `data/` harus bisa ditulis oleh web server

Alternatif: gunakan Docker agar tidak perlu setup manual.

## Instalasi

1. Salin folder `tautan/` ke server PHP.
2. `chmod 775 data`
3. Akses `index.php` di browser → otomatis diarahkan ke halaman **Setup** untuk membuat akun admin pertama.

## Docker

```bash
docker compose up -d --build
```

Buka `http://localhost:8080`. Data tersimpan di volume Docker `tautan_data`.

## Deploy ke Shared Hosting (non-Docker)

1. **Cek ekstensi PHP** — pastikan `pdo`, `pdo_sqlite`, `mbstring` aktif di panel (cPanel: Select PHP Version/MultiPHP Manager).
2. **Upload file** — upload isi folder `tautan/` ke `public_html/` (atau subfolder), paling cepat lewat upload zip lalu extract di File Manager.
3. **Permission `data/`** — set ke 755, naikkan ke 775 jika masih gagal menulis (`chmod -R 775 data`).
4. **Proteksi folder `data/`** — sudah otomatis lewat `data/.htaccess` (Apache). Untuk Nginx tambahkan: `location /data/ { deny all; }`.
5. **Jalankan Setup** — akses domain, akan diarahkan otomatis ke halaman Setup.

**Masalah umum**: 500 error → ekstensi PHP belum aktif; "readonly database" → permission/kuota disk; `open_basedir restriction` → normal selama folder ada di dalam `public_html`; `.htaccess` tidak berpengaruh → pastikan `AllowOverride All` aktif.

## Backup & Restore

Menu **⚙️ Pengaturan → Backup/Restore** (admin): unduh backup `.sqlite` kapan saja, atau pulihkan dari file `.sqlite` (divalidasi dulu, backup otomatis dibuat sebelum restore). ⚠️ Restore menimpa seluruh data.

## Catatan Keamanan

- Tidak ada kredensial default — admin dibuat sendiri lewat Setup.
- Folder `data/` sudah diblokir aksesnya lewat `.htaccess` (Apache); tambahkan rule serupa manual untuk Nginx.
- Tidak ada pendaftaran akun terbuka — user baru dibuat admin lewat Kelola User.
