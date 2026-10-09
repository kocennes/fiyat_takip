#!/bin/sh
set -eu

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-fiyattakip_demo}"
export DB_USERNAME="${DB_USERNAME:-fiyattakip}"
export DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD must be set}"
export CACHE_DRIVER="${CACHE_DRIVER:-file}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export FILESYSTEM_DISK="${FILESYSTEM_DISK:-debian_docker}"
export PORT="${PORT:-10000}"

if [ "$(dpkg --print-architecture)" = "amd64" ]; then
    export SNAPPDF_CHROMIUM_PATH=/usr/bin/google-chrome-stable
else
    export SNAPPDF_CHROMIUM_PATH=/usr/bin/chromium
fi

mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld /var/lib/mysql
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql
fi
mariadbd --user=mysql --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock --pid-file=/run/mysqld/mysqld.pid --bind-address=127.0.0.1 &

until mariadb-admin --socket=/run/mysqld/mysqld.sock -uroot ping --silent; do
    sleep 1
done

mariadb --socket=/run/mysqld/mysqld.sock -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

mkdir -p /var/www/html/public \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache
if [ "$(ls -A /tmp/public)" ]; then
    rm -rf /var/www/html/public/.htaccess /var/www/html/public/.well-known /var/www/html/public/*
    cp -r /tmp/public/* /tmp/public/.htaccess /tmp/public/.well-known /var/www/html/public/
fi
mkdir -p /var/www/html/public/images
cp /opt/bisavunma/fiyattakip-logo.svg /var/www/html/public/images/fiyattakip-logo.svg
rsync -a --exclude index.html /opt/bisavunma/ui/ /var/www/html/public/
chown -R www-data:www-data /var/www/html/public /var/www/html/storage

if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
fi

runuser -u www-data -- php artisan migrate --force
runuser -u www-data -- php artisan cache:clear
runuser -u www-data -- php artisan ninja:design-update

if [ "$(runuser -u www-data -- php artisan tinker --execute='echo Schema::hasTable("accounts") && !App\Models\Account::query()->exists();')" = "1" ]; then
    runuser -u www-data -- php artisan db:seed --force
    : "${IN_USER_EMAIL:?IN_USER_EMAIL must be set in Render}"
    : "${IN_PASSWORD:?IN_PASSWORD must be set in Render}"
    runuser -u www-data -- php artisan ninja:create-account --email "$IN_USER_EMAIL" --password "$IN_PASSWORD"
fi

php-fpm -D
envsubst '$PORT' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
exec nginx -g 'daemon off;'
