# Tautan

Aplikasi manajemen bookmark/tautan sederhana berbasis PHP + PDO SQLite.

## Fitur

- **Data user & link disimpan di SQLite** lewat PDO (file otomatis dibuat di `data/bookmarks.sqlite` saat pertama kali diakses).
- **Setup awal otomatis**: saat instalasi baru (belum ada user sama sekali), aplikasi mengarahkan ke halaman **Setup** untuk membuat akun admin pertama dengan username & password pilihan sendiri — tidak ada lagi kredensial default yang sama di setiap instalasi.
- **Halaman Pengaturan** (khusus admin, menu **⚙️ Pengaturan** di navbar) jadi satu tempat untuk mengelola user, serta backup & restore database.
- **Admin** bisa mengelola user: tambah, edit (username/role/password), hapus (tidak bisa menghapus diri sendiri atau admin terakhir).
- **Admin** bisa **backup & restore database**: unduh snapshot database (`.sqlite`) kapan saja, atau pulihkan dari file backup lewat menu **Pengaturan → Backup / Restore**. Sebelum restore, salinan pengaman dari database yang sedang aktif otomatis disimpan ke `data/backups/`.
- **User login** bisa membuat, menyunting, menghapus link, serta **mengatur urutan link** lewat drag-and-drop (disimpan otomatis).
- **Tamu (tanpa login)** tetap bisa melihat dan membuka link yang berstatus **publik**.
- **Link privat**: setiap link punya opsi visibilitas — `Publik` (semua orang bisa lihat) atau `🔒 Hanya user login` (hanya tampil untuk yang sudah login).
- Proteksi dasar: password di-hash (`password_hash`), semua query pakai prepared statement (PDO), proteksi CSRF di setiap form/aksi POST, output di-escape (XSS-safe).

## Struktur Folder

```
tautan/
├── config.php              # Koneksi PDO SQLite + pembuatan skema tabel otomatis
├── includes/
│   ├── auth.php             # Login, sesi, CSRF, helper umum
│   ├── functions.php        # Query untuk links & users
│   ├── header.php / footer.php
├── index.php                 # Daftar link (beranda)
├── login.php / logout.php
├── link_form.php             # Tambah & edit link
├── link_delete.php
├── link_reorder.php          # Endpoint AJAX untuk simpan urutan drag-and-drop
├── setup.php                 # Wizard setup awal — buat akun admin pertama (hanya muncul saat belum ada user)
├── settings.php               # Halaman Pengaturan (khusus admin) — ringkasan & tautan ke Kelola User / Backup
├── admin_users.php           # Daftar user (khusus admin)
├── admin_user_form.php       # Tambah & edit user (khusus admin)
├── admin_user_delete.php
├── admin_backup.php          # Backup & restore database (khusus admin)
├── assets/
│   ├── style.css
│   └── app.js                # Drag-and-drop (pakai SortableJS via CDN)
└── data/
    ├── .htaccess              # Blokir akses langsung ke folder data
    ├── bookmarks.sqlite       # Dibuat otomatis, jangan di-commit ke publik
    └── backups/               # Salinan pengaman otomatis sebelum restore
```

## Instalasi

Ada dua cara menjalankan aplikasi ini: langsung di server PHP, atau lewat Docker.

### Opsi A — Server PHP langsung

1. Salin folder `tautan/` ke server PHP (butuh PHP 8+ dengan ekstensi `pdo_sqlite`).
2. Pastikan folder `data/` bisa ditulis oleh web server:
   ```bash
   chmod 775 data
   ```
3. Akses `index.php` lewat browser. Tabel default akan dibuat otomatis pada request pertama.
4. Karena belum ada user sama sekali, Anda akan otomatis diarahkan ke halaman **Setup** — isi username & password untuk akun admin pertama, lalu Anda langsung masuk (login) sebagai admin tersebut.

### Opsi B — Docker

Cara termudah karena PHP, ekstensi `pdo_sqlite`, dan Apache sudah disiapkan otomatis lewat `Dockerfile`.

**Pakai Docker Compose (disarankan):**

```bash
docker compose up -d --build
```

- Aplikasi bisa diakses di `http://localhost:8080`.
- Database SQLite disimpan di Docker volume `tautan_data` (lihat `docker-compose.yml`), jadi datanya tetap ada meski container di-rebuild atau dihapus.
- Untuk menghentikan: `docker compose down` (volume `tautan_data` tidak ikut terhapus, kecuali ditambah opsi `-v`).

**Atau pakai Docker biasa (tanpa Compose):**

```bash
docker build -t tautan .
docker run -d --name tautan -p 8080:80 -v tautan_data:/var/www/html/data tautan
```

Setelah container jalan, buka `http://localhost:8080`. Karena database masih kosong, Anda akan otomatis diarahkan ke halaman **Setup** untuk membuat akun admin pertama (pilih username & password sendiri) — tidak ada lagi akun default `admin` / `admin123`.

## Backup & Restore Database

Admin bisa membuka menu **⚙️ Pengaturan → Backup / Restore** (link tersedia di navbar saat login sebagai admin) untuk:

- **Unduh Backup** — membuat snapshot database (`.sqlite`) yang konsisten (memakai `VACUUM INTO` di SQLite) lalu mengunduhnya langsung ke komputer. Cocok dijadwalkan berkala (misalnya lewat cron yang membuka URL ini) atau dijalankan manual sebelum melakukan perubahan besar.
- **Pulihkan dari Backup** — mengunggah file `.sqlite` hasil backup untuk menggantikan database yang sedang aktif. Sebelum ditimpa:
  - File yang diunggah divalidasi dulu (harus file SQLite asli dan punya tabel `users` & `links`).
  - Database yang sedang aktif otomatis disalin ke `data/backups/before-restore-<tanggal>.sqlite` sebagai jaga-jaga jika restore keliru.
  - Setelah restore berhasil, sesi login mungkin perlu login ulang jika akun admin di file backup berbeda dari sesi saat ini.

⚠️ Restore akan **menimpa seluruh data** (user & tautan) dengan isi file yang diunggah — pastikan file yang dipilih benar sebelum konfirmasi.

## Catatan Keamanan

- Akun admin pertama dibuat sendiri lewat halaman Setup saat instalasi awal, jadi tidak ada kredensial default yang perlu diganti. Tetap gunakan password yang kuat & unik.
- Jika server menggunakan Apache, file `data/.htaccess` sudah memblokir akses langsung ke database. Jika menggunakan Nginx, tambahkan rule serupa secara manual, misalnya:
  ```nginx
  location /data/ {
      deny all;
  }
  ```
- Aplikasi ini tidak menyediakan pendaftaran akun terbuka — admin yang membuat akun user baru lewat menu Kelola User.
- Folder `data/backups/` (tempat salinan pengaman otomatis sebelum restore) ikut terlindungi oleh `data/.htaccess` di atas. Jika memakai Nginx, pastikan aturan `location /data/ { deny all; }` juga mencakup folder ini.
