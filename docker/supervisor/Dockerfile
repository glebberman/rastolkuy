FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    supervisor \
    nginx \
    postgresql-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    oniguruma-dev \
    curl \
    git \
    unzip \
    netcat-openbsd \
    gettext \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    sockets

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create application directory
WORKDIR /var/www

# Copy application code
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Copy supervisor configuration
COPY docker/supervisor/conf.d/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# Create log directories
RUN mkdir -p /var/www/storage/logs \
    && touch /var/www/storage/logs/worker.log \
    && touch /var/www/storage/logs/document-analysis-worker.log \
    && touch /var/www/storage/logs/document-processing-worker.log \
    && chown -R www-data:www-data /var/www/storage/logs

# Copy supervisor main config
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Copy entrypoint script
COPY docker/supervisor/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port
EXPOSE 9001

# Start with entrypoint
ENTRYPOINT ["/entrypoint.sh"]