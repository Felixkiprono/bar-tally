#!/bin/sh
set -e

echo "▶ Starting bar-tally container..."

###############################################
# 1) WAIT FOR DATABASE FIRST
###############################################

echo "⏳ Running wait-for-db..."
/wait-for-db.sh

###############################################
# 2) LARAVEL INIT TASKS
###############################################

# Generate APP_KEY only if missing
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 No APP_KEY found — generating..."
    php artisan key:generate
else
    echo "🔑 APP_KEY exists — skipping..."
fi

echo "🧹 Clearing Laravel caches..."
php artisan optimize:clear || true

echo "🗄 Running migrations..."
php artisan migrate --force || true

###############################################
# 3) START PHP-FPM
###############################################

echo "🚀 Starting PHP-FPM..."
exec php-fpm
