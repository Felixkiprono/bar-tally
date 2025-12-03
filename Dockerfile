FROM php:8.3-fpm

# Install required extensions
RUN apt-get update && apt-get install -y \
    libzip-dev libonig-dev libicu-dev libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
    unzip git curl pkg-config default-mysql-client \
    && docker-php-ext-install pdo_mysql zip intl pcntl exif \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install gd

# Install netcat for wait-for-db.sh
RUN apt-get update && apt-get install -y netcat-openbsd

# Set working directory
WORKDIR /var/www

# Copy app files
COPY . .

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy health/wait script
COPY docker/wait-for-db.sh /wait-for-db.sh
RUN chmod +x /wait-for-db.sh

# Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/wait-for-db.sh /wait-for-db.sh

RUN chmod +x /entrypoint.sh /wait-for-db.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
