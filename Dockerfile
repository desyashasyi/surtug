# Gunakan PHP 8.2 dengan FPM
FROM php:8.2-fpm

# Tambahkan command untuk membuat symlink saat container start
RUN echo "#!/bin/sh\n\
mkdir -p /var/www/watcher \n\
ln -sf /mnt/fet-results /var/www/watcher/fet-results \n\
exec php-fpm" > /usr/local/bin/startup
RUN chmod +x /usr/local/bin/startup

ENTRYPOINT ["/usr/local/bin/startup"]

# Install dependensi sistem
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    gnupg \
    ca-certificates \
    sudo

# Install PHP extension
RUN docker-php-ext-install pdo pdo_mysql mbstring xml zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js LTS (misal versi 20.x)
# Tambahkan setelah baris RUN apt-get update ...
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs


# Set working directory
WORKDIR /var/www/html
COPY . .

# Permission Laravel
RUN mkdir -p /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

RUN mkdir -p /var/www/html/storage/app/fet-results && \
    chown -R www-data:www-data /var/www/html/storage/app/fet-results && \
    chmod -R 775 /var/www/html/storage/app/fet-results

# Set user untuk Laravel
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Expose port untuk FPM
EXPOSE 9000

# Jalankan php-fpm
CMD ["php-fpm"]
