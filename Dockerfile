# ═══════════════════════════════════════════════════════════════
#  SoulServe – PHP Web App Dockerfile  (Fixed for Render)
#  PHP 8.2 + Apache
#  - Handles Render $PORT env var via entrypoint
#  - composer install included
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
    zip unzip curl git \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        mysqli pdo pdo_mysql mbstring xml zip gd fileinfo opcache

# ── Apache modules ────────────────────────────────────────────
RUN a2enmod rewrite headers

# ── Apache: allow .htaccess overrides sitewide ────────────────
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ── PHP production config ─────────────────────────────────────
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=12M'; \
    echo 'max_execution_time=60'; \
    echo 'memory_limit=256M'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/dev/stderr'; \
} > /usr/local/etc/php/conf.d/adhaar.ini

# ── Composer ──────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App files ─────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# Remove local .env — Render injects secrets as env vars
RUN rm -f .env .env.local .env.production

# ── PHP dependencies ──────────────────────────────────────────
RUN if [ -f composer.json ] && [ ! -d vendor ]; then \
    composer install --no-dev --optimize-autoloader --no-interaction; \
fi

# ── Uploads dir ───────────────────────────────────────────────
RUN mkdir -p uploads \
    && chown -R www-data:www-data uploads \
    && chmod -R 755 uploads

# ── Permissions ───────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html

# ── Entrypoint (handles Render $PORT) ─────────────────────────
RUN chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80

CMD ["/var/www/html/docker-entrypoint.sh"]
