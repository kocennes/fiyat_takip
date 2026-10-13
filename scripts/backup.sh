#!/usr/bin/env bash
# BISAVUNMA Fiyat Takip - database and file backup of the production stack.
#
#   ./scripts/backup.sh [target directory]        (default: ./backups)
#
# Writes db-<time>.sql.gz (MySQL dump) and storage-<time>.tar.gz (uploaded
# logos, documents and generated files) and deletes backups older than
# KEEP_DAYS (default 14). Nightly at 02:30 via cron:
#
#   30 2 * * * cd /opt/fiyat-takip && ./scripts/backup.sh >> backups/backup.log 2>&1
#
# Copy the backups to a second location (another server or NAS) as well.
set -euo pipefail

cd "$(dirname "$0")/.."

ENV_FILE="${ENV_FILE:-.env.production}"
DEST="${1:-./backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
compose=(docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml)

mkdir -p "$DEST"

# Consistent InnoDB snapshot without locking the tables.
"${compose[@]}" exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$DEST/db-$STAMP.sql.gz"

"${compose[@]}" exec -T app tar -C /var/www/html -czf - storage/app > "$DEST/storage-$STAMP.tar.gz"

find "$DEST" -maxdepth 1 \( -name 'db-*.sql.gz' -o -name 'storage-*.tar.gz' \) -mtime +"$KEEP_DAYS" -delete

echo "$(date '+%F %T') yedek tamam: $DEST/db-$STAMP.sql.gz $DEST/storage-$STAMP.tar.gz"
