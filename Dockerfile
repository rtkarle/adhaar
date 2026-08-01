# ═══════════════════════════════════════════════════════════════
#  Adhaar – PHP Web App Dockerfile
#  Base: PHP 8.2 + Apache
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
    git \
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

# ── Enable Apache mod_rewrite ─────────────────────────────────
RUN a2enmod rewrite headers

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
} > /usr/local/etc/php/conf.d/adhaar.ini

# ── Install Composer ──────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Copy application files ────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Install PHP dependencies (Google API client etc.) ─────────
# Skip if vendor/ already exists in repo
RUN if [ -f composer.json ] && [ ! -d vendor ]; then \
    composer install --no-dev --optimize-autoloader --no-interaction; \
fi

# ── Ensure uploads directory exists and is writable ───────────
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# ── Set correct permissions ───────────────────────────────────
RUN chown -R www-data:www-data /var/www/html

# ── Expose port 80 ────────────────────────────────────────────
EXPOSE 80
