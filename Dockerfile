FROM php:8.2-apache

# Ekstensi yang dibutuhkan aplikasi (PDO SQLite)
# libsqlite3-dev diperlukan supaya proses build ekstensi pdo_sqlite berhasil
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite

# Izinkan .htaccess di folder data/ (AllowOverride)
RUN { \
        echo '<Directory /var/www/html/>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Salin source aplikasi
COPY . /var/www/html/

# Pastikan folder data ada & bisa ditulis oleh user apache (www-data)
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data

EXPOSE 80
