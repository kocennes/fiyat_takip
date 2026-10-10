FROM node:22-bookworm AS frontend-build

WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_IS_HOSTED=false \
    VITE_APP_TITLE="BISAVUNMA Fiyat Takip" \
    VITE_ENABLE_DOCUNINJA=false
RUN npm run build

# backend/ is the upstream 5.13.47 release source plus the BISAVUNMA changes.
# The base image must stay on exactly that release: the overlaid PHP, Blade and
# language files below rely on the vendor libraries and compiled assets that
# ship inside the image. Upgrade both together.
FROM invoiceninja/invoiceninja-debian:5.13.47 AS app

USER root

ENV APP_NAME="BISAVUNMA Fiyat Takip" \
    DEFAULT_LOCALE=tr_TR \
    MAIL_FROM_NAME="BISAVUNMA Fiyat Takip"

COPY --chown=www-data:www-data backend/app/ /var/www/html/app/
COPY --chown=www-data:www-data backend/config/ /var/www/html/config/
COPY --chown=www-data:www-data backend/database/ /var/www/html/database/
COPY --chown=www-data:www-data backend/lang/ /var/www/html/lang/
COPY --chown=www-data:www-data backend/resources/views/ /var/www/html/resources/views/
COPY --chown=www-data:www-data backend/routes/ /var/www/html/routes/
# Windows checkouts use CRLF line endings; keep the overlay byte-identical to a
# Linux checkout so local and Render builds behave the same.
RUN find /var/www/html/app /var/www/html/config /var/www/html/database \
        /var/www/html/lang /var/www/html/resources/views /var/www/html/routes \
        -type f \( -name '*.php' -o -name '*.html' -o -name '*.json' -o -name '*.js' \
        -o -name '*.css' -o -name '*.xml' -o -name '*.xsd' -o -name '*.xsl' \
        -o -name '*.xslt' -o -name '*.sch' -o -name '*.txt' \) \
        -exec sed -i 's/\r$//' {} +

# The entrypoints rebuild the public asset directory at startup. Keep Laravel's
# front controller and the branded assets outside that directory so they cannot
# be removed while it is refreshed.
RUN mkdir -p /opt/bisavunma && cp /tmp/public/index.php /opt/bisavunma/index.php
COPY --from=frontend-build /frontend/dist /opt/bisavunma/ui
COPY --from=frontend-build --chown=www-data:www-data /frontend/dist/index.html /var/www/html/resources/views/react/index.blade.php
# BISAVUNMA images published under the upstream file names, so any remaining
# reference to an upstream logo or badge shows the BISAVUNMA brand instead.
COPY docker/branding/public/ /opt/bisavunma/public/
COPY docker/app-init.sh /usr/local/bin/app-init.sh
RUN sed -i 's/\r$//' /usr/local/bin/app-init.sh && chmod 0755 /usr/local/bin/app-init.sh

FROM app AS local
ENTRYPOINT ["/usr/local/bin/app-init.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

# The final stage is used by Render as an intentionally ephemeral public demo.
FROM app AS render
RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server nginx gettext-base \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
COPY docker/render/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/render/start.sh /usr/local/bin/render-start.sh
RUN sed -i 's/\r$//' /usr/local/bin/render-start.sh && chmod 0755 /usr/local/bin/render-start.sh
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/render-start.sh"]
