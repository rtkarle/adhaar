# ═══════════════════════════════════════════════════════════════
#  Adhaar – PHP Web App Dockerfile
#  Base: PHP 8.2 + Apache
#  Includes: mysqli, pdo_mysql, curl, mbstring, fileinfo, gd
# ═══════════════════════════════════════════════════════════════

FROM php:8.2-apache

# ── System dependencies ───────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        mysqli \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        gd \
        fileinfo \
        opcache

# ── Enable Apache mod_rewrite (for .htaccess) ─────────────────
RUN a2enmod rewrite

# ── Apache config — allow .htaccess overrides ─────────────────
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# ── PHP config for production ─────────────────────────────────
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=12M'; \
    echo 'max_execution_time=60'; \
    echo 'memory_limit=256M'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/var/log/apache2/php_errors.log'; \
} > /usr/local/etc/php/conf.d/adhaar.ini

# ── Copy application files ────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Ensure uploads directory exists and is writable ───────────
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# ── Set correct permissions on all files ─────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# ── Expose port 80 ────────────────────────────────────────────
EXPOSE 80

# Apache runs in foreground by default in this base image
