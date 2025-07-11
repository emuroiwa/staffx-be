# Multi-stage Dockerfile for high-performance Laravel backend

# Stage 1: Build dependencies and prepare application
FROM php:8.2-fpm-alpine AS builder

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    build-base \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    zlib-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    postgresql-dev \
    mysql-client \
    autoconf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        mysqli \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pcntl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader

# Copy application files
COPY . .

# Generate autoloader and optimize for production
RUN composer dump-autoload --optimize --classmap-authoritative

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Stage 2: Production image
FROM php:8.2-fpm-alpine AS production

# Install runtime dependencies only
RUN apk add --no-cache \
    freetype \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    zlib-dev \
    oniguruma \
    postgresql-dev \
    mysql-client \
    nginx \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        mysqli \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pcntl \
        zip

# Copy redis extension and config from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-redis.ini /usr/local/etc/php/conf.d/

# Copy optimized application from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/nginx \
    && mkdir -p /var/run/nginx \
    && mkdir -p /var/cache/nginx

# Set working directory
WORKDIR /var/www/html

# Health check
COPY docker/scripts/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Stage 3: Queue worker image
FROM production AS queue-worker

# Copy queue worker supervisor config
COPY docker/supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf

# Override CMD for queue worker
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Stage 4: Scheduler image  
FROM production AS scheduler

# Copy scheduler supervisor config
COPY docker/supervisor/scheduler.conf /etc/supervisor/conf.d/scheduler.conf

# Override CMD for scheduler
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
