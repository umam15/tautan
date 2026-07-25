# Tautan

Aplikasi manajemen bookmark/tautan sederhana berbasis PHP + PDO SQLite.

## Fitur

- **Data user & link disimpan di SQLite** lewat PDO (file otomatis dibuat di `data/bookmarks.sqlite` saat pertama kali diakses).
- **Admin** bisa mengelola user: tambah, edit (username/role/password), hapus (tidak bisa menghapus diri sendiri atau admin terakhir).
- **User login** bisa membuat, menyunting, menghapus link, serta **mengatur urutan link** lewat drag-and-drop (disimpan otomatis).
- **Tamu (tanpa login)** tetap bisa melihat dan membuka link yang berstatus **publik**.
- **Link privat**: setiap link punya opsi visibilitas — `Publik` (semua orang bisa lihat) atau `🔒 Hanya user login` (hanya tampil untuk yang sudah login).
- Proteksi dasar: password di-hash (`password_hash`), semua query pakai prepared statement (PDO), proteksi CSRF di setiap form/aksi POST, output di-escape (XSS-safe).

## Struktur Folder

```
bookmark-links/
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
├── admin_users.php           # Daftar user (khusus admin)
├── admin_user_form.php       # Tambah & edit user (khusus admin)
├── admin_user_delete.php
├── assets/
│   ├── style.css
│   └── app.js                # Drag-and-drop (pakai SortableJS via CDN)
└── data/
    ├── .htaccess              # Blokir akses langsung ke folder data
    └── bookmarks.sqlite       # Dibuat otomatis, jangan di-commit ke publik
```

## Instalasi

1. Salin folder `bookmark-links/` ke server PHP (butuh PHP 8+ dengan ekstensi `pdo_sqlite`).
2. Pastikan folder `data/` bisa ditulis oleh web server:
   ```bash
   chmod 775 data
   ```
3. Akses `index.php` lewat browser. Tabel & akun admin default akan dibuat otomatis pada request pertama.
4. **Login default:**
   - Username: `admin`
   - Password: `admin123`

   ⚠️ **Segera ganti password admin default** setelah login pertama (lewat menu Kelola User → Edit).

## Catatan Keamanan

- Ganti password admin default sesegera mungkin.
- Jika server menggunakan Apache, file `data/.htaccess` sudah memblokir akses langsung ke database. Jika menggunakan Nginx, tambahkan rule serupa secara manual, misalnya:
  ```nginx
  location /data/ {
      deny all;
  }
  ```
- Aplikasi ini tidak menyediakan pendaftaran akun terbuka — admin yang membuat akun user baru lewat menu Kelola User.
