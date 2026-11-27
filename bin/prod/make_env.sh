#!/bin/sh
# Create .env for a server with new random passwords. Never overwrites an existing file.
#   sh bin/prod/make_env.sh shop.example.org you@example.com
set -eu
cd "$(dirname "$0")/../.."

domain=${1:-}
admin=${2:-}
if [ -z "$domain" ] || [ -z "$admin" ]; then
    echo "Usage: sh bin/prod/make_env.sh <domain> <admin e-mail>" >&2
    exit 1
fi
case $domain in
    *://*|*/*|*" "*) echo "Give the domain only, for example shop.example.org" >&2; exit 1 ;;
esac
case $admin in
    *@*.*) ;;
    *) echo "That is not an e-mail address." >&2; exit 1 ;;
esac
if [ -e .env ]; then
    echo ".env already exists; it was not changed." >&2
    exit 1
fi

random() { openssl rand -hex "$1"; }
umask 077
sed -e "s|^SITE_DOMAIN=.*|SITE_DOMAIN=$domain|" \
    -e "s|^APP_URL=.*|APP_URL=https://$domain|" \
    -e "s|^APP_KEY=.*|APP_KEY=$(random 32)|" \
    -e "s|^DB_PASS=.*|DB_PASS=$(random 24)|" \
    -e "s|^DB_ROOT_PASS=.*|DB_ROOT_PASS=$(random 24)|" \
    -e "s|^DB_ML_RO_PASS=.*|DB_ML_RO_PASS=$(random 24)|" \
    -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=$admin|" \
    -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=A1-$(random 10)|" \
    -e "s|^MAIL_FROM=.*|MAIL_FROM=$admin|" \
    .env.production.example > .env
# Apache inside the container must be able to read the settings
chmod 644 .env

echo ".env created. Your administrator password is the ADMIN_PASSWORD line in it."
echo "Now add the MAIL_ settings (and the Gemini and PayHere keys if you use them)."
