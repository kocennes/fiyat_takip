#!/bin/sh
set -eu

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
# Render already redirects public HTTP traffic to HTTPS and forwards it to this
# container over HTTP. Laravel must not attempt to force HTTPS a second time.
export REQUIRE_HTTPS=false
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-fiyattakip_demo}"
export DB_USERNAME="${DB_USERNAME:-fiyattakip}"
# Blueprint deployments receive DB_PASSWORD from render.yaml. Generate an
# ephemeral value only for manually-created demo services where it is absent.
if [ -z "${DB_PASSWORD:-}" ]; then
    export DB_PASSWORD="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)"
fi
export CACHE_DRIVER="${CACHE_DRIVER:-file}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export FILESYSTEM_DISK="${FILESYSTEM_DISK:-debian_docker}"
export PORT="${PORT:-10000}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

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
install -m 0644 /opt/bisavunma/index.php /var/www/html/public/index.php
rsync -a --exclude index.html /opt/bisavunma/ui/ /var/www/html/public/
rsync -a /opt/bisavunma/public/ /var/www/html/public/
chown -R www-data:www-data /var/www/html/public /var/www/html/storage

if [ -z "${APP_KEY:-}" ]; then
    # Compatibility fallback for a service created outside the Blueprint.
    export APP_KEY="$(php artisan key:generate --show)"
elif [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    # Render's generateValue is base64-encoded; Laravel expects its prefix.
    export APP_KEY="base64:${APP_KEY}"
fi

runuser -u www-data -- php artisan migrate --force
runuser -u www-data -- php artisan cache:clear
runuser -u www-data -- php artisan view:clear
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
