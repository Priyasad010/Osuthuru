#!/bin/sh
# Copy the database and the uploaded files into ~/osuthuru-backups, keeping 14 days.
# Also deletes old assistant chats. Run daily with cron (see docs/HOSTING.md).
set -eu
cd "$(dirname "$0")/../.."

target=${BACKUP_DIR:-$HOME/osuthuru-backups}
stamp=$(date +%Y-%m-%d_%H%M)
mkdir -p "$target"
chmod 700 "$target"
umask 077
compose() { docker compose -f docker-compose.prod.yml "$@"; }

compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --default-character-set=utf8mb4 "$MYSQL_DATABASE"' > "$target/db_$stamp.sql"
# A dump cut short by an error must not pass for a backup
tail -n 1 "$target/db_$stamp.sql" | grep -q "Dump completed" || { echo "Database backup failed." >&2; rm -f "$target/db_$stamp.sql"; exit 1; }
gzip -f "$target/db_$stamp.sql"
tar -czf "$target/uploads_$stamp.tar.gz" -C public uploads

find "$target" -type f \( -name 'db_*.sql.gz' -o -name 'uploads_*.tar.gz' \) -mtime +14 -delete
compose exec -T web php bin/purge_chat.php
echo "Backup written to $target"
