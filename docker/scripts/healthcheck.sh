#!/bin/sh

# Health check script for Laravel application

set -e

# Check if PHP-FPM is running
if ! pgrep -f "php-fpm" > /dev/null; then
    echo "PHP-FPM is not running"
    exit 1
fi

# Check if Nginx is running
if ! pgrep -f "nginx" > /dev/null; then
    echo "Nginx is not running"
    exit 1
fi

# Check PHP-FPM pool status
if ! curl -f http://localhost/php-fpm-ping > /dev/null 2>&1; then
    echo "PHP-FPM pool is not responding"
    exit 1
fi

# Check application health endpoint
if ! curl -f http://localhost/health > /dev/null 2>&1; then
    echo "Application health endpoint is not responding"
    exit 1
fi

# Check database connectivity (if DB_CONNECTION is set)
if [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    if ! php /var/www/html/artisan migrate:status > /dev/null 2>&1; then
        echo "Database connectivity check failed"
        exit 1
    fi
fi

# Check Redis connectivity (if REDIS_HOST is set)
if [ -n "$REDIS_HOST" ]; then
    if ! php -r "
        try {
            \$redis = new Redis();
            \$redis->connect('$REDIS_HOST', ${REDIS_PORT:-6379});
            \$redis->ping();
            echo 'Redis OK';
        } catch (Exception \$e) {
            echo 'Redis connection failed: ' . \$e->getMessage();
            exit(1);
        }
    " > /dev/null 2>&1; then
        echo "Redis connectivity check failed"
        exit 1
    fi
fi

echo "All health checks passed"
exit 0