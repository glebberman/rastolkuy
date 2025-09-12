#!/bin/bash

set -e

echo "🚀 Starting Laravel Queue Supervisor..."

# Set default values for environment variables
export QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}
export DOCUMENT_ANALYSIS_QUEUE=${DOCUMENT_ANALYSIS_QUEUE:-document-analysis}
export DOCUMENT_PROCESSING_QUEUE=${DOCUMENT_PROCESSING_QUEUE:-document-processing}
export ANALYSIS_JOB_MAX_TRIES=${ANALYSIS_JOB_MAX_TRIES:-3}
export ANALYSIS_JOB_TIMEOUT=${ANALYSIS_JOB_TIMEOUT:-300}

echo "📋 Queue Configuration:"
echo "  - QUEUE_CONNECTION: $QUEUE_CONNECTION"
echo "  - DOCUMENT_ANALYSIS_QUEUE: $DOCUMENT_ANALYSIS_QUEUE"
echo "  - DOCUMENT_PROCESSING_QUEUE: $DOCUMENT_PROCESSING_QUEUE"
echo "  - ANALYSIS_JOB_MAX_TRIES: $ANALYSIS_JOB_MAX_TRIES"
echo "  - ANALYSIS_JOB_TIMEOUT: $ANALYSIS_JOB_TIMEOUT"

# Wait for dependencies
echo "⏳ Waiting for Redis..."
until nc -z ${REDIS_HOST:-redis} ${REDIS_PORT:-6379}; do
  echo "Redis is unavailable - sleeping"
  sleep 1
done
echo "✅ Redis is ready!"

echo "⏳ Waiting for Database..."
until nc -z ${DB_HOST:-postgres} ${DB_PORT:-5432}; do
  echo "Database is unavailable - sleeping"
  sleep 1
done
echo "✅ Database is ready!"

# Generate Laravel app key if not exists
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating Laravel application key..."
    cd /var/www && php artisan key:generate --force
fi

# Run migrations if needed
echo "🔄 Running database migrations..."
cd /var/www && php artisan migrate --force

# Clear and cache configuration
echo "⚡ Optimizing Laravel..."
cd /var/www && php artisan config:cache
cd /var/www && php artisan route:cache
cd /var/www && php artisan view:cache

# Ensure log directories exist with proper permissions
mkdir -p /var/www/storage/logs
touch /var/www/storage/logs/worker.log
touch /var/www/storage/logs/document-analysis-worker.log  
touch /var/www/storage/logs/document-processing-worker.log
chown -R www-data:www-data /var/www/storage/logs

# Set proper permissions for Laravel storage
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Process Supervisor configuration template with environment variables
envsubst < /etc/supervisor/conf.d/laravel-worker.conf > /tmp/laravel-worker.conf
mv /tmp/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

echo "🎯 Starting Supervisor with the following workers:"
echo "  - laravel-worker (2 processes)"
echo "  - laravel-document-analysis-worker (1 process)"
echo "  - laravel-document-processing-worker (2 processes)"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf