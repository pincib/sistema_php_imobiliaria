FROM php:8.3-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install SQLite PDO and dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite opcache \
    && a2enmod rewrite headers deflate \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache DocumentRoot to point to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Install only the runtime dependencies from the lockfile; vendor is excluded from the build context.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader

# Copy application files
COPY . /var/www/html

# Permissions for storage directory
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/backups \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80
