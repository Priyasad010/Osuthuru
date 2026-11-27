#!/bin/sh
# Install or update the shop on the server. Safe to run again.
#   sh bin/prod/deploy.sh            update after "git pull"
#   sh bin/prod/deploy.sh --first    first installation: also loads the catalogue and creates the administrator
set -eu
cd "$(dirname "$0")/../.."

[ -f .env ] || { echo "Create .env first: sh bin/prod/make_env.sh <domain> <admin e-mail>" >&2; exit 1; }
compose() { docker compose -f docker-compose.prod.yml "$@"; }

compose up -d --build --wait db
compose up -d --build
compose exec -T web composer install --no-dev --optimize-autoloader --no-interaction
compose exec -T web php bin/migrate.php
if [ "${1:-}" = "--first" ]; then
    # The catalogue only. Demonstration data is never loaded on a real server.
    compose exec -T web php bin/seed.php
    compose exec -T web php bin/create_admin.php
fi
compose exec -T web php bin/grant_ml_user.php
# Uploads are written by the web server user
compose exec -T web sh -c 'mkdir -p public/uploads && chown -R www-data:www-data public/uploads'
compose restart web ml
compose ps
if grep -qE '^MAIL_HOST=("")?[[:space:]]*$' .env; then
    echo "WARNING: MAIL_HOST is empty in .env. New customers cannot confirm their e-mail until it is set." >&2
fi
echo "Done. Check e-mail with: docker compose -f docker-compose.prod.yml exec web php bin/test_mail.php you@example.com"
