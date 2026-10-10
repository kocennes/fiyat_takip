#!/bin/sh -eu

if [ "$(dpkg --print-architecture)" = "amd64" ]; then
    export SNAPPDF_CHROMIUM_PATH=/usr/bin/google-chrome-stable
elif [ "$(dpkg --print-architecture)" = "arm64" ]; then
    export SNAPPDF_CHROMIUM_PATH=/usr/bin/chromium
fi

if [ "$*" = 'supervisord -c /etc/supervisor/supervisord.conf' ]; then
    mkdir -p /var/www/html/public \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/framework/cache

    if [ "$(ls -A /tmp/public)" ]; then
        rm -rf /var/www/html/public/.htaccess \
            /var/www/html/public/.well-known \
            /var/www/html/public/*
        cp -r /tmp/public/* /tmp/public/.htaccess /tmp/public/.well-known /var/www/html/public/
        rm -rf /tmp/public/.htaccess /tmp/public/.well-known /tmp/public/*
    fi

    rsync -a --exclude index.html /opt/bisavunma/ui/ /var/www/html/public/
    rsync -a /opt/bisavunma/public/ /var/www/html/public/

    chown -R www-data:www-data /var/www/html/public /var/www/html/storage
    find /var/www/html/public /var/www/html/storage -type f -exec chmod 644 {} \;
    find /var/www/html/public /var/www/html/storage -type d -exec chmod 755 {} \;

    if [ "${APP_ENV:-production}" = "production" ]; then
        runuser -u www-data -- php artisan migrate --force
        runuser -u www-data -- php artisan cache:clear
        runuser -u www-data -- php artisan ninja:design-update
        runuser -u www-data -- php artisan optimize

        if [ "$(runuser -u www-data -- php artisan tinker --execute='echo Schema::hasTable("accounts") && !App\Models\Account::query()->exists();')" = "1" ]; then
            runuser -u www-data -- php artisan db:seed --force
            : "${IN_USER_EMAIL:?IN_USER_EMAIL must be set}"
            : "${IN_PASSWORD:?IN_PASSWORD must be set}"
            runuser -u www-data -- php artisan ninja:create-account --email "$IN_USER_EMAIL" --password "$IN_PASSWORD"
        fi
    fi
fi

exec "$@"
