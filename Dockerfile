FROM node:22-bookworm AS frontend-build

WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_IS_HOSTED=false \
    VITE_APP_TITLE="BISAVUNMA Fiyat Takip" \
    VITE_ENABLE_DOCUNINJA=false
RUN npm run build

FROM invoiceninja/invoiceninja-debian:latest AS app

USER root

COPY backend/app/Factory/InvoiceFactory.php /var/www/html/app/Factory/InvoiceFactory.php
COPY backend/app/Factory/QuoteFactory.php /var/www/html/app/Factory/QuoteFactory.php
COPY backend/database/seeders/DesignSeeder.php /var/www/html/database/seeders/DesignSeeder.php
COPY backend/database/migrations/2026_10_09_000000_add_proforma_design.php /var/www/html/database/migrations/
COPY backend/database/migrations/2026_10_09_000001_set_proforma_as_default_design.php /var/www/html/database/migrations/
COPY backend/resources/views/pdf-designs/proforma.html /var/www/html/resources/views/pdf-designs/proforma.html
COPY backend/public/images/fiyattakip-logo.svg /opt/bisavunma/fiyattakip-logo.svg
COPY --from=frontend-build /frontend/dist /opt/bisavunma/ui
COPY --from=frontend-build /frontend/dist/index.html /var/www/html/resources/views/react/index.blade.php
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
