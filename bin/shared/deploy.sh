#!/bin/sh
# Install or update the shop on shared hosting (no Docker). Safe to run again.
#   sh bin/shared/deploy.sh            update after "git pull"
#   sh bin/shared/deploy.sh --first    first installation: also loads the catalogue and creates the administrator
set -eu
cd "$(dirname "$0")/../.."

[ -f .env ] || { echo "Create .env first: php bin/shared/setup_env.php" >&2; exit 1; }

if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
elif [ -f composer.phar ]; then
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
else
    echo "Composer was not found. See https://getcomposer.org/download/ and put composer.phar here." >&2
    exit 1
fi

php bin/shared/check_server.php
php bin/migrate.php
if [ "${1:-}" = "--first" ]; then
    # The catalogue only. Demonstration data is never loaded on a real server.
    php bin/seed.php
    php bin/create_admin.php
fi

mkdir -p public/uploads
# PHP settings for the website: never show errors, allow photo uploads, stay within the memory limit
cat > public/.user.ini <<'INI'
display_errors = Off
log_errors = On
upload_max_filesize = 3M
post_max_size = 14M
memory_limit = 128M
INI

echo "Done. Next: php bin/shared/check_site.php"
