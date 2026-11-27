#!/bin/sh
# Copy the database and uploads into ~/osuthuru-backups, keeping 7 days, and delete old chats.
# Run daily as a scheduled task (see docs/HOSTING_ALWAYSDATA.md).
set -eu
cd "$(dirname "$0")/../.."

target=${BACKUP_DIR:-$HOME/osuthuru-backups}
stamp=$(date +%Y-%m-%d_%H%M)
mkdir -p "$target"
chmod 700 "$target"
umask 077

setting() { php -r 'require "vendor/autoload.php"; App\Core\Env::load(getcwd()); echo App\Core\Env::get($argv[1], "");' "$1"; }
dump=$(command -v mariadb-dump || command -v mysqldump) || { echo "No database dump program found." >&2; exit 1; }

MYSQL_PWD=$(setting DB_PASS) "$dump" --host="$(setting DB_HOST)" --port="$(setting DB_PORT)" --user="$(setting DB_USER)" \
    --single-transaction --no-tablespaces --default-character-set=utf8mb4 "$(setting DB_NAME)" > "$target/db_$stamp.sql"
# A dump cut short by an error must not pass for a backup
tail -n 1 "$target/db_$stamp.sql" | grep -q "Dump completed" || { echo "Database backup failed." >&2; rm -f "$target/db_$stamp.sql"; exit 1; }
gzip -f "$target/db_$stamp.sql"
tar -czf "$target/uploads_$stamp.tar.gz" -C public uploads

# The free plan has little disk space
find "$target" -type f \( -name 'db_*.sql.gz' -o -name 'uploads_*.tar.gz' \) -mtime +7 -delete
php bin/purge_chat.php
echo "Backup written to $target"
