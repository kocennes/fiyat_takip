#!/usr/bin/env bash
# BISAVUNMA Fiyat Takip - restore a backup made by scripts/backup.sh.
#
#   ./scripts/restore.sh backups/db-<time>.sql.gz [backups/storage-<time>.tar.gz]
#
# Overwrites the current database (and, when given, the stored files).
set -euo pipefail

cd "$(dirname "$0")/.."

ENV_FILE="${ENV_FILE:-.env.production}"
DB_BACKUP="${1:?Kullanım: ./scripts/restore.sh backups/db-<zaman>.sql.gz [backups/storage-<zaman>.tar.gz]}"
STORAGE_BACKUP="${2:-}"
compose=(docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml)

[ -f "$DB_BACKUP" ] || { echo "Dosya bulunamadı: $DB_BACKUP" >&2; exit 1; }
[ -z "$STORAGE_BACKUP" ] || [ -f "$STORAGE_BACKUP" ] || { echo "Dosya bulunamadı: $STORAGE_BACKUP" >&2; exit 1; }

read -r -p "Mevcut veritabanının üzerine yazılacak. Devam etmek için EVET yazın: " answer
[ "$answer" = "EVET" ] || { echo "İptal edildi."; exit 1; }

gunzip -c "$DB_BACKUP" | "${compose[@]}" exec -T mysql sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

if [ -n "$STORAGE_BACKUP" ]; then
    "${compose[@]}" exec -T app tar -C /var/www/html -xzf - < "$STORAGE_BACKUP"
    "${compose[@]}" exec -T app chown -R www-data:www-data /var/www/html/storage/app
fi

"${compose[@]}" exec -T app runuser -u www-data -- php artisan optimize:clear
"${compose[@]}" exec -T app runuser -u www-data -- php artisan migrate --force

echo "Geri yükleme tamam."
