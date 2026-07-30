# Tautan — image PHP + Apache dengan ekstensi SQLite aktif
FROM php:8.2-apache

# Ekstensi PHP yang dibutuhkan aplikasi ini (lihat README > Kebutuhan Sistem):
# - pdo & pdo_sqlite: koneksi database lewat PDO
# - sqlite3 (CLI): dipakai validate_sqlite_schema() saat restore backup
# - mbstring: dipakai mb_strtolower()/mb_strlen() di index.php & admin/appearance.php
#   (TIDAK ikut terpasang secara default di image php:8.2-apache, harus dikompilasi manual)
#
# PENTING: header/dev package (libsqlite3-dev, libonig-dev) harus terpasang DULU
# sebelum docker-php-ext-install, karena proses build ekstensi butuh pkg-config +
# library dev tersebut. Urutan terbalik akan gagal dengan error
# "Package 'sqlite3' ... not found".
RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 libsqlite3-dev libonig-dev pkg-config \
    && docker-php-ext-install pdo pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*

# Izinkan .htaccess (proteksi folder data/) berfungsi di Apache
RUN a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Salin source aplikasi
COPY . /var/www/html/

# Permission awal di image (dipakai saat volume masih kosong / pertama kali dibuat)
RUN mkdir -p /var/www/html/data/backups \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

# Entrypoint kustom (di /usr/local/bin, di luar document root) yang memperbaiki
# ulang kepemilikan data/ setiap container start. Ini penting karena volume
# Docker bisa punya permission berbeda dari image saat build — terutama
# volume lama sisa percobaan sebelumnya — sehingga chown di atas saja tidak
# cukup begitu volume di-mount menimpa folder data/.
RUN printf '#!/bin/sh\nset -e\nmkdir -p /var/www/html/data/backups\nchown -R www-data:www-data /var/www/html/data\nchmod -R 775 /var/www/html/data\nexec docker-php-entrypoint "$@"\n' > /usr/local/bin/tautan-entrypoint.sh \
    && chmod +x /usr/local/bin/tautan-entrypoint.sh

ENTRYPOINT ["tautan-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
