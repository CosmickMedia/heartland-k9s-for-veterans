#!/usr/bin/env bash
# One-shot local setup for the Heartland Canines for Veterans build.
#
#   docker compose -f docker/docker-compose.yml up -d
#   docker/setup.sh            # install core, activate theme + plugin
#   docker/setup.sh --import   # ...and run the content/media import from ./payload
#
# Idempotent: safe to re-run.
set -uo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker/docker-compose.yml"
SITE_URL="http://localhost:8093"
wp() { $COMPOSE run --rm -T wpcli "$@"; }

echo "==> Waiting for WordPress core files…"
for i in $(seq 1 60); do
  if wp core version >/dev/null 2>&1; then break; fi
  sleep 2
done
echo "    WordPress $(wp core version 2>/dev/null)"

echo "==> Installing WordPress (if needed)…"
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$SITE_URL" \
    --title="Heartland Canines for Veterans" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@hk9.test \
    --skip-email
else
  echo "    already installed"
fi

wp option update home "$SITE_URL" >/dev/null
wp option update siteurl "$SITE_URL" >/dev/null
wp option update timezone_string "America/Chicago" >/dev/null
wp option update blogdescription "So They Never Walk Alone" >/dev/null
wp rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true

echo "==> Activating companion plugin + theme…"
wp plugin activate heartland-k9s-core 2>&1 | tail -1
wp theme activate heartland-k9s 2>&1 | tail -1
wp rewrite flush --hard >/dev/null 2>&1 || true

# Remove default sample content on a fresh install only.
if [ "$(wp post list --post_type=post --format=count 2>/dev/null)" = "1" ] && wp post get 1 --field=post_name 2>/dev/null | grep -q '^hello-world$'; then
  wp post delete 1 --force >/dev/null 2>&1 || true
  wp post delete 2 --force >/dev/null 2>&1 || true
fi

if [ "${1:-}" = "--import" ]; then
  echo "==> Importing payload…"
  wp hk9 import /var/www/html/wp-content/hk9-payload --user=admin
fi

echo ""
echo "======================================================================"
echo " Ready."
echo "   Site:     $SITE_URL"
echo "   Admin:    $SITE_URL/wp-admin   (admin / admin)"
echo "   Mailpit:  http://localhost:8094"
echo "   WP-CLI:   $COMPOSE run --rm wpcli <command>"
echo "======================================================================"
